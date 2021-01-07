<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\Flysystem\Adapter\AbstractAdapter;
use Storage;

/**
 * Class CDash
 * @package App\Http\Controllers
 */
class CDash extends Controller
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
        $this->disk = \Storage::disk('cdash');
        $this->middleware('password')->except([]);
    }

    /**
     * Handle the incoming request.
     *
     * @return Response $response
     */
    public function __invoke()
    {
        if ($this->isValidRequest()) {
            if ($this->isRequestForExport()) {
                $response = $this->handleFileRequest();
            } elseif ($this->isApiRequest()) {
                $response = $this->handleApiRequest();
            } elseif ($this->isPartialRequest()) {
                $response = $this->handlePartialRequest();
            } elseif ($this->isSubmission()) {
                $response = $this->handleSubmission();
            } elseif ($this->isFileRequest()) {
                $response = $this->handleFileRequest();
            } else {
                $response = $this->handleRequest();
            }
        } else {
            $response = response('Not found', 404);
        }

        $status = http_response_code();
        if ($status > Response::HTTP_OK) {
            $msg = "CDash: Path: {$this->getPath()}:[HTTP Status] {$status}";
            if ($status < response::HTTP_BAD_REQUEST) {
                Log::info($msg);
            } else {
                Log::error($msg);
            }
        }
        return $response;
    }

    /**
     * Determines if the file being requested is in the CDash filesystem
     *
     * @return bool
     */
    public function isValidRequest()
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
     *
     * @return bool
     */
    public function isApiRequest()
    {
        $path = $this->getPath();
        return strpos($path, 'api/v') === 0;
    }

    /**
     * Determines if the request is a CTest submission
     *
     * @return false|int
     */
    public function isSubmission()
    {
        $path = $this->getPath();
        return preg_match('/submit.php/', $path);
    }

    /**
     * Determines if the request is made via XHR requesting partial HTML
     *
     * @return bool
     */
    public function isPartialRequest()
    {
        $path = $this->getPath();
        return strpos($path, 'ajax/') === 0;
    }

    /**
     * @return bool
     */
    public function isFileRequest()
    {
        $path = $this->getPath();
        $endpoints = config('cdash.file.endpoints');
        $export = $this->isRequestForExport();
        return in_array($path, $endpoints);
    }

    /**
     * @return bool
     */
    public function isRequestForExport()
    {
        $export = request('export');
        return $export && in_array($export, ['csv']);
    }

    /**
     * Processes the CDash file for a given request
     *
     * @return \Illuminate\Http\RedirectResponse|string
     */
    public function getRequestContents()
    {
        /** @var  AbstractAdapter $adapter */
        $adapter = $this->getDiskAdapter();
        $file = $this->getAbsolutePath();

        chdir($adapter->getPathPrefix());

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
     * Returns response containing HTML partial
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     */
    public function handlePartialRequest()
    {
        $content = $this->getRequestContents();
        return response($content, 200);
    }

    /**
     * Returns the CTest submission status XML
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     */
    public function handleSubmission()
    {
        $content = $this->getRequestContents();
        $status = is_a($content, Response::class)? $content->getStatusCode() : 200;
        return \response($content, $status)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Returns the requested file
     *
     * @return mixed
     */
    public function handleFileRequest()
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
            $response = \response()->stream(function () use ($content) {
                echo $content['file'];
            }, 200, $headers);
        } elseif (is_a($content, RedirectResponse::class)) {
            $response = $content;
        } else {
            // return a regular response because the output is not what we expected
            $response = \response($content, 400);
        }

        return $response;
    }

    /**
     * Returns a Laravel view response
     *
     * @return ResponseTrait
     */
    public function handleRequest()
    {
        $content = $this->getRequestContents() ?: ''; // view does not like null
        return is_a($content, RedirectResponse::class) ?
            $content : $this->view($content);
    }

    /**
     * Returns the path of the request with consideration given to the root path
     *
     * @return string
     */
    public function getPath()
    {
        if (!$this->path) {
            $path = $this->request->path();
            $this->path = '/' === $path ? 'index.php' : $path;
        }

        return $this->path;
    }

    /**
     *
     * @return string
     */
    public function getAbsolutePath()
    {
        /** @var  AbstractAdapter $adapter */
        $adapter = $this->getDiskAdapter();
        $path = $this->getPath();

        $file = $adapter->applyPathPrefix($path);
        return $file;
    }

    public function getDiskAdapter()
    {
        return $this->disk->getDriver()->getAdapter();
    }

    /**
     * Returns the blade view with CDash layout
     *
     * @param string $content
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function view(string $content)
    {
        $controller = $this->getController();
        return view('cdash',
            [
                'content' => $content,
                'controller' => $controller,
                'title' => $this->getTitle(),
                'xsl' => empty($controller),
                'js_version' => $this->getJsVersion(),
            ]
        );
    }

    /**
     * Returns the version used to find compiled css and javascript files
     *
     * @return string
     */
    public function getJsVersion()
    {
        $path = config('cdash.file.path.js.version');
        $version = '';
        if (is_readable($path)) {
            $file = file_get_contents($path);
            if (preg_match("/'VERSION',\s+'([0-9.]+)'/", $file, $match)) {
                $version = $match[1];
            }
        }
        return $version;
    }

    /**
     * Returns the Angular controller name for a given request
     *
     * @return string
     */
    public function getController()
    {
        $name = '';
        $path = $this->getPath();
        $file = pathinfo(substr($path, strrpos($path, '/')), PATHINFO_FILENAME);

        // Special case: viewBuildGroup.php shares a controller with index.php.
        if ($file === 'viewBuildGroup') {
            $file = 'index';
        }
        $controller_path = config('cdash.file.path.js.controllers');
        $controller = "{$controller_path}/{$file}.js";
        if (is_readable($controller)) {
            $name = Str::studly($file) . 'Controller';
        }

        return $name;
    }

    /**
     * Returns the HTML title for a given request
     *
     * @return string|string[]|null
     */
    public function getTitle()
    {
        $path = $this->getPath();
        $file = pathinfo(substr($path, strrpos($path, '/')), PATHINFO_FILENAME);
        $title = Str::studly($file);
        return preg_replace('/(?=[A-Z])/', " ", $title);
    }
}
