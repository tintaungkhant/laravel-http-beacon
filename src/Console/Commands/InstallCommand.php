<?php

namespace HttpBeacon\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'beacon:install')]
class InstallCommand extends Command
{
    protected $signature = 'beacon:install';

    protected $description = 'Install Beacon: publish config and run migrations.';

    public function handle(): int
    {
        $this->comment('Publishing Beacon configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'beacon-config']);

        $this->comment('Publishing Beacon migrations...');
        $this->callSilent('vendor:publish', ['--tag' => 'beacon-migrations']);

        $this->comment('Publishing Beacon assets...');
        $this->callSilent('vendor:publish', ['--tag' => 'beacon-assets']);

        $this->comment('Running migrations...');
        $this->call('migrate');

        $this->info('Beacon installed successfully.');

        return self::SUCCESS;
    }
}
