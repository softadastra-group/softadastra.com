<?php
namespace Modules\User\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

class HomeController extends Controller
{
    public function index(): HtmlResponse
    {
        // Titre de la page
        $title = (string) (cfg(strtolower('User') . '.title', 'Softadastra User') ?: 'Softadastra User');
        $this->setPageTitle($title);

        // Message pour la vue
        $message = "Hello from UserController!";

        // 🔹 Correct: module_asset avec Core et tag HTML généré automatiquement
        $styles  = module_asset('User/Core', 'assets/css/style.css');
        $scripts = module_asset('User/Core', 'assets/js/script.js');

        return $this->view(strtolower('User') . '::home', [
            'title'   => $title,
            'message' => $message,
            'styles'  => $styles,
            'scripts' => $scripts,
        ]);
    }
}