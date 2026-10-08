<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSubmission;
use App\Services\AuthTokenService;
use App\Utils\SubmissionUtils;
use CDash\Model\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class QueueSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'submission:queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue submitted files in the inbox directory';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Queue the "build metadata" JSON files first, so they have a chance
        // to get parsed before the subsequent payload files.
        foreach (Storage::files('inbox') as $inboxFile) {
            if (!SubmissionUtils::isBuildMetadataFilename($inboxFile)) {
                continue;
            }
            $this->queueFile($inboxFile);
        }

        // Iterate over our inbox files again, queueing them for parsing.
        foreach (Storage::files('inbox') as $inboxFile) {
            if (SubmissionUtils::isBuildMetadataFilename($inboxFile)) {
                continue;
            }
            $this->queueFile($inboxFile);
        }
    }

    private function queueFile(string $inboxFile): void
    {
        $filename = str_replace('inbox/', '', $inboxFile);
        $parsed = SubmissionUtils::parseFilename($filename);
        if ($parsed === null) {
            Storage::move("inbox/{$filename}", "failed/{$filename}");
            echo "Could not parse $filename\n";
            return;
        }

        $project = new Project();
        $project->FindByName($parsed['projectname']);
        if (!$project->Id) {
            Storage::move("inbox/{$filename}", "failed/{$filename}");
            echo "Could not find project {$parsed['projectname']}\n";
            return;
        }

        if ($project->AuthenticateSubmissions && !AuthTokenService::check($parsed['token_hash'], $project->Id)) {
            Storage::move("inbox/{$filename}", "failed/{$filename}");
            echo "Invalid authentication token for $filename\n";
            return;
        }

        ProcessSubmission::dispatch($filename, $project->Id, null, $parsed['md5']);
    }
}
