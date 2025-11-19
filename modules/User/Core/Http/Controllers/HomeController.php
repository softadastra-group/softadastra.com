<?php

namespace Modules\User\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;
use Modules\Auth\Core\Helpers\AuthUser;

class HomeController extends Controller
{
    public function index(): HtmlResponse
    {
        // Titre de la page
        $title = (string)(cfg(strtolower('User') . '.title', 'Softadastra User') ?: 'Softadastra User');
        $this->setPageTitle($title);

        $auth = new AuthUser();
        $user = $auth->getUser();

        $userData = null;
        $message  = "Hello guest!";

        if ($user) {
            $userData = [
                'id'       => $user->getId(),
                'email'    => $user->getEmail(),
                'username' => $user->getUsername(),
                'roles'    => $user->getRoleNames(),
            ];
            $message = "Hello " . htmlspecialchars($user->getUsername()) . "!";
        } else {
            // header('Location: /auth/login'); exit;
        }

        // 🔹 Assets
        $styles  = module_asset('User/Core', 'assets/css/style.css');
        $scripts = module_asset('User/Core', 'assets/js/script.js');

        return $this->view(strtolower('User') . '::home', [
            'title'    => $title,
            'message'  => $message,
            'user'     => $userData,
            'styles'   => $styles,
            'scripts'  => $scripts,
        ]);
    }
}
