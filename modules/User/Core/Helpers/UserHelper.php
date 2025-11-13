<?php

declare(strict_types=1);

namespace Modules\User\Core\Helpers;

use Ivi\Core\Jwt\JWT;
use Modules\User\Core\Repositories\UserRepository;

final class UserHelper
{
    private const PASSWORD_ALGO = PASSWORD_BCRYPT;
    private const PASSWORD_OPTIONS = [
        'cost' => 12, // Ajustable selon la sécurité et la performance
    ];

    /**
     * Hash un mot de passe pour stockage en base.
     */
    public static function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, self::PASSWORD_ALGO, self::PASSWORD_OPTIONS);
    }

    /**
     * Vérifie qu'un mot de passe correspond à son hash.
     */
    public static function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    /**
     * Détermine si le hash doit être rehashé (nouvel algo ou coût changé)
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::PASSWORD_ALGO, self::PASSWORD_OPTIONS);
    }

    /**
     * Génère un token aléatoire sécurisé pour reset de mot de passe ou sessions.
     */
    public static function generateToken(int $length = 64): string
    {
        return bin2hex(random_bytes(intdiv($length, 2)));
    }

    /**
     * Vérifie si un mot de passe respecte une politique minimale.
     * Par exemple : 8+ caractères, 1 chiffre, 1 majuscule, 1 minuscule
     */
    public static function validatePasswordPolicy(string $password): bool
    {
        if (strlen($password) < 8) return false;
        if (!preg_match('/[A-Z]/', $password)) return false;
        if (!preg_match('/[a-z]/', $password)) return false;
        if (!preg_match('/\d/', $password)) return false;
        return true;
    }

    static public function getProfileImage($userPhoto)
    {
        $defaultAvatar = '/public/images/profile/avatar.jpg';

        if (!empty($userPhoto)) {
            if (filter_var($userPhoto, FILTER_VALIDATE_URL)) {
                if (strpos($userPhoto, 'googleusercontent.com') !== false) {
                    $headers = @get_headers($userPhoto);
                    if ($headers && strpos($headers[0], '200') !== false) {
                        return $userPhoto;
                    }
                } else {
                    return $userPhoto;
                }
            } else {
                $localPath = $_SERVER['DOCUMENT_ROOT'] . '/public/images/profile/' . $userPhoto;
                if (file_exists($localPath)) {
                    return '/public/images/profile/' . $userPhoto;
                }
            }
        }

        return $defaultAvatar;
    }

    static function token($user, $validity)
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        $payload = [
            'id' => $user->getId(),
            'name' => $user->getFullName(),
            'email' => $user->getEmail(),
            'role' => $user->getRole()
        ];
        $jwt = new JWT();
        $token = $jwt->generate($header, $payload, JWT_SECRET, $validity);

        return $token;
    }

    static public function getPhoto()
    {
        return 'avatar.jpg';
    }

    static public function getRoleName(): string
    {
        return 'user';
    }
    static public function getRole(): string
    {
        return self::getRoleName();
    } // compat

    static public function getStatus()
    {
        return 'active';
    }

    static public function getVerifiedEmail()
    {
        return 0;
    }

    static public function getCoverPhoto()
    {
        return 'cover.jpg';
    }

    static public function getBio()
    {
        return 'This user has not added a bio yet.';
    }

    static public function getAdmin()
    {
        return 'admin';
    }

    static function while($errors)
    {
        foreach ($errors as $field => $error) {
            echo "Erreur sur le champ $field : $error<br>";
        }
    }

    static function generateCsrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    static function verifyCsrfToken($token)
    {
        if (empty($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
            throw new \Exception('Invalid CSRF token');
        }
    }

    static function getFlashMessage()
    {
        if (!empty($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }

    static function setFlashMessage($message)
    {
        $_SESSION['flash_message'] = $message;
    }

    static function getFlashError()
    {
        if (!empty($_SESSION['flash_error'])) {
            $error = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
            return $error;
        }
        return null;
    }

    static function setFlashError($error)
    {
        $_SESSION['flash_error'] = $error;
    }

    static function getPhotoPath($photo)
    {
        return $photo;
    }

    static function getCoverPhotoPath($photo)
    {
        return $photo;
    }

    static function getPhotoName($photo)
    {
        return basename($photo);
    }

    static function getCoverPhotoName($photo)
    {
        return basename($photo);
    }

    static function getPhotoExtension($photo)
    {
        return pathinfo($photo, PATHINFO_EXTENSION);
    }

    static function getCoverPhotoExtension($photo)
    {
        return pathinfo($photo, PATHINFO_EXTENSION);
    }

    static function getPhotoSize($photo)
    {
        return filesize($photo);
    }

    static function getCoverPhotoSize($photo)
    {
        return filesize($photo);
    }

    static function getPhotoType($photo)
    {
        return mime_content_type($photo);
    }

    static public function lastName($fullName)
    {
        $parts = explode(' ', $fullName);
        if (count($parts) > 1) {
            array_shift($parts);
            return implode(' ', $parts);
        }
        return '';
    }

    static public function formatFullName($fullName)
    {
        // Supprime les espaces inutiles et normalise
        $fullName = preg_replace('/\s+/', ' ', trim($fullName));

        // Sépare le nom en mots
        $parts = explode(' ', $fullName);

        // Si plus de 2 mots, on garde seulement les 2 premiers
        if (count($parts) > 2) {
            $parts = array_slice($parts, 0, 2);
        }

        // Met en capitales la première lettre de chaque mot
        $formatted = ucwords(strtolower(implode(' ', $parts)));

        return $formatted;
    }

    static public function formatUsername($username)
    {
        // Enlève tous les caractères non alphanumériques (garde juste lettres et chiffres)
        // Tu peux autoriser "_" ou "." en adaptant l'expression régulière
        $username = preg_replace('/[^a-z0-9]/', '', strtolower($username));
        return $username;
    }

    static public function generateUsername($fullName, UserRepository $userRepository)
    {
        // On récupère les deux premiers mots du nom
        $parts = preg_split('/\s+/', trim($fullName));
        $firstTwo = array_slice($parts, 0, 2);

        // Génère le nom d'utilisateur de base
        $usernameBase = strtolower(implode('', $firstTwo));

        // Formate comme les plateformes (lettres/chiffres uniquement)
        $username = self::formatUsername($usernameBase);

        $uniqueUsername = $username;
        $counter = 1;

        // Vérifie si le nom est déjà pris et ajoute un suffixe numérique si besoin
        while (self::isUsernameTaken($userRepository, $uniqueUsername)) {
            $uniqueUsername = $username . $counter;
            $counter++;
        }

        return $uniqueUsername;
    }

    static public function isUsernameTaken(UserRepository $userRepository, $username)
    {
        $user = $userRepository->findByUsername($username);
        return $user !== null;
    }
}
