<?php

declare(strict_types=1);

namespace Modules\Auth\Core\Services;

use Ivi\Core\Utils\FlashMessage;
use Ivi\Http\JsonResponse;
use Modules\Auth\Core\Factories\UserFactory;
use Modules\Auth\Core\Helpers\AuthUser;
use Modules\Auth\Core\Helpers\UserHelper;
use Modules\Auth\Core\Repositories\UserRepository;
use Modules\Auth\Core\Validator\UserValidator;

class UserRegistrationService extends BaseService
{
    private AuthService $auth;

    public function __construct(
        UserRepository $repository,
        AuthService $auth
    ) {
        parent::__construct($repository);
        $this->auth = $auth;
    }

    public function register(string $fullname, string $email, string $password, string $phone): array
    {
        try {
            error_log("register() called with fullname='$fullname', email='$email', phone='$phone'");

            // ---- 1) Normalisation entrée
            $fullname = trim($fullname);
            $emailRaw = strtolower(trim($email));
            $passwordPlain = trim($password);
            $phoneNumber = trim($phone);
            error_log("Normalized inputs: fullname='$fullname', email='$emailRaw', phone='$phoneNumber'");

            // ---- 2) Validation rapide côté serveur
            $earlyErrors = [];
            if ($fullname === '') $earlyErrors['fullname'] = 'Full name is required.';
            if ($emailRaw === '' || !filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) $earlyErrors['email'] = 'A valid email address is required.';
            if ($err = UserValidator::validatePassword($passwordPlain)) $earlyErrors['password'] = $err;

            if (!empty($earlyErrors)) {
                error_log("Early validation errors: " . json_encode($earlyErrors));
                return [
                    'errors' => $earlyErrors,
                    'token' => null,
                    'user' => null,
                    'message' => 'Validation failed.',
                    'redirect' => null,
                ];
            }

            // ---- 3) Création Email ValueObject
            $emailObj = new \Modules\Auth\Core\ValueObjects\Email($emailRaw);

            // ---- 4) Vérification unicité email
            $existingUser = $this->repository->findByEmail((string)$emailObj);
            if ($existingUser) {
                error_log("Email '{$emailObj}' already exists in DB");
                return [
                    'errors' => ['email' => 'This email is already taken.'],
                    'token' => null,
                    'user' => null,
                    'message' => 'Email already exists.',
                    'redirect' => null,
                ];
            }

            // ---- 5) Création entité utilisateur
            $userData = [
                'fullname'       => UserHelper::formatFullName($fullname),
                'email'          => $emailObj,
                'photo'          => UserHelper::getProfileImage(null),
                'password'       => UserHelper::hashPassword($passwordPlain),
                'roles'          => [UserHelper::defaultRole()],
                'status'         => UserHelper::defaultStatus(),
                'verified_email' => 0,
                'coverPhoto'     => UserHelper::defaultCover(),
                'bio'            => UserHelper::defaultBio(),
                'phone'          => $phoneNumber,
            ];

            $userEntity = UserFactory::createFromArray($userData);

            // ---- 6) Username unique
            $username = UserHelper::generateUsername($userEntity->getFullname(), $this->repository);
            if (!$username) {
                $username = strtolower(preg_replace('/\s+/', '', $userEntity->getFullname()));
            }
            $userEntity->setUsername($username);

            // ---- 7) Validation métier complète
            $validator = $this->validator ?? new UserValidator($this->repository);
            $errors = $validator->validate($userEntity);
            if (!empty($errors)) {
                return [
                    'errors' => (array)$errors,
                    'token' => null,
                    'user' => null,
                    'message' => 'Business validation failed.',
                    'redirect' => null,
                ];
            }

            // ---- 8) Persistance via repository
            $savedUser = $this->repository->save($userEntity);
            if (!$savedUser->getId()) {
                throw new \RuntimeException("User insertion failed, ID not generated.");
            }

            // ---- 9) Auth / token
            $token = $this->auth->issueAuthForUser($savedUser);

            // ---- 9b) Crée la session + cookie comme dans le login
            AuthUser::setSessionAndCookie($savedUser, $token);

            // ---- 10) Redirect
            $redirect = $this->withAfterLoginHash($this->safeNextFromRequest('/'));

            // ---- 11) Retour du tableau
            return [
                'token'    => $token,
                'user'     => $savedUser,
                'message'  => 'Account created successfully.',
                'errors'   => [],
                'redirect' => $redirect,
            ];
        } catch (\Throwable $e) {
            error_log("Exception caught in register(): " . $e->getMessage());

            return [
                'errors'   => ['exception' => $e->getMessage()],
                'token'    => null,
                'user'     => null,
                'message'  => 'Registration failed.',
                'redirect' => null,
            ];
        }
    }

    public function finalizeRegistration(array $post): void
    {
        try {
            $csrfSession = $_SESSION['csrf_token'] ?? '';
            $csrfPost = (string)($post['csrf_token'] ?? '');
            if (!$csrfPost || !hash_equals($csrfSession, $csrfPost)) {
                (new JsonResponse(['error' => 'Invalid CSRF token.'], 400))->send();
            }

            $sessionData = $_SESSION['user_registration'] ?? null;
            if (!$sessionData) {
                (new JsonResponse([
                    'success' => false,
                    'error' => 'Please try again.',
                    'redirect' => '/login'
                ], 400))->send();
            }

            $phone = UserHelper::normalizeE164((string)($post['phone_number'] ?? $post['phone'] ?? ''));

            $userEntity = UserFactory::createFromArray([
                'fullname'      => $sessionData['fullname'] ?? '',
                'email'         => $sessionData['email'] ?? '',
                'photo'         => $sessionData['photo'] ?? null,
                'password'      => '',
                'roles'         => [UserHelper::defaultRole()],
                'status'        => UserHelper::defaultStatus(),
                'verifiedEmail' => (int)($sessionData['verified_email'] ?? 0),
                'coverPhoto'    => UserHelper::defaultCover(),
                'bio'           => UserHelper::defaultBio(),
                'phone'         => $phone,
            ]);

            if ($phoneError = UserValidator::validatePhoneNumber($userEntity->getPhone())) {
                (new JsonResponse(['phone_number' => $phoneError], 422))->send();
            }

            $username = UserHelper::generateUsername($userEntity->getFullname(), $this->repository);
            $userEntity->setUsername($username);

            $savedUser = $this->repository->save($userEntity);
            unset($_SESSION['user_registration'], $_SESSION['referral_username']);

            $token = $this->auth->issueAuthForUser($savedUser);
            FlashMessage::add('success', 'Welcome, ' . UserHelper::lastName($savedUser->getFullname()) . '!');

            (new JsonResponse([
                'token' => $token,
                'redirect' => $this->withAfterLoginHash($this->safeNextFromRequest('/'))
            ], 201))->send();
        } catch (\Throwable $e) {
            error_log('FinalizeRegistration error: ' . $e->getMessage());
            (new JsonResponse(['error' => 'An error occurred.'], 500))->send();
        }
    }
}
