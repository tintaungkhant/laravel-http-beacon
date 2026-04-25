<?php

namespace HttpBeacon\Console\Commands;

use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'beacon:clear')]
class ClearCommand extends Command
{
    protected $signature = 'beacon:clear';

    protected $description = 'Delete all Beacon traffic logs.';

    public function handle(): int
    {
        IncomingRequest::query()->delete();
        OutgoingRequest::query()->delete();

        $this->info('Beacon entries cleared.');

        return self::SUCCESS;
    }
}
