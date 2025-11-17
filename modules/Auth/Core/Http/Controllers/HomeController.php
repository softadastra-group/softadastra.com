<?php

namespace Modules\Auth\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;
use Ivi\Core\Cache\Cache;

class HomeController extends Controller
{
    public function index(): HtmlResponse
    {
        // --- Récupération du singleton cache
        $cache = Cache::getInstance();

        // --- Définition de plusieurs clés
        $keys = [
            'home_message'    => "Hi! (generated at " . date('H:i:s') . " | home)",
            'welcome_message' => "Welcome! (generated at " . date('H:i:s') . " | welcome)",
            'footer_message'  => "Footer info (generated at " . date('H:i:s') . " | footer)",
        ];

        $messages = [];
        foreach ($keys as $key => $defaultValue) {
            $messages[$key] = $cache->remember($key, 3600, function () use ($defaultValue) {
                return $defaultValue;
            });

            // Ajouter un tag pour savoir si c'est depuis le cache
            if ($cache->get($key) !== null) {
                $messages[$key] .= " [from cache]";
            }
        }

        // --- Liste toutes les clés stockées
        $allKeys = $cache->listKeys();

        // --- Pour debug rapide : dump des clés et valeurs
        dd([
            'keys_in_cache' => $allKeys,
            'messages'      => $messages,
        ]);

        // --- Page title
        $title = (string) cfg('auth.title', 'Softadastra Auth');
        $this->setPageTitle($title);

        // --- Retour de la vue
        return $this->view(strtolower('Auth') . '::home', [
            'title'    => $title,
            'messages' => $messages,
        ]);
    }
}
