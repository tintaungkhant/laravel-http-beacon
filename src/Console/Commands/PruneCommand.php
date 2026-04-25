<?php

namespace HttpBeacon\Console\Commands;

use Carbon\CarbonInterface;
use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'beacon:prune
        {--hours= : Delete entries older than this many hours (overrides config)}
        {--dry-run : Count what would be deleted without deleting}';

    protected $description = 'Prune old beacon traffic logs.';

    public function handle(): int
    {
        $hours = $this->option('hours') !== null
            ? (int) $this->option('hours')
            : (int) config('beacon.retention.hours', 168);

        $chunkSize = (int) config('beacon.retention.chunk_size', 1000);
        $cutoff = now()->subHours($hours);
        $dryRun = (bool) $this->option('dry-run');

        $this->line(sprintf(
            '%s entries older than %s (%dh)…',
            $dryRun ? 'Counting' : 'Pruning',
            $cutoff->toIso8601String(),
            $hours,
        ));

        if ($dryRun) {
            $incoming = IncomingRequest::where('created_at', '<', $cutoff)->count();
            $outgoing = OutgoingRequest::where('created_at', '<', $cutoff)->count();
            $this->info("Would prune {$incoming} incoming and {$outgoing} outgoing entries.");

            return self::SUCCESS;
        }

        $incoming = $this->chunkDelete(IncomingRequest::class, $cutoff, $chunkSize);
        $outgoing = $this->chunkDelete(OutgoingRequest::class, $cutoff, $chunkSize);

        $this->info("Pruned {$incoming} incoming and {$outgoing} outgoing entries.");

        return self::SUCCESS;
    }

    private function chunkDelete(string $model, CarbonInterface $cutoff, int $chunkSize): int
    {
        $total = 0;

        do {
            $deleted = $model::where('created_at', '<', $cutoff)
                ->limit($chunkSize)
                ->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
