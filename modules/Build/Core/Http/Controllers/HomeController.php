<?php
namespace Modules\Build\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

class HomeController extends Controller
{
    public function index(): HtmlResponse
    {
        $title = (string) (cfg(strtolower('Build') . '.title', 'Softadastra Build') ?: 'Softadastra Build');
        $this->setPageTitle($title);

        $message = "Hello from BuildController!";
        $styles  = '<link rel="stylesheet" href="' . asset("assets/css/style.css") . '">';
        $scripts = '<script src="' . asset("assets/js/script.js") . '" defer></script>';

        return $this->view(strtolower('Build') . '::home', [
            'title'   => $title,
            'message' => $message,
            'styles'  => $styles,
            'scripts' => $scripts,
        ]);
    }
}