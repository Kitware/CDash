<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSubmission;
use CDash\Model\PendingSubmissions;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class RemoteProcessingController extends AbstractController
{
    /**
     * Retrieve a file from a particular submission.
     * This includes XML files as well as coverage tarballs, which are temporarily
     * stored in storage/app/inbox.
     * These are temporarily stored files which are removed after they've been processed,
     * usually by a queue.
     *
     * GET /getSubmissionFile.php
     * Required Params:
     * filename=[string] Filename to retrieve, must live in tmp_submissions directory
     **/
    public function getSubmissionFile(): Response
    {
        if (! (bool) config('cdash.remote_workers')) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        if (!request()->has('filename')) {
            return response('Bad request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $input_filename = decrypt($_REQUEST['filename']);
        } catch (DecryptException $e) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        $filename = Storage::path('inprogress') . '/' . basename($input_filename);
        if (!is_readable($filename)) {
            return response('Not found', Response::HTTP_NOT_FOUND);
        } else {
            return response()->file($filename);
        }
    }

    /**
     * Delete the temporary file related to a particular submission.
     *
     * DELETE /deleteSubmissionFile.php
     * Required Params:
     * filename=[string] Filename to delete, must live in tmp_submissions directory
     * Optional Params:
     * dest=[string] Instead of deleting, rename filename to dest
     **/
    public function deleteSubmissionFile(): Response
    {
        if (! (bool) config('cdash.remote_workers')) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        if (!request()->has('filename')) {
            return response('Bad request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $filename = decrypt(request()->input('filename'));
        } catch (DecryptException $e) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        if (!Storage::exists($filename)) {
            return response('File not found', Response::HTTP_NOT_FOUND);
        }

        if (config('cdash.backup_timeframe') == 0) {
            // Delete the file.
            if (Storage::delete($filename)) {
                return response('OK', Response::HTTP_OK);
            } else {
                return response('Deletion failed', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } elseif (request()->has('dest')) {
            // Rename the file.
            try {
                $dest = decrypt(request()->input('dest'));
            } catch (DecryptException $e) {
                return response('This feature is disabled', Response::HTTP_CONFLICT);
            }
            if (Storage::move($filename, $dest)) {
                return response('OK', Response::HTTP_OK);
            } else {
                return response('Rename failed', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    /**
     * Requeue the file related to a particular submission.
     *
     * Required Params:
     * filename=[string] Filename to requeue, must live in the 'inprogress' directory
     **/
    public function requeueSubmissionFile(): Response
    {
        if (! (bool) config('cdash.remote_workers')) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        if (!request()->has('filename') || !request()->has('buildid') || !request()->has('projectid')) {
            return response('Bad request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $filename = decrypt(request()->input('filename'));
        } catch (DecryptException) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }
        $buildid = request()->integer('buildid');
        $projectid = request()->integer('projectid');
        if (!Storage::exists("inprogress/{$filename}")) {
            return response('File not found', Response::HTTP_NOT_FOUND);
        }

        $retry_handler = new \RetryHandler(Storage::path("inprogress/{$filename}"));
        $retry_handler->increment();

        // Move file back to inbox.
        Storage::move("inprogress/{$filename}", "inbox/{$filename}");

        // Requeue the file with exponential backoff.
        PendingSubmissions::IncrementForBuildId($buildid);
        $delay = pow(config('cdash.retry_base'), $retry_handler->Retries);
        ProcessSubmission::dispatch($filename, $projectid, $buildid, md5_file(Storage::path("inbox/{$filename}")))->delay(now()->addSeconds($delay));
        return response('OK', Response::HTTP_OK);
    }
}
