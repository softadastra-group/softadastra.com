<?php

namespace Modules\User\Core\Http\Controllers;

use App\Controllers\Controller;
use Modules\User\Core\Models\GetUser;
use Modules\User\Core\Models\JWT;
use Modules\User\Core\Repository\UserRepository;
use Modules\User\Core\Services\UserHelper;
use Modules\User\Core\Utils\FlashMessage;
use Modules\User\Core\Utils\RedirectionHelper;

class UserController extends Controller
{
    private $jwt;
    private $token;

    public function __construct()
    {
        $this->jwt = new JWT();
        $this->token = $_COOKIE['token'] ?? null;
    }

    public function legacyDashboard()
    {
        header('Location: /dashboard', true, 301);
        exit;
    }

    protected function validateToken()
    {
        if (isset($this->token) && $this->jwt->isValid($this->token) && !$this->jwt->isExpired($this->token) && $this->jwt->check($this->token, SECRET)) {
            return $this->jwt->getPayload($this->token);
        }
        return null;
    }

    protected function jsonUnauthorized(string $msg = 'Unauthorized'): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $msg]);
        exit;
    }

    protected function isAjaxUser(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function getUserEntity()
    {
        $payload = $this->validateToken();
        if ($payload) {
            $userRepository = new UserRepository();
            return $userRepository->findById((int)$payload['id']); // inclut role via JOIN
        }
        if ($this->isAjaxUser()) {
            $this->jsonUnauthorized('Authentication required');
        }
        RedirectionHelper::redirect('login');
        exit;
    }

    public function dashboard()
    {
        $userEntity = $this->getUserEntity();
        return $this->view('user::dashboard', [
            'title' => 'Dashboard',
            'user' => $userEntity
        ]);
    }

    public function getUserJson()
    {
        $getUser = new GetUser();
        $user = $getUser->getUserEntity();
        if ($user) {
            $userPhoto = $user->getPhoto();
            $profileImage = UserHelper::getProfileImage($userPhoto);

            $this->json([
                'id' => $user->getId(),
                'fullname' => $user->getFullname(),
                'email' => $user->getEmail(),
                'photo' => $profileImage,
                'status' => $user->getStatus(),
                'verified_email' => $user->getVerifiedEmail(),
                'messageCount' => $user->getMessageCount() ? $user->getMessageCount() : 0,
                'cover_photo' => $user->getCoverPhoto(),
                'created_at' => $user->getCreatedAt(),
                'bio' => $user->getBio(),
                'role' => $user->getRole(),
                'username' => $user->getUsername()
            ], 200);
        } else {
            $this->json([
                'user' => null,
                'message' => 'User not found or token is invalid. Please log in.'
            ], 200);
        }
    }

    public function getUserById($id)
    {
        $repo = new UserRepository();
        $user = $repo->findById($id);

        if ($user) {
            $userPhoto = $user->getPhoto();
            $profileImage = UserHelper::getProfileImage($userPhoto);

            return $this->json([
                'id'                 => $user->getId(),
                'fullname'           => $user->getFullname(),
                'email'              => $user->getEmail(),
                'photo'              => $profileImage,
                'status'             => $user->getStatus(),
                'verified_email'     => $user->getVerifiedEmail(),
                'messageCount'       => $user->getMessageCount() ?? 0,
                'cover_photo'        => $user->getCoverPhoto(),
                'created_at'         => $user->getCreatedAt(),
                'bio'                => $user->getBio(),
                'role'               => $user->getRole(),
                'username'           => $user->getUsername(),
            ], 200);
        }

        return $this->json([
            'user'    => null,
            'message' => "User with id {$id} not found."
        ], 404);
    }

    public function getProfile(string $slug)
    {
        // Normalisation: decode + retire '@'
        $slug = ltrim(urldecode($slug), '@');

        $userRepository = new UserRepository();
        $user = $userRepository->findByUsername($slug);

        if ($user) {
            $userPhoto      = $user->getPhoto();
            $profileImage   = UserHelper::getProfileImage($userPhoto);

            return $this->json([
                'id'               => $user->getId(),
                'fullname'         => $user->getFullname(),
                'email'            => $user->getEmail(),
                'photo'            => $profileImage,
                'status'           => $user->getStatus(),
                'verified_email'   => $user->getVerifiedEmail(),
                'messageCount'     => $user->getMessageCount() ?: 0,
                'cover_photo'      => $user->getCoverPhoto(),
                'created_at'       => $user->getCreatedAt(),
                'bio'              => $user->getBio(),
                'phone'            => $user->getPhone(),
                'city'             => $user->getCityName() ?? null,
                'country'          => $user->getCountryName() ?? null,
                'country_image'    => $user->getCountryImageUrl() ?? null,
            ], 200);
        }

        return $this->json(['error' => 'User not found'], 404);
    }

    public function flashAPI()
    {
        $messages = FlashMessage::get();
        $response = [
            'status' => 'success',
            'messages' => [
                'success' => [],
                'error' => []
            ]
        ];

        foreach ($messages as $message) {
            if ($message['type'] === 'success') {
                $response['messages']['success'][] = $message['message'];
            } elseif ($message['type'] === 'error') {
                $response['messages']['error'][] = $message['message'];
            }
        }
        $this->json($response, 200);
    }
}
