<?php

namespace HttpBeacon\Console\Commands;

use HttpBeacon\Beacon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'beacon:resume')]
class ResumeCommand extends Command
{
    protected $signature = 'beacon:resume';

    protected $description = 'Resume Beacon recording.';

    public function handle(): int
    {
        Beacon::resume();

        $this->info('Beacon recording resumed.');

        return self::SUCCESS;
    }
}
