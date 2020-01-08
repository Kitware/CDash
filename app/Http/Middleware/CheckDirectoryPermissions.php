<?php

namespace App\Http\Middleware;

use Closure;
use DirectoryIterator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CheckDirectoryPermissions
{
    private $dirsToCheck = [
        'backup',
        'log',
        'public/upload',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (App::environment(['local', 'testing'])) {
            $tmpl = "%s is not %s by %s";
            $process = "current process";

            if (extension_loaded('posix')) {
                $user = posix_getpwuid(posix_geteuid());
                $process = "user, {$user['name']} [uid: {$user['uid']}]";
            }

            foreach ($this->dirsToCheck as $dir) {
                $path = app_path() . "/cdash/{$dir}";

                if (!is_writeable($path)) {
                    $message = sprintf($tmpl, $path, 'writable', $process);
                    Log::critical($message);
                }

                if (is_readable($path)) {
                    $dir = new RecursiveDirectoryIterator($path);
                    $itr = new RecursiveIteratorIterator($dir);
                    $itr->rewind();
                    while ($itr->valid()) {
                        /** @var DirectoryIterator $itr */
                        if ($itr->isFile()) {
                            if (!$itr->isWritable()) {
                                $message = sprintf($tmpl, $itr->getPathname(), 'writable', $process);
                                Log::critical($message);
                            }
                            if (!$itr->isReadable()) {
                                $tmpl = sprintf($tmpl, $itr->getPathname(), 'readable', $process);
                                Log::critical($message);
                            }
                        }

                        $itr->next();
                    }
                }
            }
        }

        return $next($request);
    }
}
