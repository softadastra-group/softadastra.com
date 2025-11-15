<?php

namespace Modules\User\Core\Http\Controllers;

use App\Controllers\Controller;
use Exception;
use Google\Client;
use Ivi\Http\Response;
use Modules\User\Core\Models\GetUser;
use Modules\User\Core\Repository\UserRepository;
use Modules\User\Core\Services\UserService;
use Modules\User\Core\Utils\Adastra;
use Modules\User\Core\Utils\FlashMessage;
use Modules\User\Core\Utils\RedirectionHelper;

class AuthController extends Controller
{
    public function register()
    {
        Adastra::getCookie();

        return $this->view('user::register', [
            'title' => 'Register Page'
        ]);
    }

    public function postRegister()
    {
        try {
            if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                return $this->json(["success" => false, "error" => "Invalid CSRF token."], 400);
            }

            if (empty($_POST)) {
                return $this->json(["success" => false, "error" => "Please fill all fields."], 400);
            }

            $userRepository = new UserRepository();
            $userService    = new UserService($userRepository);

            $post         = $_POST;
            $fullname     = $post['fullname'] ?? '';
            $email        = $post['email'] ?? '';
            $password     = $post['password'] ?? '';
            $phone_number = $post['phone_number'] ?? ($post['phone'] ?? '');

            $phone_number = $this->normalizeE164($phone_number);

            $result = $userService->register($fullname, $email, $password, $phone_number);

            if (isset($result['success']) && $result['success'] === true) {
                return $this->json($result, $result['status'] ?? 201);
            }
            return $this->json($result, $result['status'] ?? 422);
        } catch (Exception $e) {
            return $this->json(["success" => false, "error" => "Server error."], 500);
        }
    }

    /** Normalise grossièrement vers E.164 pour UG/CD */
    private function normalizeE164(string $raw): string
    {
        $v = trim($raw);
        $v = preg_replace('/[^\d+]/', '', $v ?? '');

        if ($v === '') return $v;

        // Déjà +256 / +243 → nettoie
        if (strpos($v, '+256') === 0) {
            $d = preg_replace('/\D/', '', substr($v, 1));
            return '+256' . substr($d, 3, 9);
        }
        if (strpos($v, '+243') === 0) {
            $d = preg_replace('/\D/', '', substr($v, 1));
            return '+243' . substr($d, 3, 9);
        }

        // Uganda variantes
        if (strpos($v, '256') === 0) {
            $d = preg_replace('/\D/', '', $v);
            return '+256' . substr($d, 3, 9);
        }
        if (strpos($v, '07') === 0) {
            $d = preg_replace('/\D/', '', $v);
            return '+256' . substr($d, 1, 9);
        }
        if (preg_match('/^7\d{8,}$/', $v)) {
            $d = preg_replace('/\D/', '', $v);
            return '+256' . substr($d, 0, 9);
        }

        // DRC variantes
        if (strpos($v, '243') === 0) {
            $d = preg_replace('/\D/', '', $v);
            return '+243' . substr($d, 3, 9);
        }
        if (preg_match('/^0[89]\d+$/', $v)) {
            $d = preg_replace('/\D/', '', $v);
            return '+243' . substr($d, 1, 9);
        }
        if (preg_match('/^[89]\d+$/', $v)) {
            $d = preg_replace('/\D/', '', $v);
            return '+243' . substr($d, 0, 9);
        }

        // fallback: renvoie tel quel (le validator gèrera)
        return $v;
    }


    private function getGoogleClient()
    {
        $client = new Client();
        $client->setClientId(GOOGLE_CLIENT_ID);
        $client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $client->setRedirectUri('https://softadastra.com/loginPost');
        $client->addScope('email');
        $client->addScope('profile');

        return $client;
    }

    public function getGoogleLoginUrl()
    {
        try {
            $client = $this->getGoogleClient();
            $authUrl = $client->createAuthUrl();
            echo json_encode(['url' => $authUrl]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['error' => 'Impossible to generate login URL']);
            exit;
        }
    }

    public function login()
    {
        Adastra::getCookie();

        $client = $this->getGoogleClient();
        return $this->view('user::login', [
            'client' => $client,
            'title' => 'Login Page'
        ]);
    }

    public function loginEmail()
    {
        Adastra::getCookie();

        $client = $this->getGoogleClient();
        return $this->view('user::email', compact('client'));
    }

    public function redirectToGoogle()
    {
        $client = $this->getGoogleClient();
        $authUrl = $client->createAuthUrl();
        return RedirectionHelper::redirect($authUrl);
    }

    public function google($code = null, $scope = null, $authuser = null, $prompt = null)
    {
        try {

            if (!$code) {
                return Response::json(['error' => "Erreur : Missing authentication code."], 400);
            }

            $client = $this->getGoogleClient();

            $token = $client->fetchAccessTokenWithAuthCode($code);


            if (isset($token['error'])) {
                return Response::json(['error' => 'Error retrieving the token : ' . $token['error']], 400);
            }

            $client->setAccessToken($token);
            $oauth = new \Google\Service\Oauth2($client);
            $user = $oauth->userinfo->get();

            $userRepository = new UserRepository();
            $userService = new UserService($userRepository);
            $userService->google($user);
        } catch (Exception $e) {
            return Response::json(['error' => $e->getMessage()]);
        }
    }

    public function finalizeRegistration()
    {
        if (!isset($_SESSION['user_registration'])) {
            return RedirectionHelper::redirect("login");
        }
        return $this->view('user::finalize-register');
    }

    public function finalizeRegistrationPost()
    {
        $userRepository = new UserRepository();
        $userService = new UserService($userRepository);
        $userService->finalizeRegistrationPOST($_POST);
    }

    public function postLogin()
    {
        Adastra::getCookie();

        if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['errors_user_register'] = "Invalid CSRF token.";
            echo "Invalid CSRF token.";
            exit;
            return RedirectionHelper::redirect("register");
        }

        $userRepository = new UserRepository();
        $userService = new UserService($userRepository);
        if (empty($_POST)) {
            RedirectionHelper::redirect('login');
        }
        $userService->login($_POST['email'], $_POST['password']);
    }

    private function clearAuthCookies(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

        // Purge session + cookie PHPSESSID
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            @setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path']     ?? '/',
                'domain'   => $p['domain']   ?? '',
                'secure'   => !empty($p['secure']),
                'httponly' => !empty($p['httponly']),
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        @session_destroy();
        @session_write_close();

        // Efface le cookie "token" avec les mêmes attributs que lors de l’émission
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocal = (bool)preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host);

        $opts = [
            'expires'  => time() - 42000,
            'path'     => '/',
            'secure'   => $isLocal ? false : true,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (!$isLocal) {
            $opts['domain'] = '.softadastra.com';
        }

        // supprime "token" (et un éventuel alias "phpjwt")
        @setcookie('token',  '', $opts);
        @setcookie('phpjwt', '', $opts);
    }

    public function logout()
    {
        try {
            $payload = null;
            try {
                $payload = (new GetUser())->validateToken(); // retourne array|null
            } catch (\Throwable $e) {
                // ignore: le logout ne doit pas dépendre de ça
            }

            // Nettoyage côté serveur + cookies
            $this->clearAuthCookies();

            // Empêcher toute mise en cache
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            FlashMessage::add('success', 'You have been successfully logged out.');
            return RedirectionHelper::redirect('login');
        } catch (\Throwable $e) {
            return Response::json([
                'error' => $e->getMessage() . ' — Tip: supprime manuellement les cookies dans l’onglet Application si besoin.'
            ]);
        }
    }

    public function me()
    {
        header('Content-Type: application/json; charset=UTF-8');
        $payload = (new GetUser())->validateToken();
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'user' => null]);
            return;
        }
        echo json_encode(['ok' => true, 'user' => ['id' => $payload['id'] ?? null]]);
    }

    public function forgotPassword()
    {
        return $this->view('user::forgot_password');
    }

    public function AuthSync()
    {
        return $this->view('user::auth-sync');
    }
}
