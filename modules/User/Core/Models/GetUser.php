<?php

namespace Modules\User\Core\Models;

use Exception;
use Modules\User\Core\Repository\UserRepository;

class GetUser
{
    private $jwt;
    private $token;

    public function __construct()
    {
        $this->jwt = new JWT();
        $this->token = $_COOKIE['token'] ?? null;

        if (!$this->token) {
            $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? null;
            if ($hdr && preg_match('/Bearer\s+(\S+)/i', $hdr, $m)) {
                $this->token = $m[1];
            }
        }
    }

    public function validateToken()
    {
        if (isset($this->token) && $this->jwt->isValid($this->token) && !$this->jwt->isExpired($this->token) && $this->jwt->check($this->token, SECRET)) {
            return $this->jwt->getPayload($this->token);
        }
        return null;
    }

    public function getUserEntity()
    {
        $payload = $this->validateToken();
        if (!$payload) return null;

        try {
            $userRepository = new UserRepository();
            $user = $userRepository->findById($payload['id']);
            if ($user && $payload['id'] == $user->getId()) {
                return $user;
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }
}
