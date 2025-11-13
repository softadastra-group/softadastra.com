<?php

namespace Modules\User\Core\Utils;

class JsonResponse
{
    /**
     * Émission JSON sûre et uniforme.
     * - Ne supprime pas tous les headers (évite de casser Set-Cookie).
     * - Pose Content-Type + Cache-Control.
     * - Gère l’erreur d’encodage JSON proprement.
     */
    private static function sendJson(array $payload, int $statusCode = 200): void
    {
        // En-têtes standards
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        http_response_code($statusCode);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            // Échec d'encodage : retourne une erreur minimale
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Erreur JSON : ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo $json;
        }
        exit;
    }

    /**
     * Succès générique avec message (+ redirection optionnelle).
     * Code par défaut 201 pour mimer ton comportement initial.
     */
    public static function handleSuccess(string $message, ?string $redirectUrl = null, int $statusCode = 201): void
    {
        $response = ['success' => true, 'message' => $message];
        if ($redirectUrl) {
            $response['redirect'] = $redirectUrl;
        }
        self::sendJson($response, $statusCode);
    }

    /**
     * Erreur générique (corrigée : applique bien le status code + headers).
     * $details peut contenir un tableau d’erreurs (validation, etc.).
     */
    public static function handleError($message, int $statusCode = 400, array $details = []): void
    {
        $payload = [
            'success' => false,
            'error'   => is_array($message) ? 'Validation error' : (string)$message,
        ];
        if (is_array($message)) {
            // Compat: si on passe directement un tableau d’erreurs en $message
            $payload['errors'] = $message;
        }
        if (!empty($details)) {
            $payload['details'] = $details;
        }
        self::sendJson($payload, $statusCode);
    }

    /** 200 OK */
    public static function ok(array|string $data = [], string $message = 'Opération réussie.'): void
    {
        // Si le 1er param est une string, on la considère comme message
        if (is_string($data)) {
            $message = $data;
            $data = [];
        }

        $response = ['success' => true, 'message' => $message];
        if (!empty($data)) {
            $response['data'] = $data;
        }
        self::sendJson($response, 200);
    }

    /** 201 Created */
    public static function created(array $data = [], string $message = 'Ressource créée.'): void
    {
        $response = ['success' => true, 'message' => $message];
        if (!empty($data)) {
            $response['data'] = $data;
        }
        self::sendJson($response, 201);
    }

    /** 204 No Content (utile pour certaines actions sans payload) */
    public static function noContent(): void
    {
        http_response_code(204);
        // Aucun body pour 204
        exit;
    }

    /** 400 Bad Request */
    public static function badRequest(string $message = 'Requête invalide.', array $errors = []): void
    {
        self::sendJson(['success' => false, 'error' => $message, 'errors' => $errors], 400);
    }

    /** 401 Unauthorized */
    public static function unauthorized(string $message = 'Non autorisé. Veuillez vous connecter.'): void
    {
        self::sendJson(['success' => false, 'error' => $message], 401);
    }

    /** 403 Forbidden */
    public static function forbidden(string $message = 'Accès refusé.'): void
    {
        self::sendJson(['success' => false, 'error' => $message], 403);
    }

    /** 404 Not Found */
    public static function notFound(string $message = 'Ressource introuvable.'): void
    {
        self::sendJson(['success' => false, 'error' => $message], 404);
    }

    /** 409 Conflict */
    public static function conflict(string $message = 'Conflit de ressource.'): void
    {
        self::sendJson(['success' => false, 'error' => $message], 409);
    }

    /** 422 Unprocessable Entity (validation) */
    public static function validationError(array $errors): void
    {
        self::sendJson(['success' => false, 'errors' => $errors], 422);
    }

    /** 500 Internal Server Error */
    public static function serverError(string $message = 'Erreur interne du serveur.', array $details = []): void
    {
        self::sendJson(['success' => false, 'error' => $message, 'details' => $details], 500);
    }

    public static function json(array $payload, int $statusCode = 200): void
    {
        self::sendJson($payload, $statusCode);
    }

    public static function success(array $payload = [], int $statusCode = 200): void
    {
        // garantit success=true au root
        $body = array_merge(['success' => true], $payload);
        self::sendJson($body, $statusCode);
    }
}
