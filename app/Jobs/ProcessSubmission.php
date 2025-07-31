<?php

namespace App\Jobs;

use App\Exceptions\BadSubmissionException;
use App\Http\Submission\Handlers\AbstractSubmissionHandler;
use App\Http\Submission\Handlers\ActionableBuildInterface;
use App\Http\Submission\Handlers\BuildPropertiesJSONHandler;
use App\Http\Submission\Handlers\DoneHandler;
use App\Http\Submission\Handlers\RetryHandler;
use App\Http\Submission\Handlers\UpdateHandler;
use App\Models\BuildFile;
use App\Models\SuccessfulJob;
use App\Utils\SubmissionUtils;
use App\Utils\UnparsedSubmissionProcessor;
use CDash\Database;
use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\Email\EmailMessage;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationDirector;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Model\Build;
use CDash\Model\BuildEmail;
use CDash\Model\BuildGroup;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use CDash\Model\Repository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessSubmission implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public $filename;
    public string $localFilename = '';
    public $projectid;
    public $buildid;
    public $expected_md5;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($filename, $projectid, $buildid, $expected_md5)
    {
        $this->timeout = config('cdash.queue_timeout');

        $context = [];
        if (isset($projectid)) {
            $context['projectid'] = $projectid;
        }
        if (isset($buildid)) {
            $context['buildid'] = $buildid;
        }
        Log::shareContext($context);

        $this->filename = $filename;
        $this->projectid = $projectid;
        $this->buildid = $buildid;
        $this->expected_md5 = $expected_md5;
    }

    private function renameSubmissionFile($src, $dst): bool
    {
        if (config('cdash.remote_workers')) {
            /** @var string $app_key */
            $app_key = config('app.key', '');
            return Http::withToken($app_key)->delete(url('/api/internal/deleteSubmissionFile'), [
                'filename' => $src,
                'dest' => $dst,
            ])->ok();
        } else {
            return Storage::move($src, $dst);
        }
    }

    private function deleteSubmissionFile($filename): bool
    {
        if (config('cdash.remote_workers')) {
            /** @var string $app_key */
            $app_key = config('app.key', '');
            return Http::withToken($app_key)->delete(url('/api/internal/deleteSubmissionFile'), [
                'filename' => $filename,
            ])->ok();
        } else {
            return Storage::delete($filename);
        }
    }

    private function requeueSubmissionFile($buildid): bool
    {
        if (config('cdash.remote_workers')) {
            /** @var string $app_key */
            $app_key = config('app.key', '');
            $response = Http::withToken($app_key)->post(url('/api/internal/requeueSubmissionFile'), [
                'filename' => $this->filename,
                'buildid' => $buildid,
                'projectid' => $this->projectid,
                'md5' => $this->expected_md5,
            ]);
            if ($this->localFilename !== '') {
                unlink($this->localFilename);
                $this->localFilename = '';
            }
            return $response->ok();
        } else {
            // Increment retry count.
            $retry_handler = new RetryHandler("inprogress/{$this->filename}");
            $retry_handler->increment();

            // Move file back to inbox.
            Storage::move("inprogress/{$this->filename}", "inbox/{$this->filename}");

            // Requeue the file with exponential backoff.
            PendingSubmissions::IncrementForBuildId($this->buildid);
            $delay = ((int) config('cdash.retry_base')) ** $retry_handler->Retries;
            if (config('queue.default') === 'sqs-fifo') {
                // Special handling for sqs-fifo, which does not support per-message delays.
                sleep(10);
                self::dispatch($this->filename, $this->projectid, $buildid, $this->expected_md5);
            } else {
                self::dispatch($this->filename, $this->projectid, $buildid, $this->expected_md5)->delay(now()->addSeconds($delay));
            }

            return true;
        }
    }

    /**
     * Execute the job.
     *
     * @throws BadSubmissionException
     */
    public function handle(): void
    {
        // Move file from inbox to inprogress.
        if (!$this->renameSubmissionFile("inbox/{$this->filename}", "inprogress/{$this->filename}")) {
            // Return early if the rename operation fails.
            // Presumably this means some other runner picked up the job before us.
            return;
        }

        // Parse file.
        $handler = $this->doSubmit("inprogress/{$this->filename}", $this->projectid, $this->buildid, $this->expected_md5, true);

        if (!is_object($handler)) {
            return;
        }

        // Resubmit the file if necessary.
        if (is_a($handler, DoneHandler::class) && $handler->shouldRequeue()) {
            $this->requeueSubmissionFile($handler->getBuild()->Id);
            return;
        }

        if ((int) config('cdash.backup_timeframe') === 0) {
            // We are configured not to store parsed files. Delete it now.
            $this->deleteSubmissionFile("inprogress/{$this->filename}");
        } else {
            // Move the file to a pretty name in the parsed directory.
            $this->renameSubmissionFile("inprogress/{$this->filename}", "parsed/{$handler->backupFileName}");
        }

        if ((bool) config('cdash.remote_workers') && $this->localFilename !== '') {
            unlink($this->localFilename);
            $this->localFilename = '';
        }

        unset($handler);
        $handler = null;

        // Store record for successful job if asynchronously parsing.
        if (config('queue.default') !== 'sync') {
            SuccessfulJob::create([
                'filename' => $this->filename,
            ]);
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning("Failed to process {$this->filename} with message: {$exception}");
        $this->renameSubmissionFile("inprogress/{$this->filename}", "failed/{$this->filename}");

        if ((bool) config('cdash.remote_workers') && $this->localFilename !== '') {
            unlink($this->localFilename);
            $this->localFilename = '';
        }
    }

    /**
     * This method could be running on a worker that is either remote or local, so it accepts
     * a file handle or a filename that it can query the CDash API for.
     *
     * @throws BadSubmissionException
     **/
    private function doSubmit($filename, $projectid, $buildid = null, $expected_md5 = ''): AbstractSubmissionHandler|UnparsedSubmissionProcessor|false
    {
        $filehandle = $this->getSubmissionFileHandle($filename);
        if ($filehandle === false) {
            return false;
        }

        // Special handling for "build metadata" files created while the DB was down.
        if (str_contains($filename, '_-_build-metadata_-_') && str_contains($filename, '.json')) {
            $handler = new UnparsedSubmissionProcessor();
            $handler->backupFileName = $this->filename;
            $handler->deserializeBuildMetadata($filehandle);
            fclose($filehandle);
            $handler->initializeBuild();
            $handler->populateBuildFileRow();
            return $handler;
        }

        // Special handling for unparsed (non-XML) submissions.
        $handler = self::parse_put_submission($filename, $projectid, $expected_md5, $buildid);
        if ($handler === false) {
            // Otherwise, parse this submission as CTest XML.
            $handler = self::ctest_parse($filehandle, $filename, $projectid, $expected_md5, $buildid);
        }

        fclose($filehandle);
        unset($filehandle);

        // this is the md5 checksum fail case
        if ($handler == false) {
            // no need to log an error since ctest_parse already did
            return false;
        }

        $build = $handler->getBuild();
        $pendingSubmissions = new PendingSubmissions();
        $pendingSubmissions->Build = $build;
        if ($pendingSubmissions->Exists()) {
            $pendingSubmissions->Decrement();
        }

        // Set status on repository.
        if ($handler instanceof UpdateHandler
            || $handler instanceof BuildPropertiesJSONHandler
        ) {
            Repository::setStatus($build, false);
        }

        // Send emails about update problems.
        if ($handler instanceof UpdateHandler) {
            self::send_update_email($handler, intval($projectid));
        }

        // Send more general build emails.
        if ($handler instanceof ActionableBuildInterface) {
            self::sendemail($handler, intval($projectid));
        }

        return $handler;
    }

    /**
     * Given a filename, query the CDash API for its contents and return
     * a read-only file handle.
     * This is used by workers running on other machines that need access to build xml.
     **/
    private function getRemoteSubmissionFileHandle($filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $_t = tempnam(Storage::path('inbox'), 'cdash-submission-');
        $this->localFilename = "{$_t}.{$ext}";
        rename($_t, $this->localFilename);

        /** @var string $app_key */
        $app_key = config('app.key', '');
        $response = Http::withToken($app_key)->get(url('/api/internal/getSubmissionFile'), [
            'filename' => $filename,
        ]);

        if ($response->status() === 200) {
            // TODO: It's probably possible to use a streaming approach for this instead.
            // The file could be read directly from the stream without needing to explicitly save it somewhere.
            if (!Storage::put('inbox/' . basename($this->localFilename), $response->body())) {
                Log::warning('Failed to write file to inbox.');
                return false;
            }

            return fopen($this->localFilename, 'r');
        } else {
            // Log the status code and requested filename.
            // (404 status means it's already been processed).
            Log::warning('Failed to retrieve a file handle from filename ' . $filename . '(' . $response->status() . ')');
            return false;
        }
    }

    private function getSubmissionFileHandle($filename)
    {
        if ((bool) config('cdash.remote_workers') && is_string($filename)) {
            return $this->getRemoteSubmissionFileHandle($filename);
        } elseif (Storage::exists($filename)) {
            return Storage::readStream($filename);
        } else {
            Log::error('Failed to get a file handle for submission (was type ' . gettype($filename) . ')');
            return false;
        }
    }

    /** Determine the descriptive filename for a submission file. */
    private static function generateBackupFileName($projectname, $subprojectname, $buildname,
        $sitename, $stamp, $fileNameWithExt): string
    {
        // Generate a timestamp to include in the filename.
        $currenttimestamp = microtime(true) * 100;

        // Escape the sitename, buildname, and projectname.
        $sitename_escaped = preg_replace('/[^\w\-~_]+/u', '-', $sitename);
        $buildname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $buildname);
        $projectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $projectname);
        $subprojectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $subprojectname ?? '');

        // Separate the extension from the filename.
        $ext = '.' . pathinfo($fileNameWithExt, PATHINFO_EXTENSION);
        $file = pathinfo($fileNameWithExt, PATHINFO_FILENAME);

        $filename = $projectname_escaped . '_';
        if ($file != 'Project') {
            // Project.xml files aren't associated with a particular build, so we
            // only record the site and buildname for other types of submissions.
            $filename .= $subprojectname_escaped . '_' . $sitename_escaped . '_' . $buildname_escaped . '_' . $stamp . '_';
        }
        $filename .= $currenttimestamp . '_' . $file . $ext;

        // Make sure we don't generate a filename that's too long, otherwise
        // fopen() will fail later.
        $maxChars = 250;
        $textLength = strlen($filename);
        if ($textLength > $maxChars) {
            $filename = substr_replace($filename, '', $maxChars / 2, $textLength - $maxChars);
        }

        return $filename;
    }

    /** Function to handle new style submissions via HTTP PUT */
    private static function parse_put_submission(string $filename, int $projectid, ?string $expected_md5, ?int $buildid): AbstractSubmissionHandler|false
    {
        $db = Database::getInstance();

        if ($expected_md5 === null) {
            return false;
        }

        if ($buildid === null) {
            $buildfile = BuildFile::where(['md5' => $expected_md5])->first();
        } else {
            $buildfile = BuildFile::where(['buildid' => $buildid, 'md5' => $expected_md5])->first();
        }
        if ($buildfile === null) {
            return false;
        }

        // Save a backup file for this submission.
        $row = $db->executePreparedSingleRow('SELECT name FROM project WHERE id=? LIMIT 1', [$projectid]);
        if (empty($row)) {
            return false;
        }
        $projectname = $row['name'];

        $row = $db->executePreparedSingleRow('SELECT name, stamp FROM build WHERE id=? LIMIT 1', [$buildfile->buildid]);
        if (empty($row)) {
            return false;
        }
        $buildname = $row['name'];
        $stamp = $row['stamp'];

        $row = $db->executePreparedSingleRow('SELECT name FROM site WHERE id=
                                             (SELECT siteid FROM build WHERE id=?) LIMIT 1', [$buildfile->buildid]);
        if (empty($row)) {
            return false;
        }
        $sitename = $row['name'];

        // Include the handler file for this type of submission.
        $valid_types = [
            'BazelJSON',
            'BuildPropertiesJSON',
            'GcovTar',
            'JavaJSONTar',
            'JSCoverTar',
            'OpenCoverTar',
            'SubProjectDirectories',
        ];
        if (!in_array($buildfile->type, $valid_types, true)) {
            Log::error("Project: $projectid.  No handler include file for {$buildfile->type}");
            $buildfile->delete();
            return false;
        }

        // Instantiate the handler.
        $className = 'App\\Http\\Submission\\Handlers\\' . $buildfile->type . 'Handler';
        if (!class_exists($className)) {
            Log::error("Project: $projectid.  No handler class for {$buildfile->type}");
            $buildfile->delete();
            return false;
        }

        $build = new Build();
        $build->Id = $buildfile->buildid;
        $handler = new $className($build);

        // Make sure the file exists.
        if (!Storage::exists($filename)) {
            Log::error("Failed to locate file {$filename}");
            return false;
        }

        // Parse the file.
        if ($handler->Parse($filename) === false) {
            Log::error("Failed to parse file {$filename}");
            return false;
        }

        $buildfile->delete();

        $handler->backupFileName = self::generateBackupFileName($projectname, '', $buildname, $sitename, $stamp, $buildfile->filename);

        return $handler;
    }

    /**
     * Main function to parse the incoming xml from ctest
     *
     * @throws BadSubmissionException
     */
    private static function ctest_parse($filehandle, string $filename, $projectid, $expected_md5 = '', ?int $buildid = null): AbstractSubmissionHandler|false
    {
        // Try to get the IP of the build.
        $ip = null;
        if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $Project = new Project();
        $Project->Id = $projectid;
        // Figure out what type of XML file this is.
        $xml_info = SubmissionUtils::get_xml_type($filehandle, $filename);

        $handler_ref = $xml_info['xml_handler'];
        $file = $xml_info['xml_type'];
        $handler = isset($handler_ref) ? new $handler_ref($Project) : null;

        rewind($filehandle);
        $content = fread($filehandle, 8192);

        if ($handler == null) {
            // TODO: Add as much context as possible to this message
            Log::error('error: could not create handler based on xml content');
            abort(400, 'Could not create handler based on xml content');
        }

        $parser = xml_parser_create();
        xml_set_element_handler($parser, [$handler, 'startElement'], [$handler, 'endElement']);
        xml_set_character_data_handler($parser, [$handler, 'text']);
        xml_parse($parser, $content, false);

        $projectname = get_project_name($projectid);

        $sitename = '';
        $buildname = '';
        $subprojectname = '';
        $stamp = '';
        if ($file != 'Project') {
            // projects don't have some of these fields.

            $sitename = $handler->getSiteName();
            $buildname = $handler->getBuildName();
            $subprojectname = $handler->getSubProjectName();
            $stamp = $handler->getBuildStamp();
        }

        // Check if the build is in the block list
        $db = Database::getInstance();
        $rows = $db->executePrepared("SELECT id FROM blockbuild WHERE projectid=?
                                      AND (buildname='' OR buildname=?)
                                      AND (sitename='' OR sitename=?)
                                      AND (ipaddress='' OR ipaddress=?)",
            [$projectid, $buildname, $sitename, $ip]);
        if (!empty($rows)) {
            echo 'The submission is banned from this CDash server.';
            Log::info('Blocked prohibited submission.', [
                'projectid' => $projectid,
                'build' => $buildname,
                'site' => $sitename,
                'ip' => $ip,
            ]);
            return false;
        }

        while (!feof($filehandle)) {
            $content = fread($filehandle, 8192);
            xml_parse($parser, $content, false);
        }
        xml_parse($parser, '', true);
        xml_parser_free($parser);
        unset($parser);

        // Generate a pretty, "relative to storage" filepath and store it in the handler.
        $backup_filename = self::generateBackupFileName(
            $projectname, $subprojectname, $buildname, $sitename, $stamp, $file . '.xml');
        $handler->backupFileName = $backup_filename;

        return $handler;
    }

    /** Main function to send email if necessary */
    private static function sendemail(ActionableBuildInterface $handler, int $projectid): void
    {
        $Project = new Project();
        $Project->Id = $projectid;
        $Project->Fill();

        // If we shouldn't send any emails we stop
        if ($Project->EmailBrokenSubmission == 0) {
            return;
        }

        $buildGroup = $handler->GetBuildGroup();
        if ($buildGroup->GetSummaryEmail() == 2) {
            return;
        }

        $subscriptions = new SubscriptionCollection();

        foreach ($handler->GetSubscriptionBuilderCollection() as $builder) {
            $builder->build($subscriptions);
        }

        // TODO: remove NotificationCollection then pass subscriptions to constructor
        $builder = new EmailBuilder(new NotificationCollection());
        $builder->setSubscriptions($subscriptions);

        $director = new NotificationDirector();
        $notifications = $director->build($builder);

        /**
         * @var EmailMessage $notification
         */
        foreach ($notifications as $notification) {
            Mail::raw($notification->getBody(), function ($message) use ($notification) {
                $message->subject($notification->getSubject())
                    ->to($notification->getRecipient());
            });

            BuildEmail::SaveNotification($notification);
        }
    }

    /** function to send email to site maintainers when the update step fails */
    private static function send_update_email(UpdateHandler $handler, int $projectid): void
    {
        $Project = new Project();
        $Project->Id = $projectid;
        $Project->Fill();

        // If we shouldn't sent any emails we stop
        if ($Project->EmailBrokenSubmission == 0) {
            return;
        }

        // If the handler has a buildid (it should), we use it
        if (isset($handler->BuildId) && $handler->BuildId > 0) {
            $buildid = $handler->BuildId;
        } else {
            // Get the build id
            $name = $handler->getBuildName();
            $stamp = $handler->getBuildStamp();
            $sitename = $handler->getSiteName();

            $buildid = intval(DB::select('
            SELECT build.id AS id
            FROM build, site
            WHERE
                build.name=?
                AND build.stamp=?
                AND build.projectid=?
                AND build.siteid=site.id
                AND site.name=?
            ORDER BY build.id DESC
        ', [$name, $stamp, $projectid, $sitename])[0]->id ?? -1);
        }

        if ($buildid < 0) {
            return;
        }

        //  Check if the group as no email
        $Build = new Build();
        $Build->Id = $buildid;
        $eloquentBuild = \App\Models\Build::findOrFail((int) $buildid);

        $BuildGroup = new BuildGroup();
        $BuildGroup->SetId($Build->GetGroup());

        // If we specified no email we stop here
        if ($BuildGroup->GetSummaryEmail() == 2) {
            return;
        }

        // Send out update errors to site maintainers
        $update_errors = self::check_email_update_errors(intval($buildid));
        if ($update_errors['errors']) {
            // Find the site maintainer(s)
            $sitename = $handler->getSiteName();
            $siteid = $handler->GetSite()->id;
            $recipients = [];

            $db = Database::getInstance();
            $email_addresses = $db->executePrepared('
                               SELECT email
                               FROM users, site2user
                               WHERE
                                   users.id=site2user.userid
                                   AND site2user.siteid=?
                           ', [$siteid]);
            foreach ($email_addresses as $email_addresses_array) {
                $recipients[] = $email_addresses_array['email'];
            }

            if (!empty($recipients)) {
                // Generate the email to send
                $subject = 'CDash [' . $Project->Name . '] - Update Errors for ' . $sitename;

                $body = "$sitename has encountered errors during the Update step and you have been identified as the maintainer of this site.\n\n";
                $body .= "*Update Errors*\n";
                $body .= 'Status: ' . $eloquentBuild->updates()->firstOrFail()->status . ' (' . url('/build/' . $buildid . '/update') . ")\n";

                Mail::raw($body, function ($message) use ($subject, $recipients) {
                    $message->subject($subject)
                        ->to($recipients);
                });
            }
        }
    }

    /** Check for update errors for a given build. */
    private static function check_email_update_errors(int $buildid): array
    {
        $errors = [];
        $errors['errors'] = true;
        $errors['hasfixes'] = false;

        // Update errors
        $errors['update_errors'] = \App\Models\Build::findOrFail($buildid)->updates()->first()->errors ?? 0;

        // Green build we return
        if ($errors['update_errors'] == 0) {
            $errors['errors'] = false;
        }
        return $errors;
    }
}
