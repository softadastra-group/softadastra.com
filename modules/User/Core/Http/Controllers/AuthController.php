<?php

declare(strict_types=1);

namespace Modules\User\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

class AuthController extends Controller
{
    public function index(): HtmlResponse
    {
        $title = (string) (cfg(strtolower('User') . '.title', 'Softadastra User') ?: 'Softadastra User');
        $this->setPageTitle($title);

        $styles  = '<link rel="stylesheet" href="' . asset("assets/css/login.css") . '">';
        $scripts = '<script src="' . asset("assets/js/login.js") . '" defer></script>';

        return $this->view(strtolower('User') . '::login', [
            'title'   => $title,
            'styles'  => $styles,
            'scripts' => $scripts,
        ]);
    }
}
