<?php

declare(strict_types=1);

namespace Modules\Auth\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\JsonResponse;
use Ivi\Http\HtmlResponse;
use Ivi\Core\Services\GoogleService;
use Modules\Auth\Core\Services\UserService;
use Exception;
use Ivi\Core\Validation\ValidationException;
use Ivi\Http\Request;

class AuthController extends Controller
{
    private GoogleService $google;
    private UserService $users;

    public function __construct()
    {
        // GOOGLE CONFIG
        $config = config_value('google');

        if (!$config || !is_array($config)) {
            throw new \RuntimeException(
                "Google configuration not found. Ensure config/google.php exists and returns an array."
            );
        }

        $this->google = new GoogleService($config);

        // 💡 Injection UserRepository → UserService
        $this->users = make(UserService::class);
    }

    // ---------------------------------------------------
    // GET PAGES
    // ---------------------------------------------------

    public function home(): HtmlResponse
    {
        $title = (string) cfg('user.title', 'Login');
        $this->setPageTitle($title);

        $styles = module_asset('Auth/Core', 'assets/css/home.css');

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
        $title = (string) cfg('user.title', 'Register');
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

    // ---------------------------------------------------
    // POST REGISTER (compatible avec ton test !)
    // ---------------------------------------------------

    public function handleRegistration(Request $request): JsonResponse
    {
        try {
            // On récupère toutes les sources (POST + JSON)
            $data = $request->all();

            $fullname = (string) ($data['fullname'] ?? '');
            $email    = (string) ($data['email'] ?? '');
            $password = (string) ($data['password'] ?? '');
            $phone    = (string) ($data['phone'] ?? '');

            // Appel à la logique métier
            $result = $this->users->register($fullname, $email, $password, $phone);

            // Si register() renvoie des erreurs, on retourne 422
            if (!empty($result['errors'])) {
                return new JsonResponse([
                    'message' => 'Invalid data.',
                    'errors'  => $result['errors'],
                ], 422);
            }

            // Succès
            return new JsonResponse($result, 201);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'message' => 'Invalid data.',
                'errors'  => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Registration failed.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function showSyncPage(): HtmlResponse
    {
        $title = (string) cfg('user.title', 'Login');
        $this->setPageTitle($title);

        $styles = module_asset('Auth/Core', 'assets/css/home.css');

        return $this->view('auth::sync', [
            'title'     => $title,
            'styles'    => $styles
        ]);
    }
}
