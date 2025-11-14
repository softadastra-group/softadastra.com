<?php

namespace Modules\User\Core\Auth;

use Ivi\Core\Jwt\JWT;
use Modules\User\Core\Helpers\UserHelper;
use Modules\User\Core\Models\User;
use Modules\User\Core\Repositories\UserRepository;

class AuthUser
{
    private JWT $jwt;
    private ?string $token;

    public function __construct(?string $token = null, ?JWT $jwt = null)
    {
        $this->jwt = $jwt ?? new JWT();
        $this->token = $token ?? $this->extractTokenFromRequest();
    }

    public function getPayload(): ?array
    {
        if (!$this->token) return null;

        try {
            $this->jwt->check($this->token, ['key' => env('JWT_SECRET')]);
            return $this->jwt->getPayload($this->token);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getUser(): ?User
    {
        $payload = $this->getPayload();
        if (!$payload || empty($payload['sub'])) return null;

        try {
            $repo = new UserRepository();
            $user = $repo->findById((int)$payload['sub']);
            return ($user && $user->getId() === (int)$payload['sub']) ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractTokenFromRequest(): ?string
    {
        $token = $_COOKIE['token'] ?? null;
        if (!$token) {
            $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? null;
            if ($hdr && preg_match('/Bearer\s+(\S+)/i', $hdr, $m)) $token = $m[1];
        }
        return $token;
    }

    // -----------------------------
    // Méthodes statiques helper
    // -----------------------------
    public static function login(User $user, int $validity = 604800): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        session_regenerate_id(true);

        $_SESSION['unique_id']  = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['roles']      = $user->getRoleNames() ?? [];

        $jwt = new JWT();
        $token = UserHelper::generateJwt($user, $validity);
        $user->setAccessToken($token);

        setcookie('token', $token, [
            'expires'  => time() + $validity,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return $token;
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        setcookie('token', '', time() - 3600, '/');
    }

    /**
     * Vérifie si le token est expiré.
     */
    public function isExpired(string $token): bool
    {
        try {
            $payload = $this->getPayload($token);
            if (!isset($payload['exp'])) return false;
            $now = new \DateTime();
            return $payload['exp'] < $now->getTimestamp();
        } catch (\Throwable $e) {
            return true; // si token invalide, on le considère comme expiré
        }
    }

    /**
     * Retourne l’utilisateur connecté via JWT (ou null)
     */
    public static function user(?string $token = null, ?UserRepository $repo = null): ?User
    {
        $token ??= $_COOKIE['token'] ?? null;
        if (!$token || !$repo) return null;

        try {
            $jwt = new JWT();
            $payload = $jwt->getPayload($token);
            if (!isset($payload['sub'])) return null;

            return $repo->findById((int)$payload['sub']);
        } catch (\Throwable) {
            return null;
        }
    }
}
