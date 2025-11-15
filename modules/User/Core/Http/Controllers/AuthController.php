<?php

declare(strict_types=1);

namespace Modules\User\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Core\Services\GoogleService;
use Ivi\Http\HtmlResponse;

class AuthController extends Controller
{
    private GoogleService $google;

    public function __construct()
    {
        $config = config('google');
        $this->google = new GoogleService($config);
    }

    public function index(): HtmlResponse
    {
        $title = (string) (cfg('user.title', 'Softadastra User') ?: 'Softadastra User');
        $this->setPageTitle($title);

        // 🔹 Utiliser module_asset avec $tag=true
        $styles  = module_asset('User/Core', 'assets/css/login.css');
        $scripts = module_asset('User/Core', 'assets/js/login.js');

        return $this->view('user::login', [
            'title'   => $title,
            'styles'  => $styles,
            'scripts' => $scripts,
        ]);
    }

    public function showLoginForm(): HtmlResponse
    {
        $title = (string) cfg('user.title', 'Login');
        $this->setPageTitle($title);

        $styles = module_asset('User/Core', 'assets/css/login-email.css');
        $scripts = module_asset('User/Core', 'assets/js/login-email');

        return $this->view('user::auth.email', [
            'title' => $title,
            'styles' => $styles,
            'scripts' => $scripts,
            'googleUrl' => $this->google->loginUrl()
        ]);
    }
}
