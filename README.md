## Requirements
* PHP 7.1.*

## Installation

### Laravel

Use the Laravel installer to create a project, e.g.:

```bash
$ laravel install cdash
```

The Laravel installer will create a directory named `cdash` with the Laravel application framework contained within. This location henceforth will be referred to in its absolute form as `$INSTALL_DIR`.

### CDash

`cd` into the newly created Laravel project then checkout as a submodule CDash, e.g.:

```bash
$ git submodule add git@github.com:Kitware/CDash.git app/cdash
```

Then perform normal CDash setup.

### HTTP Server Access

Normal CDash installation requires you to ensure that the following directories are writable by the webserver (e.g. daemon or httpd) (from the CDash root):

* ./backup
* ./log
* ./public/upload
* ./public/rss

Additionally, the following Laravel directories also need write permission (from the Laravel root):

* ./storage/framework/cache
* ./storage/framework/sessions
* ./storage/framework/views
* ./storage/logs

### Fallback Route Handling

While Laravel has a method for this very purpose, i.e. `Route::fallback`, it unfortunately only handles `GET` requests. Because we need Laravel to route all unhandled routes to CDash regardless of http method, we need to create our own fallback handler. Fortunately this is easily accomplished by adding the following to the `<root>/routes/web.php` routes file:

```php
Route::any('{url}', 'CDash')->where('url', '.*');
```

_It is important to remember that line must always remain as the last line in the file, otherwise, routes appearing after that statement will not get handled in the expected manner._

We also need to remove the default route in `routes/web.php` so that we can handle the index request to CDash.

```php
// comment out or remove the following from routes/web.php
Route::get('/', function () {
    return view('welcome');
});
```

### CDash Controller

In our routes file we specified that any url not already handled will be caught by our fallback route. You may notice that the usual `controller@action` is not present as the second argument to the `Route::any` method. Instead we're using request handler, otherwise known as an invokable controller, to manage the incoming CDash request.

The CDash request handler simply needs to ensure that the incoming request can be matched to the CDash filesystem, create a buffer, require the file indicated by the path, collect the output from the buffer, turn off the buffer, then set the collected output in a `Illuminate\Http\Response` object and return it to continue normal Laravel operation.

Below is an example of the controller, though, it is subject to change:

```php
namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Storage;

class CDash extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @return Response $response
     */
    public function __invoke(Request $request)
    {
        // Get the requested file from the request
        $path = $request->path();

        /** @var FilesystemAdapter $cdash */
        $cdash = Storage::disk('cdash');

        // ensure that the file exists in the CDash filesystem
        if ($cdash->exists($path)) {
            $file = $cdash->getDriver()
                ->getAdapter()
                ->applyPathPrefix($path);

            // change directories so that CDash can require its includes
            chdir(app_path('cdash/public'));

            // create a buffer
            ob_start();
            $nocontent = require $file;
            $content = ob_get_contents();
            ob_end_clean();

            if (is_array($nocontent) && isset($nocontent['Location'])) {
                return redirect($nocontent['Location']);
            }
            
            // return a normal Response object with CDash output
            $response = new Response($content, 200);
            return $response;
        } else {
            $response = new Response('<strong>Not found</strong>', 404);
            return $response->header('Content-Type', 'text/html');
        }
    }
}
```

### Laravel's Default CSRF Protection

This is turned off for files ending in .php for the time being. Investigate the best path forward to turning it back on.

To turn off for all URIs ending in `.php`:
```php
// in App\Http\Middleware\VerifyCsrfToken
protected $except = [
        '*.php'
    ];
```

To turn back on remove the asterisk from the array.

### Testing CDash

Now that we presumably have everything set up correctly we can test that CDash is able to run the CTest, `test_install`, by creating a build directory, e.g. `<path to laravel root>/app/cdash/_build`, `cd` into the directory then:
```bash
$ ccmake ..
``` 

When running ccmake be sure to turn on testing, turn on htaccess handling and set your database and http server information correctly. When finished run:

```bash
$ ctest -R install
```

and hope for the best.

### Handling CDash Assets

CDash assets such as images, style sheets, et al. can be linked into Laravel's public directory eliminating the need to have the request processed by the application.

For instance CDash views are build and stored in `$INSTALL_DIR/app/cdash/public/build`. We can easily provide access to these by creating a symbolic link to these directories from Laravel's public directory:

```bash
$ ln -s $INSTALL_DIR/app/cdash/public/build $INSTALL_DIR/public/build
$ ln -s $INSTALL_DIR/app/cdash/public/img $INSTALL_DIR/public/img
$ ln -s $INSTALL_DIR/app/cdash/public/css $INSTALL_DIR/public/css
$ ln -s $INSTALL_DIR/app/cdash/public/js $INSTALL_DIR/public/js
$ ln -s $INSTALL_DIR/app/cdash/public/views $INSTALL_DIR/public/views
```

