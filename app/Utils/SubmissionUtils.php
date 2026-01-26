<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\BadSubmissionException;
use App\Http\Submission\Handlers\BuildHandler;
use App\Http\Submission\Handlers\ConfigureHandler;
use App\Http\Submission\Handlers\CoverageHandler;
use App\Http\Submission\Handlers\CoverageJUnitHandler;
use App\Http\Submission\Handlers\CoverageLogHandler;
use App\Http\Submission\Handlers\DoneHandler;
use App\Http\Submission\Handlers\DynamicAnalysisHandler;
use App\Http\Submission\Handlers\NoteHandler;
use App\Http\Submission\Handlers\ProjectHandler;
use App\Http\Submission\Handlers\TestingHandler;
use App\Http\Submission\Handlers\TestingJUnitHandler;
use App\Http\Submission\Handlers\UpdateHandler;
use App\Http\Submission\Handlers\UploadHandler;
use App\Models\RichBuildAlert;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use Illuminate\Support\Facades\DB;

class SubmissionUtils
{
    /**
     * Figure out what type of XML file this is
     *
     * @return array{
     *     file_handle: mixed,
     *     xml_handler: 'App\Http\Submission\Handlers\BuildHandler'|'App\Http\Submission\Handlers\ConfigureHandler'|'App\Http\Submission\Handlers\CoverageHandler'|'App\Http\Submission\Handlers\CoverageJUnitHandler'|'App\Http\Submission\Handlers\CoverageLogHandler'|'App\Http\Submission\Handlers\DoneHandler'|'App\Http\Submission\Handlers\DynamicAnalysisHandler'|'App\Http\Submission\Handlers\NoteHandler'|'App\Http\Submission\Handlers\ProjectHandler'|'App\Http\Submission\Handlers\TestingHandler'|'App\Http\Submission\Handlers\TestingJUnitHandler'|'App\Http\Submission\Handlers\UpdateHandler'|'App\Http\Submission\Handlers\UploadHandler',
     *     xml_type: 'Build'|'Configure'|'Coverage'|'CoverageJUnit'|'CoverageLog'|'Done'|'DynamicAnalysis'|'Notes'|'Project'|'Test'|'TestJUnit'|'Update'|'Upload',
     * }
     *
     * @throws BadSubmissionException
     */
    public static function get_xml_type(mixed $filehandle, string $xml_file): array
    {
        $file = '';
        $handler = null;
        // read file contents until we recognize its elements
        while ($file === '' && !feof($filehandle)) {
            $content = fread($filehandle, 8192);
            if ($content === false) {
                // if read failed, fallback onto default null values
                break;
            }
            if (str_contains($content, '<Update')) {
                // Should be first otherwise confused with Build
                $handler = UpdateHandler::class;
                $file = 'Update';
            } elseif (str_contains($content, '<Build')) {
                $handler = BuildHandler::class;
                $file = 'Build';
            } elseif (str_contains($content, '<Configure')) {
                $handler = ConfigureHandler::class;
                $file = 'Configure';
            } elseif (str_contains($content, '<Testing')) {
                $handler = TestingHandler::class;
                $file = 'Test';
            } elseif (str_contains($content, '<CoverageLog')) {
                // Should be before coverage
                $handler = CoverageLogHandler::class;
                $file = 'CoverageLog';
            } elseif (str_contains($content, '<Coverage')) {
                $handler = CoverageHandler::class;
                $file = 'Coverage';
            } elseif (str_contains($content, '<report')) {
                $handler = CoverageJUnitHandler::class;
                $file = 'CoverageJUnit';
            } elseif (str_contains($content, '<Notes')) {
                $handler = NoteHandler::class;
                $file = 'Notes';
            } elseif (str_contains($content, '<DynamicAnalysis')) {
                $handler = DynamicAnalysisHandler::class;
                $file = 'DynamicAnalysis';
            } elseif (str_contains($content, '<Project')) {
                $handler = ProjectHandler::class;
                $file = 'Project';
            } elseif (str_contains($content, '<Upload')) {
                $handler = UploadHandler::class;
                $file = 'Upload';
            } elseif (str_contains($content, '<testsuite')) {
                $handler = TestingJUnitHandler::class;
                $file = 'TestJUnit';
            } elseif (str_contains($content, '<Done')) {
                $handler = DoneHandler::class;
                $file = 'Done';
            }
        }

        // restore the file descriptor to beginning of file
        rewind($filehandle);

        // perform minimal error checking as a sanity check
        if ($file === '' || $handler === null) {
            throw new BadSubmissionException("Could not determine submission file type for: '{$xml_file}'");
        }

        return [
            'file_handle' => $filehandle,
            'xml_handler' => $handler,
            'xml_type' => $file,
        ];
    }

    /** Add a new build */
    public static function add_build(Build $build)
    {
        if (!is_numeric($build->ProjectId) || !is_numeric($build->SiteId)) {
            return;
        }

        $buildid = $build->GetIdFromName($build->SubProjectName);
        if ($buildid > 0 && !$build->Append) {
            $build->Id = $buildid;
            if ($build->GetDone()) {
                DatabaseCleanupUtils::removeBuild($buildid);
                $build->Id = null;
            }
        }

        // Move this into a Build::SetAppend($append, $buildid) method:
        //
        if (!$build->Exists() && $build->Append && empty($build->Id)) {
            $build->Id = $buildid;
        }

        $build->Save();

        // If the build is part of a subproject we link the update file
        if (isset($build->SubProjectName) && $build->SubProjectName != '') {
            $BuildUpdate = new BuildUpdate();
            $BuildUpdate->BuildId = $build->Id;
            $BuildUpdate->AssociateBuild((int) $build->SiteId, $build->Name, $build->GetStamp());
        }

        return $build->Id;
    }

    /** Extract the date from the build stamp */
    public static function extract_date_from_buildstamp($buildstamp): string
    {
        return substr($buildstamp, 0, strpos($buildstamp, '-', strpos($buildstamp, '-') + 1));
    }

    /** Add the difference between the numbers of errors and warnings
     *  for the previous and current build
     *
     * TODO: Find a better home for this
     */
    public static function compute_error_difference($buildid, $previousbuildid, $warning): void
    {
        $pdo = Database::getInstance()->getPdo();

        $build = \App\Models\Build::findOrFail((int) $buildid);
        $previous_build = \App\Models\Build::findOrFail((int) $previousbuildid);

        // Look at the difference positive and negative test errors
        DB::update('
            UPDATE builderror
            SET newstatus=1
            WHERE
                buildid = :buildid
                AND type = :type
                AND NOT EXISTS(
                    SELECT *
                    FROM builderror builderror_previous
                    WHERE
                        builderror_previous.buildid = :previousbuildid
                        AND builderror_previous.type = builderror.type
                        AND builderror_previous.stderror = builderror.stderror
                        AND builderror_previous.sourcefile = builderror.sourcefile
                        AND builderror_previous.sourceline = builderror.sourceline
                )
        ', [
            'buildid' => $buildid,
            'type' => $warning,
            'previousbuildid' => $previousbuildid,
        ]);

        // Recurring buildfailures are represented by the buildfailuredetails table.
        // Get a list of buildfailuredetails IDs for the current build and the
        // previous build.

        $current_failures = $build->richAlerts()
            ->whereRelation('details', 'type', $warning)
            ->pluck('detailsid')->toArray();

        $previous_failures = $previous_build->richAlerts()
            ->whereRelation('details', 'type', $warning)
            ->pluck('detailsid')->toArray();

        // Check if any of these are new failures and mark them accordingly.
        foreach ($current_failures as $failure) {
            if (!in_array($failure, $previous_failures)) {
                RichBuildAlert::where([
                    'buildid' => $buildid,
                    'detailsid' => $failure,
                ])->update([
                    'newstatus' => 1,
                ]);
            }
        }

        // Maybe we can get that from the query (don't know).
        $npositives = $build->basicAlerts()
            ->where('type', $warning)
            ->where('newstatus', 1)
            ->count();

        $npositives += $build->richAlerts()
            ->where('newstatus', 1)
            ->whereRelation('details', 'type', $warning)
            ->count();

        // Count how many build defects were fixed since the previous build.
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM builderror builderror_previous
            WHERE
                builderror_previous.buildid = :previousbuildid
                AND builderror_previous.type = :type
                AND NOT EXISTS(
                    SELECT *
                    FROM builderror
                    WHERE
                        builderror.buildid = :buildid
                        AND builderror_previous.type = builderror.type
                        AND builderror_previous.stderror = builderror.stderror
                        AND builderror_previous.sourcefile = builderror.sourcefile
                        AND builderror_previous.sourceline = builderror.sourceline
                )
        ');
        $stmt->bindValue(':buildid', $buildid);
        $stmt->bindValue(':previousbuildid', $previousbuildid);
        $stmt->bindValue(':type', $warning);
        pdo_execute($stmt);
        $negatives_array = $stmt->fetch();
        $nnegatives = $negatives_array[0];

        foreach ($previous_failures as $failure) {
            if (!in_array($failure, $current_failures)) {
                $nnegatives++;
            }
        }

        // Check if a diff already exists for this build.
        DB::beginTransaction();
        $query = DB::select('
            SELECT *
            FROM builderrordiff
            WHERE
                buildid=:buildid
                AND type=:type
            FOR UPDATE
        ', [
            'buildid' => $buildid,
            'type' => $warning,
        ]);
        $existing_npositives = 0;
        $existing_nnegatives = 0;
        if ($query !== []) {
            $existing_npositives = $query[0]->difference_positive;
            $existing_nnegatives = $query[0]->difference_negative;
        }

        // Only log if there's a diff since last build or an existing diff record.
        if ($npositives == 0 && $nnegatives == 0 && $existing_npositives == 0 && $existing_nnegatives == 0) {
            DB::commit();
            return;
        }

        if ($query !== []) {
            // Update existing record.
            DB::update('
                UPDATE builderrordiff
                SET
                    difference_positive=:npositives,
                    difference_negative=:nnegatives
                WHERE
                    buildid=:buildid
                  AND type=:type
            ', [
                'npositives' => $npositives,
                'nnegatives' => $nnegatives,
                'buildid' => $buildid,
                'type' => $warning,
            ]);
        } else {
            // Insert new record.
            DB::insert('
                INSERT INTO builderrordiff (buildid, type, difference_positive, difference_negative)
                VALUES (:buildid, :type, :npositives, :nnegatives)
            ', [
                'npositives' => $npositives,
                'nnegatives' => $nnegatives,
                'buildid' => $buildid,
                'type' => $warning,
            ]);
        }
        DB::commit();
    }

    /** Return the hash of an open file handle */
    public static function hashFileHandle(mixed $filehandle, string $algo): string
    {
        $hashContext = hash_init($algo);
        hash_update_stream($hashContext, $filehandle);
        rewind($filehandle);
        return hash_final($hashContext);
    }
}
