<?php

namespace HttpBeacon\Console\Commands;

use HttpBeacon\Beacon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'beacon:pause')]
class PauseCommand extends Command
{
    protected $signature = 'beacon:pause';

    protected $description = 'Pause Beacon recording.';

    public function handle(): int
    {
        Beacon::pause();

        $this->info('Beacon recording paused.');

        return self::SUCCESS;
    }
}
