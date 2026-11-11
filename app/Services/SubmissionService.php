<?php

namespace App\Services;

use App\Enums\UnparsedSubmissionType;
use App\Exceptions\BadSubmissionException;
use App\Http\Submission\Handlers\AbstractSubmissionHandler;
use App\Http\Submission\Handlers\ActionableBuildInterface;
use App\Http\Submission\Handlers\BuildPropertiesJSONHandler;
use App\Http\Submission\Handlers\DoneHandler;
use App\Http\Submission\Handlers\UpdateHandler;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Parses submission files and reports the results.
 */
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
     * Run a callback in a transaction that holds the build's advisory lock, if the build is known.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private static function withBuildLock(?int $buildid, callable $callback): mixed
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
