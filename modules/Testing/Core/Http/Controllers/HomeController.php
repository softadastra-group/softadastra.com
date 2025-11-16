<?php
namespace Modules\Testing\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

class HomeController extends Controller
{
    public function index(): HtmlResponse
    {
        // Titre de la page
        $title = (string) (cfg(strtolower('Testing') . '.title', 'Softadastra Testing') ?: 'Softadastra Testing');
        $this->setPageTitle($title);

        // Message pour la vue
        $message = "Hello from TestingController!";

        // 🔹 Correct: module_asset avec Core et tag HTML généré automatiquement
        $styles  = module_asset('Testing/Core', 'assets/css/style.css');
        $scripts = module_asset('Testing/Core', 'assets/js/script.js');

        return $this->view(strtolower('Testing') . '::home', [
            'title'   => $title,
            'message' => $message,
            'styles'  => $styles,
            'scripts' => $scripts,
        ]);
    }
}