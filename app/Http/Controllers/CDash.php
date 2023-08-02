<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * TODO: (williamjallen) This file now only serves as a route handler for API routes which have not
 *       been migrated to Laravel yet.  The ultimate goal is to remove this file entirely.
 *
 * Class CDash
 * @package App\Http\Controllers
 */
final class CDash extends AbstractController
{
    /** @var Request $request */
    private $request;

    /** @var FilesystemAdapter $disk */
    private $disk;

    /** @var string $path */
    private $path;

    /**
     * CDash constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->disk = Storage::disk('cdash');
    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return mixed $response
     */
    public function __invoke(Request $request)
    {
        $this->request = $request;
        $this->path = '';
        if (!$this->isValidRequest() || !$this->isApiRequest()) {
            abort(404);
        }

        $response = $this->handleApiRequest();

        $status = http_response_code();
        if ($status > Response::HTTP_OK) {
            $msg = "CDash: Path: {$this->getPath()}:[HTTP Status] {$status}";
            if ($status < Response::HTTP_BAD_REQUEST) {
                Log::info($msg);
            } else {
                Log::error($msg);
            }
        }
        return $response;
    }

    /**
     * Determines if the file being requested is in the CDash filesystem
     */
    public function isValidRequest(): bool
    {
        $valid = false;
        $path = $this->getPath();

        if ($this->disk->exists($path)) {
            $valid = true;
        }
        return $valid;
    }

    /**
     * Determines if the request is a request for a CDash api endpoint
     */
    public function isApiRequest(): bool
    {
        $path = $this->getPath();
        return str_starts_with($path, 'api/');
    }

    /**
     * Processes the CDash file for a given request
     */
    public function getRequestContents()
    {
        $file = $this->getAbsolutePath();
        chdir($this->disk->path(''));

        ob_start();
        $redirect = require $file;
        $content = ob_get_contents();
        ob_end_clean();

        // Possible values of $redirect are null, 0, 1 and a ResponseRedirect.
        // Clearly we want to ignore when null or int, otherwise $redirect should
        // be returned
        return is_numeric($redirect) || is_null($redirect) ? $content : $redirect;
    }

    /**
     * Returns JSON responses for CDash API requests or regular response given an
     * un-decodable json response
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|Response|\Symfony\Component\HttpFoundation\Response
     */
    public function handleApiRequest()
    {
        $json = $this->getRequestContents();
        $status = http_response_code(); // this should be empty if not previously set

        if (is_a($json, \Symfony\Component\HttpFoundation\Response::class)) {
            return $json;
        } elseif (empty($json)) {
            $status = $status ?: 204;
            $response = response('', $status);
        } elseif (($decoded = json_decode($json))) {
            $response = response()->json($decoded);
            if ($status) {
                $response->setStatusCode($status);
            }
        } else {
            $msg = json_last_error_msg();
            $response = response($msg, 500);
        }
        return $response;
    }

    /**
     * Returns the path of the request with consideration given to the root path
     */
    public function getPath(): string
    {
        if (!$this->path) {
            $path = $this->request->path();
            $this->path = '/' === $path ? 'index.php' : $path;
        }

        return $this->path;
    }

    public function getAbsolutePath(): string
    {
        $path = $this->getPath();
        $file = $this->disk->path($path);
        return $file;
    }
}
