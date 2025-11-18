<?php

declare(strict_types=1);

namespace Modules\Auth\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\JsonResponse;
use Ivi\Http\HtmlResponse;
use Ivi\Core\Services\GoogleService;
use Ivi\Core\Validation\ValidationException;
use Modules\Auth\Core\Services\UserService;
use Ivi\Http\Request;
use Modules\Auth\Core\Helpers\AuthUser;
use Modules\Auth\Core\Helpers\UserHelper;

class AuthController extends Controller
{
    private UserService $users;
    private GoogleService $google;

    public function __construct()
    {
        // Google config
        $config = config_value('google');
        if (!$config || !is_array($config)) {
            throw new \RuntimeException(
                "Google configuration not found. Ensure config/google.php exists and returns an array."
            );
        }

        $this->google = new GoogleService($config);

        // Inject UserService (qui contient le wrapper vers UserRegistrationService)
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
        $data = $request->all();

        $fullname = (string)($data['fullname'] ?? '');
        $email    = (string)($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');
        $phone    = (string)($data['phone'] ?? '');

        $result = $this->users->register($fullname, $email, $password, $phone);

        // Si aucune erreur → status 201, sinon 422
        $status = empty($result['errors']) ? 201 : 422;

        // ⚠ Important : s'assurer que "errors" est toujours un array
        if (!isset($result['errors']) || $result['errors'] === null) {
            $result['errors'] = [];
        }

        return new JsonResponse($result, $status);
    }

    // ---------------------------------------------------
    // POST LOGIN
    // ---------------------------------------------------
    public function handleLogin(Request $request): JsonResponse
    {
        $data = $request->all();
        $email    = (string)($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');
        $next     = (string)($data['next'] ?? '/');

        try {
            if (!$email || !$password) {
                return new JsonResponse([
                    'success' => false,
                    'errors'  => ['email' => 'Email and password are required.'],
                    'message' => 'Invalid login data.',
                    'redirect' => $next,
                ], 422);
            }

            $user = $this->users->findByEmail($email);

            if (!$user || !UserHelper::verifyPassword($password, $user->getPassword())) {
                return new JsonResponse([
                    'success' => false,
                    'errors'  => ['email' => 'Invalid email or password.'],
                    'message' => 'Login failed.',
                    'redirect' => $next,
                ], 401);
            }

            // Génère le token
            $token = $this->users->loginUser($user);

            // Crée session + cookie
            AuthUser::setSessionAndCookie($user, $token);

            // Réponse finale avec succès
            $response = [
                'success'  => true,
                'token'    => $token,
                'user'     => [
                    'id'       => $user->getId(),
                    'email'    => (string)$user->getEmail(),
                    'username' => $user->getUsername(),
                    'roles'    => array_map(fn($r) => $r->getName(), $user->getRoles()),
                ],
                'message'  => 'Login successful.',
                'errors'   => [],
                'redirect' => $next,
            ];

            return new JsonResponse($response, 200);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'errors'  => ['exception' => $e->getMessage()],
                'message' => 'Login error.',
                'redirect' => $next,
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
