<?php

namespace App\Jobs;

use App\Models\Build;
use App\Models\Project;
use App\Models\UploadFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * Deletes all uploaded files which exceed the project upload size quota.
 */
class PruneUploads implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $uploaded_files_to_check = collect();

        // First, we determine the total size of the uploaded files for the project.
        foreach (Project::all() as $project) {
            // We deliberately load this separately for each project to minimize query running time,
            // at the expense of issuing more queries overall.  Laravel unfortunately does not support
            // HasManyThrough with many-to-many relationships, so there's no practical way to do this
            // with Eloquent.  In the future, this should be converted to use Eloquent once Laravel
            // adds support...
            $total_size = (int) (DB::select('
                SELECT SUM(uploadfile.filesize) AS s
                FROM uploadfile
                INNER JOIN build2uploadfile ON build2uploadfile.fileid = uploadfile.id
                INNER JOIN build ON build2uploadfile.buildid = build.id
                INNER JOIN project ON build.projectid = project.id
                WHERE project.id = ?
            ', [$project->id])[0]->s ?? -1);

            if ($total_size > $project->uploadquota) {
                // We're over the limit, so delete references to files from oldest to newest until we're
                // under the limit.  We're probably not over the limit by very much, so we load in relatively
                // small chunks to minimize the amount of data being loaded.
                $project->builds()->orderBy('starttime')->with('uploadedFiles')->chunk(100, function (Collection $builds) use (&$total_size, &$project, &$uploaded_files_to_check) {
                    /** @var Build $build */
                    foreach ($builds as $build) {
                        Log::debug("Removing references to uploaded files for build {$build->id}");
                        $total_size -= $build->uploadedFiles()->sum('filesize');
                        $uploaded_files_to_check = $uploaded_files_to_check->merge($build->uploadedFiles()->pluck('id'));
                        $build->uploadedFiles()->detach();

                        if ($total_size <= $project->uploadquota) {
                            // Stop the build chunking.
                            return false;
                        }
                    }

                    // Grab another chunk of builds...
                    return true;
                });
            }
        }

        if ($uploaded_files_to_check->count() === 0) {
            return;
        }

        // Once we've removed all of the builds which exceed their respective projects' limits, we
        // delete the files which are no longer referenced.
        $uploaded_files_to_check = UploadFile::doesntHave('builds')->findMany($uploaded_files_to_check);
        Log::info("Deleting {$uploaded_files_to_check->count()} uploaded files or urls.");
        /** @var UploadFile $file */
        foreach ($uploaded_files_to_check as $file) {
            if (!$file->isurl) {
                try {
                    File::delete($file->file());
                } catch (FileNotFoundException) {
                    Log::warning("Attempt to delete uploaded file which does not exist: {$file->sha1sum}");
                }
            }
            $file->delete();
        }
    }
}
