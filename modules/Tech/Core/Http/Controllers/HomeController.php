<?php
namespace Modules\Tech\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

class HomeController extends Controller
{
    public function index(): HtmlResponse
    {
        $title = (string) (cfg(strtolower('Tech') . '.title', 'Softadastra Tech') ?: 'Softadastra Tech');
        $this->setPageTitle($title);

        $message = "Hello from TechController!";
        $styles  = '<link rel="stylesheet" href="' . asset("assets/css/style.css") . '">';
        $scripts = '<script src="' . asset("assets/js/script.js") . '" defer></script>';

        return $this->view(strtolower('Tech') . '::home', [
            'title'   => $title,
            'message' => $message,
            'styles'  => $styles,
            'scripts' => $scripts,
        ]);
    }
}