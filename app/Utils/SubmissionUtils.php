<?php

declare(strict_types=1);

namespace App\Utils;

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use DOMDocument;
use App\Exceptions\CDashXMLValidationException;

class SubmissionUtils
{
    /**
     * Figure out what type of XML file this is
     * @return array<string,mixed>
     * @throws CDashXMLValidationException
     */
    public static function get_xml_type(mixed $filehandle, string $xml_file): array
    {
        $file = '';
        $handler = null;
        $schemas_dir = base_path()."/app/Validators/Schemas";
        $schema_file = null;
        // read file contents until we recognize its elements
        while ($file === '' && !feof($filehandle)) {
            $content = fread($filehandle, 8192);
            if ($content === false) {
                // if read failed, fallback onto default null values
                break;
            }
            if (str_contains($content, '<Update')) {
                // Should be first otherwise confused with Build
                $handler = \UpdateHandler::class;
                $file = 'Update';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<Build')) {
                $handler = \BuildHandler::class;
                $file = 'Build';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<Configure')) {
                $handler = \ConfigureHandler::class;
                $file = 'Configure';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<Testing')) {
                $handler = \TestingHandler::class;
                $file = 'Test';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<CoverageLog')) {
                // Should be before coverage
                $handler = \CoverageLogHandler::class;
                $file = 'CoverageLog';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<Coverage')) {
                $handler = \CoverageHandler::class;
                $file = 'Coverage';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<report')) {
                $handler = \CoverageJUnitHandler::class;
                $file = 'CoverageJUnit';
            } elseif (str_contains($content, '<Notes')) {
                $handler = \NoteHandler::class;
                $file = 'Notes';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<DynamicAnalysis')) {
                $handler = \DynamicAnalysisHandler::class;
                $file = 'DynamicAnalysis';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<Project')) {
                $handler = \ProjectHandler::class;
                $file = 'Project';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<Upload')) {
                $handler = \UploadHandler::class;
                $file = 'Upload';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            } elseif (str_contains($content, '<testsuite')) {
                $handler = \TestingJUnitHandler::class;
                $file = 'TestJUnit';
            } elseif (str_contains($content, '<Done')) {
                $handler = \DoneHandler::class;
                $file = 'Done';
                $schema_file = "{$schemas_dir}/{$file}.xsd";
            }
        }

        // restore the file descriptor to beginning of file
        rewind($filehandle);

        // perform minimal error checking as a sanity check
        if ($file === '') {
            throw new CDashXMLValidationException(["ERROR: Could not determine submission"
                                                  ." file type for: '{$xml_file}'"]);
        }
        if (isset($schema_file) && !file_exists($schema_file)) {
            throw new CDashXMLValidationException(["ERROR: Could not find schema file '{$schema_file}'"
                                                  ." to validate input file: '{$xml_file}'"]);
        }

        return [
            'file_handle' => $filehandle,
            'xml_handler' => $handler,
            'xml_type' => $file,
            'xml_schema' => $schema_file,
        ];
    }

    /**
     * Validate the given XML file based on its type
     * @throws CDashXMLValidationException
     */
    public static function validate_xml(string $xml_file, string $schema_file): void
    {
        $errors = [];

        // let us control the failures so we can continue
        // parsing files instead of crashing midway
        libxml_use_internal_errors(true);

        // load the input file to be validated
        $xml = new DOMDocument();
        $xml->load($xml_file, LIBXML_PARSEHUGE);

        // run the validator and collect errors if there are any
        if (!$xml->schemaValidate($schema_file)) {
            $validation_errors = libxml_get_errors();
            foreach ($validation_errors as $error) {
                if ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                    $errors[] = "ERROR: {$error->message} in {$error->file},"
                                ." line: {$error->line}, column: {$error->column}";
                }
            }
            libxml_clear_errors();
        }

        if (count($errors) !== 0) {
            throw new CDashXMLValidationException($errors);
        }
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
                remove_build($buildid);
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
            $BuildUpdate->AssociateBuild(intval($build->SiteId), $build->Name, $build->GetStamp());
        }

        return $build->Id;
    }

    /** Extract the date from the build stamp */
    public static function extract_date_from_buildstamp($buildstamp)
    {
        return substr($buildstamp, 0, strpos($buildstamp, '-', strpos($buildstamp, '-') + 1));
    }

    /** Add the difference between the numbers of errors and warnings
     *  for the previous and current build
     *
     * TODO: Find a better home for this
     */
    public static function compute_error_difference($buildid, $previousbuildid, $warning)
    {
        $pdo = Database::getInstance()->getPdo();
        // Look at the difference positive and negative test errors
        $stmt = $pdo->prepare(
            'UPDATE builderror SET newstatus=1
        WHERE buildid=:buildid AND type=:type AND crc32 IN
        (SELECT crc32 FROM
        (SELECT crc32 FROM builderror WHERE buildid=:buildid AND type=:type)
        AS builderrora
        LEFT JOIN
        (SELECT crc32 as crc32b FROM builderror
        WHERE buildid=:previousbuildid AND type=:type)
        AS builderrorb ON builderrora.crc32=builderrorb.crc32b
        WHERE builderrorb.crc32b IS NULL)');
        $stmt->bindValue(':buildid', $buildid);
        $stmt->bindValue(':type', $warning);
        $stmt->bindValue(':previousbuildid', $previousbuildid);
        pdo_execute($stmt);

        // Recurring buildfailures are represented by the buildfailuredetails table.
        // Get a list of buildfailuredetails IDs for the current build and the
        // previous build.
        $current_failures = [];
        $previous_failures = [];

        $stmt = $pdo->prepare(
            'SELECT bf.detailsid FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=:buildid AND bfd.type=:type');
        $stmt->bindValue(':buildid', $buildid);
        $stmt->bindValue(':type', $warning);
        pdo_execute($stmt);
        while ($row = $stmt->fetch()) {
            $current_failures[] = $row['detailsid'];
        }

        $stmt = $pdo->prepare(
            'SELECT bf.detailsid FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=:previousbuildid AND bfd.type=:type');
        $stmt->bindValue(':previousbuildid', $previousbuildid);
        $stmt->bindValue(':type', $warning);
        pdo_execute($stmt);
        while ($row = $stmt->fetch()) {
            $previous_failures[] = $row['detailsid'];
        }

        // Check if any of these are new failures and mark them accordingly.
        foreach ($current_failures as $failure) {
            if (!in_array($failure, $previous_failures)) {
                $stmt = $pdo->prepare(
                    'UPDATE buildfailure SET newstatus=1
                WHERE buildid=:buildid AND detailsid=:detailsid');
                $stmt->bindValue(':buildid', $buildid);
                $stmt->bindValue(':detailsid', $failure);
                pdo_execute($stmt);
            }
        }

        // Maybe we can get that from the query (don't know).
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM builderror
        WHERE buildid=:buildid AND type=:type AND newstatus=1');
        $stmt->bindValue(':buildid', $buildid);
        $stmt->bindValue(':type', $warning);
        pdo_execute($stmt);
        $positives_array = $stmt->fetch();
        $npositives = $positives_array[0];

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=:buildid AND bfd.type=:type AND bf.newstatus=1');
        $stmt->bindValue(':buildid', $buildid);
        $stmt->bindValue(':type', $warning);
        pdo_execute($stmt);
        $positives_array = $stmt->fetch();
        $npositives += $positives_array[0];

        // Count how many build defects were fixed since the previous build.
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM
        (SELECT crc32 FROM builderror WHERE buildid=:previousbuildid AND type=:type) AS builderrora
        LEFT JOIN (SELECT crc32 as crc32b FROM builderror WHERE buildid=:buildid AND type=:type) AS builderrorb
        ON builderrora.crc32=builderrorb.crc32b
        WHERE builderrorb.crc32b IS NULL');
        $stmt->bindValue(':buildid', $buildid);
        $stmt->bindValue(':previousbuildid', $previousbuildid);
        $stmt->bindValue(':type', $warning);
        pdo_execute($stmt);
        $negatives_array = $stmt->fetch();
        $nnegatives = $negatives_array[0];

        foreach ($previous_failures as $failure) {
            if (!in_array($failure, $current_failures)) {
                $nnegatives += 1;
            }
        }

        // Check if a diff already exists for this build.
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT * FROM builderrordiff WHERE buildid=:buildid AND type=:type FOR UPDATE');
        $stmt->bindParam(':buildid', $buildid);
        $stmt->bindParam(':type', $warning);
        pdo_execute($stmt);
        $row = $stmt->fetch();
        $existing_npositives = 0;
        $existing_nnegatives = 0;
        if ($row) {
            $existing_npositives = $row['difference_positive'];
            $existing_nnegatives = $row['difference_negative'];
        }

        // Only log if there's a diff since last build or an existing diff record.
        if ($npositives == 0 && $nnegatives == 0 && $existing_npositives == 0 && $existing_nnegatives == 0) {
            $pdo->commit();
            return;
        }

        if ($row) {
            // Update existing record.
            $stmt = $pdo->prepare(
                'UPDATE builderrordiff
            SET difference_positive=:npositives, difference_negative=:nnegatives
            WHERE buildid=:buildid AND type=:type');
        } else {
            // Insert new record.
            $duplicate_sql = '';
            if (config('database.default') !== 'pgsql') {
                $duplicate_sql =
                    'ON DUPLICATE KEY UPDATE difference_positive=:npositives, difference_negative=:nnegatives';
            }
            $stmt = $pdo->prepare(
                "INSERT INTO builderrordiff
            (buildid, type, difference_positive, difference_negative)
            VALUES (:buildid, :type, :npositives, :nnegatives)
            $duplicate_sql");
        }
        $stmt->bindValue(':buildid', $buildid);
        $stmt->bindValue(':type', $warning);
        $stmt->bindValue(':npositives', $npositives);
        $stmt->bindValue(':nnegatives', $nnegatives);
        if (!pdo_execute($stmt)) {
            $pdo->rollBack();
            return;
        }
        $pdo->commit();
    }
}
