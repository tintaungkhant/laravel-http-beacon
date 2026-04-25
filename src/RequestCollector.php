<?php

namespace Tintaungkhant\TrafficMonitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Str;
use ReflectionObject;
use ReflectionProperty;

class RequestCollector
{
    private array $queries = [];

    private array $models = [];

    private array $jobs = [];

    private int $pauseDepth = 0;

    private ?array $modelActionPatterns = null;

    public function recordQuery(QueryExecuted $event): void
    {
        if ($this->pauseDepth > 0) {
            return;
        }

        $this->queries[] = [
            'sql' => $event->sql,
            'bindings' => $event->bindings,
            'time_ms' => $event->time,
            'connection' => $event->connectionName,
        ];
    }

    public function recordModel(string $event, array $data): void
    {
        if ($this->pauseDepth > 0) {
            return;
        }

        if (! Str::is($this->modelActionPatterns(), $event)) {
            return;
        }

        $model = $data[0] ?? null;

        if (! $model instanceof Model) {
            return;
        }

        $class = $model::class;
        $id = $model->getKey();
        $key = $class.':'.($id ?? '_new');
        $action = $this->extractAction($event);

        if (! isset($this->models[$key])) {
            $this->models[$key] = [
                'class' => $class,
                'id' => $id,
                'actions' => [],
            ];
        }

        if (! in_array($action, $this->models[$key]['actions'], true)) {
            $this->models[$key]['actions'][] = $action;
        }
    }

    public function recordJob(JobQueued $event): void
    {
        if ($this->pauseDepth > 0) {
            return;
        }

        $job = $event->job;
        $class = is_object($job) ? $job::class : (string) $job;

        $this->jobs[] = [
            'class' => $class,
            'connection' => $event->connectionName,
            'queue' => $event->queue,
            'payload' => is_object($job) ? $this->extractJobPayload($job) : null,
        ];
    }

    public function pause(): void
    {
        $this->pauseDepth++;
    }

    public function resume(): void
    {
        $this->pauseDepth = max(0, $this->pauseDepth - 1);
    }

    public function flush(): array
    {
        $data = [
            'queries' => $this->queries,
            'models' => array_values($this->models),
            'jobs' => $this->jobs,
        ];

        $this->queries = [];
        $this->models = [];
        $this->jobs = [];
        $this->pauseDepth = 0;

        return $data;
    }

    private function modelActionPatterns(): array
    {
        if ($this->modelActionPatterns === null) {
            $actions = (array) config('traffic-monitor.collect.model_actions', [
                'created', 'updated', 'deleted', 'restored', 'retrieved',
            ]);
            $this->modelActionPatterns = array_map(fn ($a) => "eloquent.{$a}:*", $actions);
        }

        return $this->modelActionPatterns;
    }

    private function extractAction(string $event): string
    {
        preg_match('/^eloquent\.([^:]+):/', $event, $matches);

        return $matches[1] ?? 'unknown';
    }

    private function extractJobPayload(object $command): array
    {
        $reflection = new ReflectionObject($command);
        $payload = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $payload[$property->getName()] = $this->normalizeValue($property->getValue($command));
        }

        return $payload;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return [
                '_model' => $value::class,
                'id' => $value->getKey(),
            ];
        }

        if (is_object($value)) {
            return ['_object' => $value::class];
        }

        return $value;
    }
}
