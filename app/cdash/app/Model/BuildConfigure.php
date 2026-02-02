<?php

/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

namespace CDash\Model;

use App\Models\BuildConfigure as EloquentBuildConfigure;
use App\Models\Configure as EloquentConfigure;
use CDash\Database;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** BuildConfigure class */
class BuildConfigure
{
    public $Id;
    public $StartTime;
    public $EndTime;
    public $Command;
    public $Log;
    public $Status;
    public $BuildId;
    public $NumberOfWarnings;
    public $NumberOfErrors;
    public Collection $LabelCollection;
    private $Crc32;

    /**
     * BuildConfigure constructor.
     */
    public function __construct()
    {
        $this->Command = '';
        $this->Log = '';
        $this->Status = '';
        $this->LabelCollection = collect();
    }

    public function AddLabel($label): void
    {
        $label->BuildId = $this->BuildId;
        $this->LabelCollection->put($label->Text, $label);
    }

    /** Check if the configure exists */
    public function Exists(): bool
    {
        // Check by Id if it is set.
        if ($this->Id > 0) {
            return $this->ExistsHelper('id', $this->Id);
        }

        // Next, try crc32.
        if ($this->Command !== '' && $this->Log !== '' && $this->Status !== '') {
            return $this->ExistsByCrc32();
        }

        // Lastly, try buildid.
        return $this->ExistsByBuildId();
    }

    /**
     * TODO: This is a beautiful example of why getters with side effects are a horrible idea...
     *
     * Check if a configure record exists for a given field and value.
     * Populate this object from the database if such a record is found.
     */
    private function ExistsHelper(string $field, $value): bool
    {
        $configure = EloquentConfigure::where($field, $value)->first();
        if ($configure !== null) {
            $this->Id = $configure->id;
            $this->Command = $configure->command;
            $this->Log = $configure->log;
            $this->NumberOfErrors = $configure->status;
            $this->NumberOfWarnings = $configure->warnings;
            $this->Crc32 = $configure->crc32;
            return true;
        }
        return false;
    }

    /** Check if a configure record exists for these contents. */
    public function ExistsByCrc32(): bool
    {
        if ($this->Command === '' || $this->Status === '') {
            return false;
        }
        $this->Crc32 = crc32($this->Command . $this->Log . $this->Status);
        return $this->ExistsHelper('crc32', $this->Crc32);
    }

    /** Check if a configure record exists for this Id. */
    public function ExistsByBuildId(): bool
    {
        if (!$this->BuildId) {
            Log::error('BuildId not set', [
                'function' => 'BuildConfigure::Exists',
            ]);
            return false;
        }
        if (!is_numeric($this->BuildId)) {
            Log::error('BuildId is not numeric', [
                'function' => 'BuildConfigure::Exists',
            ]);
            return false;
        }

        $configure = EloquentBuildConfigure::firstWhere('buildid', (int) $this->BuildId);

        if ($configure === null) {
            return false;
        }
        return $this->ExistsHelper('id', $configure->configureid);
    }

    /** Delete a current configure given a buildid
     * Returns true if the configure row was deleted from the database.
     */
    public function Delete(): bool
    {
        if (!$this->Exists()) {
            Log::error('this configure does not exist', [
                'function' => 'BuildConfigure::Delete',
            ]);
            return false;
        }

        // Delete the configure row if it is not shared with any other build.
        $retval = false;
        $count = EloquentBuildConfigure::where('configureid', (int) $this->Id)->count();
        if ($count < 2) {
            EloquentConfigure::find((int) $this->Id)?->delete();
            $retval = true;
        }

        if ($this->BuildId) {
            // Delete the build2configure row for this build.
            EloquentBuildConfigure::where('buildid', (int) $this->BuildId)->delete();
        }

        return $retval;
    }

    public function InsertLabelAssociations(): void
    {
        if ($this->BuildId) {
            if ($this->LabelCollection->isEmpty()) {
                return;
            }

            foreach ($this->LabelCollection as $label) {
                $label->BuildId = $this->BuildId;
                $label->Insert();
            }
        } else {
            Log::error('No BuildConfigure::BuildId - cannot call $label->Insert...', [
                'function' => 'BuildConfigure::InsertLabelAssociations',
            ]);
        }
    }

    /**
     * Save in the database.  Returns true is a new configure row was created,
     * false otherwise.
     */
    public function Insert()
    {
        if (!$this->BuildId) {
            Log::error('BuildId not set', [
                'function' => 'BuildConfigure::Insert',
                'configureid' => $this->Id,
            ]);
            return false;
        }

        if ($this->ExistsByBuildId()) {
            Log::error('This build already has a configure', [
                'function' => 'BuildConfigure::Insert',
                'configureid' => $this->Id,
                'buildid' => $this->BuildId,
            ]);
            return false;
        }

        $new_configure_inserted = false;
        if (!$this->ExistsByCrc32()) {
            // No such configure exists yet, insert a new row.
            try {
                $this->Id = EloquentConfigure::create([
                    'command' => $this->Command,
                    'log' => $this->Log,
                    'status' => $this->Status,
                    'crc32' => $this->Crc32,
                ])->id;
                $new_configure_inserted = true;
            } catch (Exception $e) {
                // This error might be due to a unique constraint violation.
                // Query again to see if this configure was created since
                // the last time we checked.
                if ($this->ExistsByCrc32()) {
                    return true;
                }
                report($e);
                return false;
            }
        }

        // Insert a new build2configure row for this build.
        EloquentBuildConfigure::create([
            'buildid' => $this->BuildId,
            'configureid' => $this->Id,
            'starttime' => $this->StartTime,
            'endtime' => $this->EndTime,
        ]);

        $this->InsertLabelAssociations();
        return $new_configure_inserted;
    }

    /** Return true if the specified line contains a configure warning,
     * false otherwise.
     */
    public static function IsConfigureWarning($line): bool
    {
        return str_contains($line, 'CMake Warning') || str_contains($line, 'WARNING:');
    }

    /**
     * Returns configurations for the build
     */
    public function GetConfigureForBuild(): mixed
    {
        if (!$this->BuildId) {
            Log::warning('BuildId not set', [
                'function' => 'BuildConfigure::GetConfigureForBuild()',
            ]);
            return false;
        }

        return DB::select('
            SELECT *
            FROM configure c
            JOIN build2configure b2c ON c.id = b2c.configureid
            WHERE buildid = ?
        ', [$this->BuildId])[0] ?? false;
    }

    /** Compute the warnings from the log. In the future we might want to add errors */
    public function ComputeWarnings(): void
    {
        $this->NumberOfWarnings = 0;
        $log_lines = explode("\n", $this->Log);
        $numlines = count($log_lines);

        $stmt = Database::getInstance()->getPdo()->prepare(
            'INSERT INTO configureerror (configureid, type, text)
             VALUES (:id, 1, :text)');
        $stmt->bindParam(':id', $this->Id);

        for ($l = 0; $l < $numlines; $l++) {
            if ($this->IsConfigureWarning($log_lines[$l])) {
                $precontext = '';
                $postcontext = '';

                // Get 2 lines of precontext
                $pre_start = max($l - 2, 0);
                for ($j = $pre_start; $j < $l; $j++) {
                    $precontext .= $log_lines[$j] . "\n";
                }

                // Get 5 lines of postcontext
                $post_end = min($l + 6, $numlines);
                for ($j = $l + 1; $j < $post_end; $j++) {
                    $postcontext .= $log_lines[$j] . "\n";
                }

                // Add the warnings in the configureerror table
                $warning = $precontext . $log_lines[$l] . "\n" . $postcontext;
                $stmt->bindParam(':text', $warning);
                pdo_execute($stmt);

                $this->NumberOfWarnings++;
            }
        }

        EloquentConfigure::find((int) $this->Id)?->update([
            'warnings' => $this->NumberOfWarnings,
        ]);
    }

    /** Get the number of configure error for a build */
    public function ComputeErrors(): int
    {
        if (!$this->Exists()) {
            return 0;
        }
        return (int) $this->NumberOfErrors;
    }

    /**
     * @return array<string, mixed>
     */
    public static function marshal($data): array
    {
        $response = [
            'status' => $data['status'],
            'command' => $data['command'],
            'output' => $data['log'],
            'configureerrors' => $data['configureerrors'],
            'configurewarnings' => $data['configurewarnings'],
        ];

        if (isset($data['subprojectid'])) {
            $response['subprojectid'] = $data['subprojectid'];
            $response['subprojectname'] = $data['subprojectname'];
        }

        return $response;
    }

    /**
     * Returns the current BuildConfigure's Label property as a LabelCollection.
     */
    public function GetLabelCollection(): Collection
    {
        return $this->LabelCollection;
    }
}
