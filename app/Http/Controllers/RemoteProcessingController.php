<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSubmission;
use CDash\Model\PendingSubmissions;
use Exception;
use Illuminate\Support\Facades\Storage;
use RetryHandler;
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
     * GET /getSubmissionFile
     * Required Params:
     * filename=[string] Filename to retrieve, must live in tmp_submissions directory
     **/
    public function getSubmissionFile(): Response
    {
        if (!request()->has('filename')) {
            return response('Bad request', Response::HTTP_BAD_REQUEST);
        }

        $filename = Storage::path('inprogress') . '/' . basename(request()->string('filename'));
        if (!is_readable($filename)) {
            return response('Not found', Response::HTTP_NOT_FOUND);
        } else {
            return response()->file($filename);
        }
    }

    /**
     * Delete the temporary file related to a particular submission.
     *
     * DELETE /deleteSubmissionFile
     * Required Params:
     * filename=[string] Filename to delete, must live in tmp_submissions directory
     * Optional Params:
     * dest=[string] Instead of deleting, rename filename to dest
     **/
    public function deleteSubmissionFile(): Response
    {
        if (!(bool) config('cdash.remote_workers')) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        if (!request()->has('filename')) {
            return response('Bad request', Response::HTTP_BAD_REQUEST);
        }

        $filename = request()->string('filename');

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
            if (Storage::move($filename, request()->string('dest'))) {
                return response('OK', Response::HTTP_OK);
            } else {
                return response('Rename failed', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } else {
            throw new Exception('Invalid request.');
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
        if (!(bool) config('cdash.remote_workers')) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        if (!request()->has('filename') || !request()->has('buildid') || !request()->has('projectid')) {
            return response('Bad request', Response::HTTP_BAD_REQUEST);
        }

        $filename = request()->string('filename');
        $buildid = request()->integer('buildid');
        $projectid = request()->integer('projectid');
        $md5 = request()->string('md5');
        if (!Storage::exists("inprogress/{$filename}")) {
            return response('File not found', Response::HTTP_NOT_FOUND);
        }

        $retry_handler = new RetryHandler(Storage::path("inprogress/{$filename}"));
        $retry_handler->increment();

        // Move file back to inbox.
        Storage::move("inprogress/{$filename}", "inbox/{$filename}");

        // Requeue the file with exponential backoff.
        PendingSubmissions::IncrementForBuildId($buildid);
        $delay = ((int) config('cdash.retry_base')) ** $retry_handler->Retries;
        ProcessSubmission::dispatch($filename, $projectid, $buildid, $md5)->delay(now()->addSeconds($delay));
        return response('OK', Response::HTTP_OK);
    }
}
