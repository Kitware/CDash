<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\UnparsedSubmissionType;
use App\Exceptions\BadSubmissionException;
use App\Http\Submission\Handlers\AbstractSubmissionHandler;
use App\Http\Submission\Handlers\ActionableBuildInterface;
use App\Http\Submission\Handlers\BuildPropertiesJSONHandler;
use App\Http\Submission\Handlers\DoneHandler;
use App\Http\Submission\Handlers\UpdateHandler;
use App\Models\Submission;
use App\Utils\SubmissionUtils;
use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\Email\EmailMessage;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationDirector;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Model\Build;
use CDash\Model\BuildEmail;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use CDash\Model\Repository;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class SubmissionService extends AbstractService
{
    /**
     * Parse a CTest XML file.  If the build is known, first wait for any other worker that is parsing a
     * file for the same build.
     *
     * @throws BadSubmissionException
     */
    public static function parseCTestXml(string $filename, \App\Models\Project $project, ?int $buildid): AbstractSubmissionHandler
    {
        return self::withBuildLock($buildid, function () use ($filename, $project, $buildid) {
            $filehandle = Storage::readStream($filename) ?? throw new BadSubmissionException("Failed to open file {$filename}");
            try {
                return self::parseCTestXmlStream($filehandle, $filename, $project, $buildid);
            } finally {
                fclose($filehandle);
            }
        });
    }

    /**
     * Parse an unparsed (non-XML) data file into an existing build, after waiting for any other worker
     * that is parsing a file for the same build.
     *
     * @throws BadSubmissionException
     */
    public static function parseDataFile(string $filename, int $buildid, UnparsedSubmissionType $type): AbstractSubmissionHandler
    {
        return self::withBuildLock($buildid, function () use ($filename, $buildid, $type) {
            $className = 'App\\Http\\Submission\\Handlers\\' . $type->value . 'Handler';
            if (!class_exists($className)) {
                throw new BadSubmissionException("No handler class for {$type->value}");
            }

            $build = new Build();
            $build->Id = $buildid;
            $handler = new $className($build);

            if (!Storage::exists($filename)) {
                throw new BadSubmissionException("Failed to locate file {$filename}");
            }

            if ($handler->Parse($filename) === false) {
                throw new BadSubmissionException("Failed to parse file {$filename}");
            }

            return $handler;
        });
    }

    /**
     * Set the repository status for a parsed submission.  Call this after the parse has been committed.
     */
    public static function setRepositoryStatus(AbstractSubmissionHandler $handler): void
    {
        if ($handler instanceof UpdateHandler
            || $handler instanceof BuildPropertiesJSONHandler
        ) {
            Repository::setStatus($handler->getBuild(), false);
        }
    }

    /**
     * Send emails about a parsed submission.  Call this after the parse has been committed.
     */
    public static function sendBuildEmails(AbstractSubmissionHandler $handler, \App\Models\Project $project): void
    {
        // Send emails about update problems.
        if ($handler instanceof UpdateHandler) {
            self::sendUpdateErrorEmail($handler, $project->id);
        }

        // Send more general build emails.
        if ($handler instanceof ActionableBuildInterface) {
            self::sendSubscriptionEmails($handler, $project->id);
        }
    }

    /**
     * Record a received submission file.  Returns the row's id, or null if the row couldn't be written.
     */
    public static function recordSubmission(string $id, int $projectid, ?int $buildid, string $type, string $path, ?string $filename = null): ?string
    {
        // The legacy Project::Fill() sets PHP's default timezone to the project's nightly timezone, so take the
        // time in UTC.
        $now = now('UTC');
        $submission = new Submission([
            'id' => $id,
            'projectid' => $projectid,
            'buildid' => $buildid,
            'type' => $type,
            'filename' => $filename,
            'path' => $path,
            'status' => SubmissionStatus::QUEUED,
            'received_at' => $now,
            'next_attempt_at' => $now,
        ]);

        return self::recordWithoutFailing(fn () => $submission->save()) ? $id : null;
    }

    /**
     * Update a submission's row.  Does nothing for a job that has no row.
     *
     * @param array<string,mixed> $attributes
     */
    public static function updateSubmission(?string $id, array $attributes): void
    {
        if ($id === null) {
            return;
        }

        self::recordWithoutFailing(fn () => Submission::whereKey($id)->update($attributes));
    }

    /**
     * Where a submission's file is, according to its row.  Null for a job that has no row, or if the row can't be
     * read.
     */
    public static function submissionPath(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        try {
            $path = Submission::whereKey($id)->value('path');
        } catch (Throwable $e) {
            report($e);
            return null;
        }

        return is_string($path) ? $path : null;
    }

    /**
     * Move a queued submission's row to processing, so that no other job processes its file.  Returns false
     * if the row isn't queued, e.g. because another job claimed it first.
     */
    public static function claimSubmission(string $id): bool
    {
        // In the worker this commits at once, before parsing starts.  Nested in another transaction, an update
        // that throws only rolls back its own savepoint.
        return DB::transaction(fn () => Submission::whereKey($id)
            ->where('status', SubmissionStatus::QUEUED)
            ->update([
                'status' => SubmissionStatus::PROCESSING,
                'attempts' => DB::raw('attempts + 1'),
                'started_at' => now('UTC'),
            ]) > 0);
    }

    /**
     * Mark a submission's row as done.  Call this inside the parse transaction, so that the parse and the
     * row commit together.
     */
    public static function markSubmissionDone(string $id): void
    {
        // Parsing calls the legacy Project::Fill(), which sets PHP's default timezone to the project's nightly
        // timezone, so take the time in UTC.
        Submission::whereKey($id)->update([
            'status' => SubmissionStatus::DONE,
            'error' => null,
            'finished_at' => now('UTC'),
        ]);
    }

    /**
     * Set a submission's row back to queued, for a file that will be parsed again later.  Unlike the
     * best-effort updates, this one can't be skipped: the next attempt can only claim a queued row.
     */
    public static function requeueSubmission(string $id, string $path, Carbon $next_attempt_at): void
    {
        Submission::whereKey($id)->update([
            'status' => SubmissionStatus::QUEUED,
            'path' => $path,
            'next_attempt_at' => $next_attempt_at,
        ]);
    }

    /**
     * Whether a failed job should leave a submission's row and file alone: the row is finished (done or failed),
     * or a requeue has handed the file to a later attempt.  False for a job that has no row, or if the row can't
     * be read.
     */
    public static function isSubmissionFinishedOrRequeued(?string $id): bool
    {
        if ($id === null) {
            return false;
        }

        try {
            return self::whereFinishedOrRequeued(Submission::whereKey($id))->exists();
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Mark a submission's row as failed, unless it's finished or a requeue has handed it to a later attempt.
     * Does nothing for a job that has no row.
     *
     * @param array<string,mixed> $attributes
     */
    public static function failSubmission(?string $id, array $attributes): void
    {
        if ($id === null) {
            return;
        }

        self::recordWithoutFailing(fn () => Submission::whereKey($id)
            ->whereNot(fn (Builder $query) => self::whereFinishedOrRequeued($query))
            ->update(['status' => SubmissionStatus::FAILED, ...$attributes]));
    }

    /**
     * Limit a query to rows that a failed job must leave alone: finished rows, and queued rows that were claimed
     * before, i.e. requeued for a later attempt.  A job that dies after requeueing a file has its failed() run
     * much later, possibly while the next attempt is still waiting to run, or after it failed with its own error.
     *
     * @param Builder<Submission> $query
     *
     * @return Builder<Submission>
     */
    private static function whereFinishedOrRequeued(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereIn('status', [SubmissionStatus::DONE, SubmissionStatus::FAILED])
            ->orWhere(fn (Builder $query) => $query
                ->where('status', SubmissionStatus::QUEUED)
                ->where('attempts', '>', 0)));
    }

    /** Determine the descriptive filename for a submission file. */
    public static function generateBackupFileName($projectname, $subprojectname, $buildname,
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
        if ($file !== 'Project') {
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

    /**
     * Write to the submissions table.  A failed write is reported and skipped, so recording a submission
     * never fails an upload or a job.  Returns whether the write succeeded.
     *
     * @param Closure(): mixed $callback
     */
    private static function recordWithoutFailing(Closure $callback): bool
    {
        try {
            // Nested in another transaction, this rolls back to a savepoint if the write fails, so the
            // caller's transaction can continue.  A deadlock still aborts it.
            DB::transaction($callback);
            return true;
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Run a callback in a transaction that holds the build's advisory lock, if the build is known.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public static function withBuildLock(?int $buildid, callable $callback): mixed
    {
        return DB::transaction(function () use ($buildid, $callback) {
            // Process submissions for the same build one at a time.  Concurrent workers can otherwise
            // deadlock, e.g. when one replaces a build that was marked as done while another updates it.
            // The lock is released automatically when the transaction commits or rolls back.
            if ($buildid !== null) {
                DB::select('SELECT pg_advisory_xact_lock(?)', [$buildid]);
            }

            return $callback();
        });
    }

    /**
     * Main function to parse the incoming xml from ctest
     *
     * @throws BadSubmissionException
     */
    private static function parseCTestXmlStream($filehandle, string $filename, \App\Models\Project $project_param, ?int $buildid = null): AbstractSubmissionHandler
    {
        // Figure out what type of XML file this is.
        $xml_info = SubmissionUtils::get_xml_type($filehandle, $filename);

        $handler_ref = $xml_info['xml_handler'];
        $file = $xml_info['xml_type'];

        if ($handler_ref === DoneHandler::class) {
            if ($buildid === null) {
                throw new InvalidArgumentException('Null buildid found while initializing DoneHandler');
            }

            $build = new Build();
            $build->Id = $buildid;
            $handler = new $handler_ref($build);
        } elseif ($handler_ref !== null) {
            $project = new Project();
            $project->Id = $project_param->id;
            $handler = new $handler_ref($project);
        } else {
            // TODO: Add as much context as possible to this message
            Log::error('error: could not create handler based on xml content');
            abort(400, 'Could not create handler based on xml content');
        }

        rewind($filehandle);
        $content = fread($filehandle, 8192);
        $parser = xml_parser_create();
        xml_set_element_handler($parser, [$handler, 'startElement'], [$handler, 'endElement']);
        xml_set_character_data_handler($parser, [$handler, 'text']);
        xml_parse($parser, $content, false);

        $sitename = '';
        $buildname = '';
        $subprojectname = '';
        $stamp = '';
        if ($file !== 'Project') {
            // projects don't have some of these fields.

            $sitename = $handler->getSiteName();
            $buildname = $handler->getBuildName();
            $subprojectname = $handler->getSubProjectName();
            $stamp = $handler->getBuildStamp();
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
            $project_param->name, $subprojectname, $buildname, $sitename, $stamp, $file . '.xml');
        $handler->backupFileName = $backup_filename;

        return $handler;
    }

    /** Main function to send email if necessary */
    private static function sendSubscriptionEmails(ActionableBuildInterface $handler, int $projectid): void
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
            Mail::raw($notification->getBody(), function ($message) use ($notification): void {
                $message->subject($notification->getSubject())
                    ->to($notification->getRecipient());
            });

            BuildEmail::SaveNotification($notification);
        }
    }

    /** function to send email to site maintainers when the update step fails */
    private static function sendUpdateErrorEmail(UpdateHandler $handler, int $projectid): void
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

            $buildid = (int) (DB::select('
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
        $update_errors = self::checkUpdateErrors((int) $buildid);
        if ($update_errors['errors']) {
            // Find the site maintainer(s)
            $sitename = $handler->getSiteName();

            $recipients = $handler->GetSite()->maintainers()->pluck('email');

            if ($recipients->isNotEmpty()) {
                // Generate the email to send
                $subject = 'CDash [' . $Project->Name . '] - Update Errors for ' . $sitename;

                $body = "$sitename has encountered errors during the Update step and you have been identified as the maintainer of this site.\n\n";
                $body .= "*Update Errors*\n";
                $body .= 'Status: ' . $eloquentBuild->updateStep?->status . ' (' . url('/builds/' . $buildid . '/update') . ")\n";

                Mail::raw($body, function ($message) use ($subject, $recipients): void {
                    $message->subject($subject)
                        ->to($recipients->toArray());
                });
            }
        }
    }

    /** Check for update errors for a given build. */
    private static function checkUpdateErrors(int $buildid): array
    {
        $num_errors = \App\Models\Build::findOrFail($buildid)->updateStep->errors ?? 0;

        return [
            'hasfixes' => false,
            'update_errors' => $num_errors,
            'errors' => $num_errors > 0,
        ];
    }
}
