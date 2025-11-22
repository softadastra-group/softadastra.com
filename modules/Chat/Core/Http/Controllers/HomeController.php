<?php
namespace Modules\Chat\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

class HomeController extends Controller
{
    public function index(): HtmlResponse
    {
        // Titre de la page
        $title = (string) (cfg(strtolower('Chat') . '.title', 'Softadastra Chat') ?: 'Softadastra Chat');
        $this->setPageTitle($title);

        // Message pour la vue
        $message = "Hello from ChatController!";

        // 🔹 Correct: module_asset avec Core et tag HTML généré automatiquement
        $styles  = module_asset('Chat/Core', 'assets/css/style.css');
        $scripts = module_asset('Chat/Core', 'assets/js/script.js');

        return $this->view(strtolower('Chat') . '::home', [
            'title'   => $title,
            'message' => $message,
            'styles'  => $styles,
            'scripts' => $scripts,
        ]);
    }
}