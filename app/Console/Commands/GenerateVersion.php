<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class GenerateVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use `git describe` to compute the version if git is installed.';

    public function handle(): int
    {
        // Use --tags to capture all tags, including lightweight tags.
        $describe_result = Process::run('git describe --tags');

        $version = $describe_result->successful() ? $describe_result->output() : 'v' . config('cdash.version');

        $result = file_put_contents(public_path('VERSION'), $version);

        return $result === false ? self::FAILURE : self::SUCCESS;
    }
}
