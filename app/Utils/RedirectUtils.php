<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Http\Request;

class RedirectUtils
{
    /**
     * Whether a URL points to this CDash instance.  Check URLs that come from the request or the session before
     * redirecting to them, so that a link to CDash can't send users to another site.
     */
    public static function isLocalUrl(string $url): bool
    {
        // Browsers ignore some control characters, so "/\t/example.org" would mean "//example.org".
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        // Laravel resolves a path against APP_URL, so "/projects" is a CDash page too.
        $path = str_starts_with($url, '/') ? $url : self::pathBelowRootUrl($url);
        if ($path === null) {
            return false;
        }

        // Browsers read "//example.org" as another host, treat "\" in a path as "/", and resolve "." and ".." segments,
        // even percent-encoded ones, which could leave CDash's path.
        $path = explode('?', explode('#', $path, 2)[0], 2)[0];
        return !str_starts_with($path, '//')
            && !str_contains($path, '\\')
            && preg_match('#(^|/)\.\.?(/|$)#', rawurldecode($path)) !== 1;
    }

    /**
     * The rest of an absolute URL after CDash's root URL, or null if the URL doesn't start with it.
     */
    private static function pathBelowRootUrl(string $url): ?string
    {
        // Pages record their URL with the host the request used, which can differ from APP_URL's.
        $appPath = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
        foreach ([url('/'), request()->getSchemeAndHttpHost() . $appPath] as $root) {
            $rest = substr($url, strlen($root));
            if (str_starts_with($url, $root) && ($rest === '' || in_array($rest[0], ['/', '?', '#'], true))) {
                return $rest;
            }
        }
        return null;
    }

    /**
     * Remember where to send the user once they log in.
     */
    public static function setIntendedUrl(Request $request): void
    {
        // A form submission can't be repeated after logging in, so return to the page it came from instead.  That
        // comes from the Referer header, which another site controls when it submits the form.
        $url = $request->isMethod('GET') ? $request->fullUrl() : (string) $request->headers->get('referer');

        if (self::isLocalUrl($url)) {
            session(['url.intended' => $url]);
        } else {
            session()->forget('url.intended');
        }
    }

    /**
     * Remove the remembered URL from the session and return it, or return $default if it points to another site.
     */
    public static function consumeIntendedUrl(string $default): string
    {
        $url = session()->pull('url.intended');
        return is_string($url) && self::isLocalUrl($url) ? $url : $default;
    }
}
