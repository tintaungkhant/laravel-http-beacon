<?php

namespace HttpBeacon;

use HttpBeacon\Support\Caller;
use HttpBeacon\Support\Redactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Str;
use ReflectionObject;

class RequestCollector
{
    private array $queries = [];

    private int $queryCount = 0;

    private array $models = [];

    private array $jobs = [];

    private int $pauseDepth = 0;

    private ?array $modelActionPatterns = null;

    private ?string $requestUuid = null;

    public function getRequestUuid(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        if ($this->requestUuid === null) {
            $this->requestUuid = (string) Str::uuid();
        }

        return $this->requestUuid;
    }

    public function recordQuery(QueryExecuted $event): void
    {
        if ($this->pauseDepth > 0) {
            return;
        }

        $this->queryCount++;

        $cap = (int) config('beacon.collect.max_queries_per_request', 0);
        if ($cap > 0 && count($this->queries) >= $cap) {
            return;
        }

        $this->queries[] = [
            'sql' => $event->sql,
            'sql_with_bindings' => $this->replaceBindings($event),
            'bindings' => $event->bindings,
            'time_ms' => $event->time,
            'connection' => $event->connectionName,
            'caller' => Caller::find(),
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

        $action = $this->extractAction($event);

        $this->models[] = [
            'class' => $model::class,
            'id' => $model->getKey(),
            'action' => $action,
            'changes' => $this->extractChanges($model, $action),
            'caller' => Caller::find(),
        ];
    }

    public function recordJob(JobQueued $event): void
    {
        if ($this->pauseDepth > 0) {
            return;
        }

        $job = $event->job;
        $isObject = is_object($job);

        $this->jobs[] = [
            'class' => $isObject ? $job::class : (string) $job,
            'connection' => $event->connectionName,
            'queue' => $event->queue,
            'payload' => $isObject ? $this->extractJobPayload($job) : null,
            'caller' => Caller::find(),
        ];
    }

    private function extractChanges(Model $model, string $action): ?array
    {
        if ($action === 'retrieved') {
            return null;
        }

        $changes = $model->getChanges();

        if (empty($changes)) {
            return null;
        }

        return Redactor::parameters($changes);
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
            'query_count' => $this->queryCount,
            'models' => array_values($this->models),
            'jobs' => $this->jobs,
        ];

        $this->queries = [];
        $this->queryCount = 0;
        $this->models = [];
        $this->jobs = [];
        $this->pauseDepth = 0;
        $this->requestUuid = null;

        return $data;
    }

    private function modelActionPatterns(): array
    {
        if ($this->modelActionPatterns === null) {
            $actions = (array) config('beacon.collect.model_actions', [
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

    private function replaceBindings(QueryExecuted $event): string
    {
        $sql = $event->sql;

        if (empty($event->bindings)) {
            return $sql;
        }

        $bindings = $event->connection->prepareBindings($event->bindings);

        foreach ($bindings as $key => $binding) {
            $regex = is_numeric($key)
                ? "/\?(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/"
                : "/:{$key}(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/";

            if ($binding === null) {
                $value = 'null';
            } elseif (is_int($binding) || is_float($binding)) {
                $value = (string) $binding;
            } else {
                $value = $this->quoteString($event, (string) $binding);
            }

            $sql = preg_replace_callback(
                $regex,
                fn () => $value,
                $sql,
                is_numeric($key) ? 1 : -1
            );
        }

        return $sql;
    }

    private function quoteString(QueryExecuted $event, string $value): string
    {
        try {
            $pdo = $event->connection->getPdo();
            if ($pdo instanceof \PDO) {
                return $pdo->quote($value);
            }
        } catch (\Throwable $e) {
            // PDO unavailable — fall through to manual escape
        }

        return "'".strtr($value, ["'" => "''", '\\' => '\\\\'])."'";
    }

    private const IGNORED_JOB_PROPERTIES = [
        'connection', 'queue', 'delay', 'middleware', 'chained',
        'chainConnection', 'chainQueue', 'chainCatchCallbacks',
        'afterCommit', 'retryUntil',
        'tries', 'timeout', 'maxExceptions', 'backoff', 'failOnTimeout',
    ];

    private function extractJobPayload(object $command): array
    {
        $reflection = new ReflectionObject($command);
        $payload = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();

            if (in_array($name, self::IGNORED_JOB_PROPERTIES, true)) {
                continue;
            }

            $payload[$name] = $this->normalizeValue($property->getValue($command));
        }

        return Redactor::parameters($payload);
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
