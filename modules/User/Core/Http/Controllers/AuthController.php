<?php

declare(strict_types=1);

namespace Modules\User\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

class AuthController extends Controller
{
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
}
