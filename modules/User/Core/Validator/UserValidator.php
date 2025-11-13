<?php

namespace Modules\User\Core\Validator;

use Modules\User\Core\Models\User;

class UserValidator
{
    static function validate(User $user)
    {
        $errors = [];

        if ($error = self::validateEmail($user->getEmail())) {
            $errors['email'] = $error;
        }
        if ($error = self::validatePassword($user->getPassword())) {
            $errors['password'] = $error;
        }
        if ($error = self::validateFullname($user->getFullname())) {
            $errors['fullname'] = $error;
        }
        if ($error = self::validateRole($user->getRole())) {
            $errors['role'] = $error;
        }
        if ($error = self::validateStatus($user->getStatus())) {
            $errors['status'] = $error;
        }
        if ($error = self::validateVerifiedEmail($user->getVerifiedEmail())) {
            $errors['verified_email'] = $error;
        }
        if ($error = self::validatePhoto($user->getPhoto())) {
            $errors['photo'] = $error;
        }

        if ($error = self::validateCover($user->getCoverPhoto())) {
            $errors['cover'] = $error;
        }

        if ($error = self::validatePhoneNumber($user->getPhone())) {
            $errors['phone'] = $error;
        }

        return $errors;
    }

    static function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format";
        }
        return null;
    }

    public static function validatePassword($password)
    {
        $password = (string)$password;

        $ok =
            self::isValidLength($password) &&
            self::hasUpperAndLowerCase($password) &&
            self::hasDigits($password) &&
            self::hasSpecialCharacters($password) &&
            !self::containsSpaces($password);

        if ($password === '') {
            return "The password cannot be empty.";
        }

        if (!$ok) {
            // ✅ Un seul message clair, complet
            return "Password must be 8–20 characters, include uppercase and lowercase letters, at least one digit, at least one special character (e.g., @, #, $), and contain no spaces.";
        }

        return null;
    }

    private static function isValidLength($password)
    {
        return strlen($password) >= 8 && strlen($password) <= 20;
    }

    private static function hasUpperAndLowerCase($password)
    {
        return preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password);
    }

    private static function hasDigits($password)
    {
        return preg_match('/\d/', $password);
    }

    private static function hasSpecialCharacters($password)
    {
        return preg_match('/[!@#$%^&*(),.?":{}|<>+\-=_\[\]\\;\'\/~€£¥°]/', $password);
    }

    private static function containsSpaces($password)
    {
        return strpos($password, ' ') !== false;
    }

    public static function validateFullname($fullname)
    {
        // Vérifie si le nom est vide ou composé uniquement d'espaces
        if (empty(trim($fullname))) {
            return "The full name cannot be empty or consist only of spaces.";
        }

        // Nettoie les espaces inutiles
        $cleanedFullname = preg_replace('/\s+/', ' ', trim($fullname));

        // Vérifie les caractères valides (lettres, apostrophes, tirets, espaces)
        if (!preg_match("/^[\p{L} '-]+$/u", $cleanedFullname)) {
            return "The full name contains invalid characters.";
        }

        // Sépare le nom en parties
        $parts = explode(' ', $cleanedFullname);

        // Si le nom a plus de deux parties, on n'accepte que les deux premiers mots
        if (count($parts) < 2) {
            return "The full name must include a first name and a last name.";
        }

        // Garde seulement les deux premiers mots comme prénom et nom
        $firstName = implode(' ', array_slice($parts, 0, 2));

        // Vérifie si le prénom et nom sont séparés en deux parties
        $parts = explode(' ', $firstName);
        if (count($parts) !== 2) {
            return "The full name must include exactly a first name and a last name.";
        }

        return null; // Aucun problème, le nom est valide
    }

    public static function validateBio($bio)
    {
        if (empty($bio)) {
            return "Bio is required.";
        }

        if (strlen($bio) < 10) {
            return "The bio must be at least 10 characters long.";
        }

        if (strlen($bio) > 245) {
            return "The bio must not exceed 245 characters.";
        }
        return null;
    }

    static function validateRole($role)
    {
        if (!in_array($role, ['admin', 'user'])) {
            return "Invalid role.";
        }
        return null;
    }

    static function validateStatus($status)
    {
        if (!in_array($status, ['active', 'inactive'])) {
            return "Invalid status";
        }
        return null;
    }

    static function validateVerifiedEmail($verified_email)
    {
        if (!is_int($verified_email)) {
            return "Invalid verified_email";
        }
        return null;
    }

    static function validateAccessToken($accessToken)
    {
        if (strlen($accessToken) < 3) {
            return "Access token must be at least 3 characters long";
        }
        return null;
    }

    static function validateRefreshToken($refreshToken)
    {
        if (strlen($refreshToken) < 3) {
            return "Refresh token must be at least 3 characters long";
        }
        return null;
    }

    static function validateId($id)
    {
        if (!is_int($id)) {
            return "Invalid id";
        }
        return null;
    }

    static function validatePhoto($photo)
    {
        if (strlen($photo) < 3) {
            return "Photo must be at least 3 characters long";
        }
        return null;
    }

    static function validateCover($cover)
    {
        if (strlen($cover) < 3) {
            return "Cover must be at least 3 characters long";
        }
        return null;
    }
    public static function validatePhoneNumber($phoneNumber)
    {
        if (empty($phoneNumber)) {
            return "Phone number is required.";
        }
        $cleanedPhoneNumber = self::removeSpaces($phoneNumber);
        if (!self::isValidFormat($cleanedPhoneNumber)) {
            return "The phone number is invalid. It must start with a '+' followed by the country code and contain between 9 and 15 digits.";
        }
        if (!self::isValidNumber($cleanedPhoneNumber)) {
            return "The phone number should only contain digits after the '+' sign.";
        }

        return null;
    }
    private static function removeSpaces($phoneNumber)
    {
        return str_replace(' ', '', $phoneNumber);
    }

    private static function isValidFormat($phoneNumber)
    {
        return preg_match("/^\+(?P<country_code>\d{1,4})(?P<number>\d{9,15})$/", $phoneNumber);
    }

    private static function isValidNumber($phoneNumber)
    {
        return preg_match("/^\+\d+$/", $phoneNumber);
    }

    public static function validateShippingAddress($bio)
    {
        if (empty($bio)) {
            return "Shipping address is required.";
        }

        if (strlen($bio) > 245) {
            return "Shipping address must not exceed 245 characters.";
        }

        return null;
    }
}
