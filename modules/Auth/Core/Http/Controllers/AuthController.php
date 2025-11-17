<?php

declare(strict_types=1);

namespace Modules\Auth\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Core\Services\GoogleService;
use Ivi\Http\HtmlResponse;
use Exception;

class AuthController extends Controller
{
    private GoogleService $google;

    public function __construct()
    {
        $config = config_value('google');

        if (!$config || !is_array($config)) {
            throw new \RuntimeException(
                "Google configuration not found. " .
                    "Ensure config/google.php exists and returns an array."
            );
        }

        $this->google = new GoogleService($config);
    }

    public function home(): HtmlResponse
    {
        $title = (string) cfg('user.title', 'Login');
        $this->setPageTitle($title);

        $styles  = module_asset('Auth/Core', 'assets/css/home.css');

        return $this->view('auth::home', [
            'title'     => $title,
            'styles'    => $styles,
            'googleUrl' => $this->google->loginUrl(),
        ]);
    }

    public function showLoginForm(): HtmlResponse
    {
        $title = (string) cfg('user.title', 'Login');
        $this->setPageTitle($title);

        $styles  = module_asset('Auth/Core', 'assets/css/login.css');
        $scripts = module_asset('Auth/Core', 'assets/js/login.js');

        return $this->view('auth::login', [
            'title'     => $title,
            'styles'    => $styles,
            'scripts'   => $scripts,
            'googleUrl' => $this->google->loginUrl(),
        ]);
    }

    public function showRegistrationForm(): HtmlResponse
    {
        $title = (string) cfg('user.title', 'Login');
        $this->setPageTitle($title);

        $styles  = module_asset('Auth/Core', 'assets/css/register.css');
        $scripts = module_asset('Auth/Core', 'assets/js/register.js');

        return $this->view('auth::register', [
            'title'     => $title,
            'styles'    => $styles,
            'scripts'   => $scripts,
            'googleUrl' => $this->google->loginUrl(),
        ]);
    }
}
