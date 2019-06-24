<?php
namespace App\Providers;

use CDash\Middleware\OAuth2\OAuth2Interface;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class OAuthServiceProvider
 * @package App\Providers
 */
class OAuthServiceProvider extends ServiceProvider
{
    /**
     * Ensure that provider configuration is complete. (This must be done at boot time in order
     * to throw the NotFoundHttpException)
     *
     * @throws Exception
     */
    public function boot()
    {
        if ($this->isOAuthRequest()) {
            $provider = $this->getProviderName();
            $settings = $this->getProviderSettings($provider);
            $exception = null;
            $message = '';

            if (!$settings) {
                $message = "OAuth2 provider, {$provider}, is not configured";
                $exception = new NotFoundHttpException($message);
            }

            if ($settings['enable'] === false) {
                $message = "OAuth2 provider, {$provider}, is not enabled";
                $exception = new Exception($message);
            }

            // all settings are required, if any are empty throw error
            foreach ($settings as $key => $setting) {
                if (empty($setting)) {
                    $message = "OAuth2 {$provider} configuration setting, {$key}, must have a value";
                    $exception = new Exception($message);
                    break;
                }
            }

            if ($exception) {
                Log::alert($message);
                throw $exception;
            }
        }
    }

    /**
     * This registers the correct oauth2 service provider for a given route.
     *
     * @return void
     * @throws Exception
     */
    public function register()
    {
        if ($this->isOAuthRequest()) {
            $provider = $this->getProviderName();
            $settings = $this->getProviderSettings($provider);

            $this->app->bind(OAuth2Interface::class, $provider);
            $this->app->bind($provider, $settings['className']);
        }
    }

    /**
     * @return bool
     */
    private function isOAuthRequest()
    {
        $path = request()->path();
        return Str::is('oauth/*', $path);
    }

    /**
     * @return string
     */
    private function getProviderName()
    {
        $path = explode('/', request()->path());
        return array_pop($path);
    }

    /**
     * @param $provider
     * @return Repository
     */
    private function getProviderSettings($provider)
    {
        return config("oauth2.{$provider}");
    }
}
