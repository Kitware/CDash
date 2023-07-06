<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TODO: (williamjallen) should some of this logic be moved to AbstractController.php?
 *
 * Class CDash
 * @package App\Http\Controllers
 */
class CDash extends AbstractController
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
     * @return Response $response
     */
    public function __invoke(Request $request)
    {
        $this->request = $request;
        $this->path = '';
        if (!$this->isValidRequest()) {
            abort(404);
        }

        if ($this->isRequestForExport()) {
            $response = $this->handleFileRequest();
        } elseif ($this->isApiRequest()) {
            $response = $this->handleApiRequest();
        } elseif ($this->isSubmission()) {
            $response = $this->handleSubmission();
        } else {
            $response = $this->handleRequest();
        }

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
     * Determines if the request is a CTest submission
     */
    public function isSubmission(): bool
    {
        $path = $this->getPath();
        return boolval(preg_match('/submit\.php/', $path));
    }

    public function isRequestForExport(): bool
    {
        $export = request('export');
        return $export && in_array($export, ['csv']);
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
     * Returns the CTest submission status XML
     */
    public function handleSubmission(): Response
    {
        $content = $this->getRequestContents();
        $status = is_a($content, Response::class) ? $content->getStatusCode() : 200;
        return response($content, $status)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Returns the requested file
     */
    public function handleFileRequest(): Response|RedirectResponse|StreamedResponse
    {
        $content = $this->getRequestContents();

        if (is_array($content)
            && array_key_exists('file', $content)
            && array_key_exists('type', $content)
        ) {
            $headers = [];
            $headers['Content-Type'] = $content['type'];
            if (isset($content['filename'])) {
                $headers['Content-Disposition'] = "attachment; filename=\"{$content['filename']}\"";
            }
            $response = response()->stream(function () use ($content) {
                echo $content['file'];
            }, 200, $headers);
        } elseif (is_a($content, RedirectResponse::class)) {
            $response = $content;
        } else {
            // return a regular response because the output is not what we expected
            $response = response($content, 400);
        }

        return $response;
    }

    /**
     * Returns a Laravel view response
     */
    public function handleRequest(): RedirectResponse|View
    {
        $content = $this->getRequestContents() ?: ''; // view does not like null
        return is_a($content, RedirectResponse::class) ?
            $content : $this->view($content);
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

    /**
     * Returns the blade view with CDash layout
     */
    protected function view(string $content): View
    {
        return view('cdash',
            [
                'xsl_content' => $content,
                'xsl' => true,
                'js_version' => self::getJsVersion(),
            ]
        );
    }
}
