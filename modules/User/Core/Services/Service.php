<?php

namespace Modules\User\Core\Services;

use Exception;
use Ivi\Http\Response;
use Modules\User\Core\Image\PhotoHandler;
use Modules\User\Core\Models\JWT;
use Modules\User\Core\Repository\UserRepository;

abstract class Service
{
    /** @var JWT */
    private $jwt;

    /** @var string|null */
    private $token;

    public function __construct()
    {
        $this->jwt = new JWT();

        // 1) Cookie d’abord (si tu gardes ce contrat)
        $cookieToken = $_COOKIE['token'] ?? $_COOKIE['jwt'] ?? null;

        // 2) Sinon, tente le header Authorization: Bearer <token>
        $bearer = $this->getBearerToken();

        // 3) Choisis la source (cookie prioritaire, sinon bearer)
        $this->token = $cookieToken ?: $bearer;
    }

    /**
     * Détecte si l’appel attend du JSON (API/XHR)
     */
    protected function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return (stripos($accept, 'application/json') !== false) || ($xhr === 'XMLHttpRequest');
    }

    /**
     * Récupère le token Bearer depuis le header Authorization
     */
    protected function getBearerToken(): ?string
    {
        // getallheaders peut ne pas exister selon SAPI
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        $auth = $headers['Authorization']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($auth && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        return null;
    }

    /**
     * Valide un JWT (optionnellement un token brut passé en paramètre)
     * Retourne le payload (array) ou null si invalide/expiré/absent
     */
    public function validateToken(?string $rawToken = null): ?array
    {
        try {
            $tok = $rawToken ?: $this->token;
            if (!$tok) {
                return null;
            }

            // Ton contrat JWT existant
            if (
                $this->jwt->isValid($tok) &&
                !$this->jwt->isExpired($tok) &&
                $this->jwt->check($tok, SECRET)
            ) {
                return $this->jwt->getPayload($tok);
            }

            return null;
        } catch (\Throwable $e) {
            // Pas d'affichage en prod, log silencieux
            error_log('validateToken error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère l'entité utilisateur.
     * - $requireAuth = true (par défaut): bloque et répond (401 JSON ou redirect /login) si non authentifié.
     * - $requireAuth = false : renvoie null si non authentifié (utile sur endpoints publics).
     */
    public function getUserEntity(bool $requireAuth = true)
    {
        $payload = $this->validateToken();
        if ($payload && isset($payload['id'])) {
            $userRepository = new UserRepository();
            return $userRepository->findById((int)$payload['id']);
        }

        if ($requireAuth) {
            if ($this->wantsJson()) {
                // Réponse API claire
                Response::json(["error" => "You must be logged in."], 401);
            } else {
                // Flux page web
                Response::redirect('/login'); // 302
            }
        }

        return null;
    }

    public static function handleImages($files, $directory, $prefix = 'softadastra')
    {
        if (!isset($files['tmp_name']) || !is_array($files['tmp_name']) || empty(array_filter($files['tmp_name']))) {
            throw new Exception("You haven't selected any images to upload.");
        }

        if (count($files['tmp_name']) > 20) {
            throw new Exception("You can only upload up to 20 images.");
        }

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new Exception("Unable to create upload directory.");
            }
        }

        $uploadedImages = [];
        $errors = [];

        foreach ($files['tmp_name'] as $key => $tmp_name) {
            $fileName = $files['name'][$key] ?? 'Unknown file';

            try {
                if (empty($tmp_name) || $files['error'][$key] === UPLOAD_ERR_NO_FILE) {
                    throw new Exception("No file selected.");
                }

                if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                    throw new Exception("Upload error for file: $fileName");
                }

                $file = [
                    'name' => $fileName,
                    'type' => $files['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key]
                ];

                $uploadedImage = PhotoHandler::photo($file, $prefix, $directory);
                $uploadedImages[] = $uploadedImage;
            } catch (Exception $e) {
                $message = $e->getMessage();
                $decoded = json_decode($message, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
                    $errors[] = "File '$fileName': " . $decoded['message'];
                } else {
                    $errors[] = "File '$fileName': " . $message;
                }

                continue;
            }
        }

        if (!empty($errors)) {
            foreach ($uploadedImages as $image) {
                @unlink($directory . '/' . $image);
            }

            throw new Exception(implode("\n", $errors));
        }

        return $uploadedImages;
    }
}
