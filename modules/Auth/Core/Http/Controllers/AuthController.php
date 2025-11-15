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
        $config = config('google');
        if (!is_array($config)) {
            throw new \RuntimeException('Google configuration not found. Make sure config/google.php exists and returns an array.');
        }

        $this->google = new GoogleService($config);
    }

    /**
     * Return Google OAuth login URL as JSON
     */
    public function getGoogleLoginUrl(): void
    {
        try {
            $authUrl = $this->google->loginUrl();
            echo json_encode(['url' => $authUrl]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Impossible to generate login URL']);
        }

        exit;
    }

    // Exemple pour afficher le formulaire de login avec Google URL
    public function showLoginForm(): HtmlResponse
    {
        $title = (string) cfg('user.title', 'Login');
        $this->setPageTitle($title);

        $styles  = module_asset('Auth/Core', 'assets/css/login-email.css');
        $scripts = module_asset('Auth/Core', 'assets/js/login-email');

        return $this->view('auth::email', [
            'title'     => $title,
            'styles'    => $styles,
            'scripts'   => $scripts,
            'googleUrl' => $this->google->loginUrl(),
        ]);
    }
}
