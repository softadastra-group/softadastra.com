<?php

namespace Modules\User\Core\Services;

use Cloudinary\Api\Upload\UploadApi;
use Exception;
use Ivi\Core\Jwt\JWT;
use Ivi\Core\Security\Csrf;
use Ivi\Core\Utils\FlashMessage;
use Modules\User\Core\Auth\AuthUser;
use Modules\User\Core\Models\User;
use Modules\User\Core\Repositories\UserRepository;
use Modules\User\Core\Helpers\UserHelper;
use Ivi\Http\JsonResponse;
use Ivi\Http\RedirectResponse;
use Modules\User\Core\Factories\UserFactory;
use Modules\User\Core\Image\PhotoHandler;
use Modules\User\Core\Validator\UserValidator;
use Modules\User\Core\ValueObjects\Email;
use Modules\User\Core\ValueObjects\Role;

class UserService extends BaseService
{
    private UserRepository $repository;
    private int $jwtValidity = 3600 * 24; // 24h

    private ?UserValidator $validator = null;
    private $tokenGenerator;
    /** Handler pour intercepter JsonResponse dans les tests */
    private $jsonResponseHandler = null;

    public function __construct(UserRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    public function setJsonResponseHandler(callable $handler): void
    {
        $this->jsonResponseHandler = $handler;
    }

    public function setTokenGenerator(callable $generator): void
    {
        $this->tokenGenerator = $generator;
    }

    /** Login standard via AuthUser helper */
    public function loginUser(User $user): string
    {
        return AuthUser::login($user);
    }

    /** Logout */
    public function logout(): void
    {
        AuthUser::logout();
    }

    /** Retourne l’utilisateur connecté ou null */
    public function currentUser(): ?User
    {
        return AuthUser::user(null, $this->repository);
    }

    /** Login via email/password avec gestion des tentatives */
    public function loginWithCredentials(string $email, string $password): void
    {
        $lockKey = null;

        try {
            $email = strtolower(trim($email));
            $password = trim($password);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->sendJson(['error' => 'Invalid email format.'], 400);
                return;
            }

            $lockKey = 'login_lock_' . md5($email);
            $this->repository->acquireLock($lockKey);

            // --- récupérer les tentatives échouées
            $failedData = $this->getFailedAttempts($email);
            $attempts = $failedData['failed_attempts'];
            $lastFailed = $failedData['last_failed_login'];

            $BLOCK_SECONDS = 600;
            if ($attempts >= 5) {
                $elapsed = $lastFailed ? (time() - strtotime($lastFailed)) : 0;
                if ($elapsed < $BLOCK_SECONDS) {
                    $remainingMin = (int)ceil(($BLOCK_SECONDS - $elapsed) / 60);
                    $this->sendJson([
                        'success'   => false,
                        'error'     => "Account locked. Try again in {$remainingMin} minute(s).",
                        'blocked'   => true,
                        'remaining' => $remainingMin,
                        'attempts'  => $attempts,
                    ], 429);
                    return;
                } else {
                    $this->repository->resetFailedAttempts($email);
                    $attempts = 0;
                }
            }

            if ($attempts >= 5) {
                $delay = min(2000000, 100000 * pow(2, $attempts - 2));
                usleep((int)$delay);
            }

            $user = $this->repository->findByEmail($email);
            if (!$user || !$user->getPassword() || !UserHelper::verifyPassword($password, $user->getPassword())) {
                $this->repository->incrementFailedAttempts($email);
                $this->sendJson(['error' => 'Incorrect email or password.'], 401);
                return;
            }

            $this->repository->resetFailedAttempts($email);

            $token = $this->issueAuthForUser($user);

            FlashMessage::add('success', "Welcome, {$user->getUsername()}!");
            $redirect = $this->withAfterLoginHash($this->safeNextFromRequest('/user/dashboard'));

            $this->sendJson([
                'token' => $token,
                'user' => [
                    'id'       => (int)$user->getId(),
                    'email'    => (string)$user->getEmail(),
                    'username' => $user->getUsername(),
                ],
                'redirect' => $redirect,
            ], 200);
        } catch (\Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $this->sendJson(['error' => 'An unexpected error occurred.'], 500);
        } finally {
            if ($lockKey) {
                $this->repository->releaseLock($lockKey);
            }
        }
    }

    /**
     * Récupère les tentatives échouées pour un email.
     */
    private function getFailedAttempts(string $email): array
    {
        $row = User::query('login_attempts')->where('email = ?', $email)->first();
        return [
            'failed_attempts'   => $row['failed_attempts'] ?? 0,
            'last_failed_login' => $row['last_failed_login'] ?? null,
        ];
    }


    /** Login via Google OAuth (entrée depuis callback Google) */
    public function loginWithGoogleOAuth(object $googleUser): void
    {
        try {
            $this->captureNextFromGoogleState();

            $email = strtolower(trim((string)($googleUser->email ?? '')));
            if (!$email) {
                FlashMessage::add('error', 'Google did not return a valid email.');
                RedirectResponse::to('/login')->send();
                return;
            }

            $existingUser = $this->repository->findByEmail($email);
            if ($existingUser) {
                $this->issueAuthForUser($existingUser);
                FlashMessage::add('success', 'Welcome back, ' . ($existingUser->getUsername() ?: $existingUser->getFullname()) . '!');
                $next = $this->safeNextFromRequest('/');
                RedirectResponse::to($this->withAfterLoginHash($next))->send(); // <-- send()
                return;
            }

            // Création du nouvel utilisateur
            $userData = [
                'fullname'      => $googleUser->name ?? '',
                'email'         => $email,
                'photo'         => $googleUser->picture ?? null,
                'password'      => null,
                'status'        => 'active',
                'verifiedEmail' => (bool)($googleUser->verifiedEmail ?? false),
                'coverPhoto'    => 'cover.jpg',
            ];

            $roles = [new Role(1, 'user')];
            $newUser = $this->repository->createWithRoles($userData, $roles);

            $this->issueAuthForUser($newUser);
            FlashMessage::add('success', 'Welcome, ' . ($newUser->getUsername() ?: $newUser->getFullname()) . '!');

            $next = $this->safeNextFromRequest('/finalize-registration');
            RedirectResponse::to($this->withAfterLoginHash($next))->send(); // <-- send()
        } catch (\Throwable $e) {
            error_log('Google OAuth login error: ' . $e->getMessage());
            FlashMessage::add('error', 'An error occurred during Google login.');
            RedirectResponse::to('/login')->send(); // <-- send()
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

            $phone = $this->normalizeE164((string)($post['phone_number'] ?? $post['phone'] ?? ''));

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

            $token = $this->issueAuthForUser($savedUser);
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

    public function loginWithGoogle(object $googleUser): void
    {
        try {
            // 1️⃣ Normalisation email
            $emailStr = strtolower(trim((string)($googleUser->email ?? '')));
            if (!$emailStr) {
                FlashMessage::add('error', 'Google did not return a valid email.');
                RedirectResponse::to('/login'); // handler de test ou production
                return;
            }

            // 2️⃣ Vérifie si l'utilisateur existe déjà
            $existingUser = $this->repository->findByEmail($emailStr);
            if ($existingUser) {
                $token = $this->issueAuthForUser($existingUser);
                FlashMessage::add('success', 'Welcome back, ' . $existingUser->getUsername() . '!');
                RedirectResponse::to($this->safeNextFromRequest('/'));
                return;
            }

            // 3️⃣ Crée l'utilisateur via UserFactory
            $userData = [
                'fullname'       => $googleUser->name ?? '',
                'email'          => $emailStr,
                'photo'          => $googleUser->picture ?? null,
                'password'       => null,
                'roles'          => [UserHelper::defaultRole()],
                'status'         => 'active',
                'verifiedEmail'  => (bool)($googleUser->verifiedEmail ?? false),
                'coverPhoto'     => 'cover.jpg',
            ];

            $userEntity = UserFactory::createFromArray($userData);

            // 4️⃣ Persistance via repository
            $savedUser = $this->repository->save($userEntity);

            // 5️⃣ Auth + JWT
            $token = $this->issueAuthForUser($savedUser);
            FlashMessage::add('success', 'Welcome, ' . $savedUser->getUsername() . '!');

            // 6️⃣ Redirection sécurisée
            RedirectResponse::to($this->safeNextFromRequest('/finalize-registration'));
        } catch (\Throwable $e) {
            error_log('LoginWithGoogle error: ' . $e->getMessage());
            FlashMessage::add('error', 'An unexpected error occurred.');
            RedirectResponse::to('/login');
        }
    }


    // === Helpers privés ===

    /**
     * Capture et sécurise le paramètre `next` depuis le state Google OAuth
     */
    private function captureNextFromGoogleState(): void
    {
        $state = $_GET['state'] ?? null;
        if (!$state) {
            return;
        }

        $decoded = json_decode(base64_decode($state), true) ?: [];

        // Vérifie le token CSRF stocké en session
        if (!empty($decoded['csrf']) && !empty($_SESSION['google_oauth_state_csrf'])) {
            if (!hash_equals($_SESSION['google_oauth_state_csrf'], $decoded['csrf'])) {
                return; // CSRF invalide → on ignore
            }
            $_SESSION['google_oauth_state_csrf'] = null; // supprime le token
        }

        // Stocke le next dans la session pour redirection après login
        if (!empty($decoded['next'])) {
            $_SESSION['post_auth_next'] = $decoded['next'];
        } elseif (!empty($_GET['next'])) {
            $_SESSION['post_auth_next'] = $_GET['next'];
        }
    }

    /** Crée session + JWT pour un utilisateur */
    protected function issueAuthForUser(User $user): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        session_regenerate_id(true);

        $_SESSION['unique_id']  = (int)$user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['roles']      = $user->getRoleNames() ?? ['user'];

        $token = UserHelper::generateJwt($user, $this->jwtValidity);

        $user->setAccessToken($token);
        $this->repository->updateAccessToken($user);

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        $isLocal = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) === 1;

        $cookieOptions = [
            'expires'  => time() + $this->jwtValidity,
            'path'     => '/',
            'secure'   => !$isLocal && $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (!$isLocal && preg_match('/(^|\.)softadastra\.com$/i', $host)) {
            $cookieOptions['domain'] = '.softadastra.com';
        }

        setcookie('token', $token, $cookieOptions);

        return $token;
    }


    /** Normalise et sécurise le paramètre `next` */
    private function safeNextFromRequest(string $default = '/'): string
    {
        $next = $_GET['next'] ?? $_POST['next'] ?? ($_SESSION['post_auth_next'] ?? '');
        if (!$next) return $default;

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        $nextHost   = parse_url($next, PHP_URL_HOST) ?? '';
        $nextScheme = parse_url($next, PHP_URL_SCHEME) ?? '';

        if ((strpos($next, '//') === false && str_starts_with($next, '/')) ||
            ($nextHost === $host && ($nextScheme === '' || $nextScheme === $scheme))
        ) {
            return $next;
        }

        return $default;
    }

    /** Ajoute un hash #__sa_after_login pour scroll/restoration front */
    private function withAfterLoginHash(string $url): string
    {
        return str_contains($url, '#') ? $url : ($url . '#__sa_after_login');
    }

    /** Normalisation du téléphone en E.164 pour UG & DRC */
    private function normalizeE164(string $raw): string
    {
        $v = preg_replace('/[^\d+]/', '', trim($raw));
        if ($v === '') return '';

        // Uganda
        if (preg_match('/^(?:\+256|256|0?7)(\d{8})$/', $v, $m)) {
            return '+256' . $m[1];
        }
        // DRC
        if (preg_match('/^(?:\+243|243|0?[89])(\d{8})$/', $v, $m)) {
            return '+243' . $m[1];
        }

        return $v;
    }

    /** Retourne le rôle principal d'un user ou 'user' par défaut */
    private function roleNameOf($user): string
    {
        if (method_exists($user, 'getRoleName') && $user->getRoleName()) return $user->getRoleName();
        if (method_exists($user, 'getRole') && $user->getRole()) return $user->getRole();
        if (method_exists($user, 'getRoleNames')) {
            $all = (array)$user->getRoleNames();
            return $all[0] ?? 'user';
        }
        return 'user';
    }

    /** Synchronise tous les rôles entre source et target, en incluant le principal */
    private function syncAllRolesIfAny($sourceUser, $targetUser): void
    {
        if (method_exists($sourceUser, 'getRoleNames') && method_exists($targetUser, 'setRoleNames')) {
            $roles = array_values(array_filter((array)$sourceUser->getRoleNames()));
            if (!empty($roles)) $targetUser->setRoleNames($roles);
        }
        if (method_exists($targetUser, 'getRoleName') && method_exists($targetUser, 'getRoleNames') && method_exists($targetUser, 'setRoleNames')) {
            $primary = $targetUser->getRoleName();
            if ($primary) {
                $all = $targetUser->getRoleNames();
                if (!in_array($primary, $all, true)) {
                    $all[] = $primary;
                    $targetUser->setRoleNames($all);
                }
            }
        }
    }

    public function register(string $fullname, string $email, string $password, string $phone_number): void
    {
        try {
            // ---- 1) Normalisation entrée
            $fullname = trim($fullname);
            $email = strtolower(trim($email));
            $passwordPlain = trim($password);
            $phone_number = trim($phone_number);

            // ---- 2) Validation rapide
            $earlyErrors = [];
            if ($fullname === '') $earlyErrors['fullname'] = 'Full name is required.';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $earlyErrors['email'] = 'A valid email address is required.';
            if ($err = UserValidator::validatePassword($passwordPlain)) $earlyErrors['password'] = $err;

            if (!empty($earlyErrors)) {
                $this->sendJson(['errors' => $earlyErrors], 400);
                return;
            }

            // ---- 3) Unicité email
            if ($this->repository->findByEmail($email)) {
                $this->sendJson(['error' => 'This email is already taken.'], 409);
                return;
            }

            // ---- 4) Création entité utilisateur via UserFactory
            $photo = null;
            $userData = [
                'fullname'      => $fullname,
                'email'         => $email,
                'photo'         => UserHelper::getProfileImage($photo),
                'password'      => UserHelper::hashPassword($passwordPlain),
                'roles'         => [UserHelper::defaultRole()],
                'status'        => UserHelper::defaultStatus(),
                'verifiedEmail' => false,
                'coverPhoto'    => UserHelper::defaultCover(),
                'bio'           => UserHelper::defaultBio(),
                'phone'         => $phone_number,
            ];

            $userEntity = UserFactory::createFromArray($userData);

            // ---- 5) Validation métier complète
            $validator = $this->validator ?? new UserValidator($this->repository);
            if ($errors = $validator->validate($userEntity)) {
                $this->sendJson(['errors' => (array)$errors], 422);
                return;
            }

            // ---- 6) Nom formaté + username unique
            $userEntity->setFullname(UserHelper::formatFullName($userEntity->getFullname()));
            $userEntity->setUsername(UserHelper::generateUsername($userEntity->getFullname(), $this->repository));

            // ---- 7) Persistance via repository
            $savedUser = $this->repository->save($userEntity);

            // ---- 8) Auth / token
            $token = $this->issueAuthForUser($savedUser);

            // ---- 9) Flash + redirect
            FlashMessage::add('success', 'Welcome, ' . UserHelper::lastName($savedUser->getFullname()) . ', to your account.');
            $redirect = $this->withAfterLoginHash($this->safeNextFromRequest('/'));

            // ---- 10) Réponse JSON 201
            $this->sendJson([
                'token'    => $token,
                'redirect' => $redirect,
                'message'  => 'Account created successfully.'
            ], 201);
        } catch (\Throwable $e) {
            $this->sendJson([
                'error'  => 'An error occurred.',
                'reason' => $e->getMessage()
            ], 500);
        }
    }

    /** Helper interne pour centraliser l'envoi de JSON */
    private function sendJson(array $data, int $status): void
    {
        $response = new JsonResponse($data, $status);

        if ($this->jsonResponseHandler) {
            // Interception pour les tests
            ($this->jsonResponseHandler)($response);
        } else {
            $response->send();
        }
    }

    public function updatePhoto(array $files): void
    {
        // 1) Auth
        $user = $this->getUserEntity();
        if (!$user || !$user->getId()) {
            (new JsonResponse(['error' => 'You must be logged in.'], 401))->send();
        }
        $userId = (int)$user->getId();

        // 2) Identifier le champ visé (on n’accepte qu’un seul à la fois)
        $hasPhoto = isset($files['photo']) && is_array($files['photo']);
        $hasCover = isset($files['cover_photo']) && is_array($files['cover_photo']);

        if (!$hasPhoto && !$hasCover) {
            (new JsonResponse(['error' => 'No image was uploaded.'], 400))->send();
        }
        if ($hasPhoto && $hasCover) {
            (new JsonResponse(['error' => 'Please upload either "photo" or "cover_photo", not both.'], 400))->send();
        }

        $field     = $hasPhoto ? 'photo' : 'cover_photo';
        $file      = $files[$field];
        $fileLabel = $field === 'photo' ? 'Profile photo' : 'Cover photo';
        $destDir   = $field === 'photo' ? 'public/images/profile' : 'public/images/cover';

        // 3) Vérification de l'upload PHP
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            ];
            $msg = $map[$file['error']] ?? 'Upload error.';
            (new JsonResponse(['error' => $msg], 400))->send();
        }

        // 4) Validation basique (taille + MIME)
        $maxBytes = 5 * 1024 * 1024; // 5 Mo
        if (!isset($file['size']) || $file['size'] <= 0) {
            (new JsonResponse(['error' => 'Empty file.'], 400))->send();
        }
        if ($file['size'] > $maxBytes) {
            (new JsonResponse(['error' => 'File too large. Max 5 MB.'], 413))->send();
        }

        $tmpPath = $file['tmp_name'] ?? null;
        if (!$tmpPath || !is_uploaded_file($tmpPath)) {
            (new JsonResponse(['error' => 'Invalid temporary file.'], 400))->send();
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? @finfo_file($finfo, $tmpPath) : null;
        if ($finfo) @finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!$mime || !in_array($mime, $allowed, true)) {
            (new JsonResponse(['error' => 'Unsupported image type. Allowed: JPG, PNG, WEBP.'], 400))->send();
        }

        // 5) Essayer Cloudinary
        $savedUrl = null;
        $savedPid = null;
        try {
            $folder   = defined('CLOUDINARY_FOLDER') ? CLOUDINARY_FOLDER : 'softadastra/users';
            $isAvatar = ($field === 'photo');

            $publicId = sprintf(
                '%s/%d/%s-%s',
                trim($folder, '/'),
                $userId,
                $isAvatar ? 'avatar' : 'cover',
                bin2hex(random_bytes(6))
            );

            $eager = $isAvatar
                ? [['width' => 512,  'height' => 512,  'crop' => 'fill', 'gravity' => 'auto']]
                : [['width' => 1600, 'height' => 600,  'crop' => 'fill', 'gravity' => 'auto']];

            $result = (new UploadApi())->upload($tmpPath, [
                'public_id'     => $publicId,
                'resource_type' => 'image',
                'overwrite'     => true,
                'invalidate'    => true,
                'context'       => ['caption' => $fileLabel, 'alt' => $fileLabel],
                'eager'         => $eager,
            ]);

            $originalUrl = $result['secure_url'] ?? null;
            $eagerUrl    = (!empty($result['eager'][0]['secure_url'])) ? $result['eager'][0]['secure_url'] : null;

            $savedUrl = $eagerUrl ?: $originalUrl;
            $savedPid = $result['public_id'] ?? null;

            if (!$savedUrl || !$savedPid) {
                throw new Exception('Cloudinary upload failed.');
            }
        } catch (\Throwable $e) {
            // 6) Fallback local
            try {
                $filename = $this->handleImage($file, $destDir, $field);
                $savedUrl = '/' . ltrim($filename, '/'); // chemin relatif pour le site
                $savedPid = null;
            } catch (\Throwable $ex) {
                (new JsonResponse([
                    'error' => 'Image upload failed on both Cloudinary and local.',
                    'cloudinary' => $e->getMessage(),
                    'local' => $ex->getMessage()
                ], 500))->send();
            }
        }

        // 7) Persistance en BDD
        $ok = $this->repository->updateField($userId, $field, $savedUrl, $savedPid);
        if (!$ok) {
            (new JsonResponse(['error' => 'Failed to save the new image path.'], 500))->send();
        }

        // 8) Réponse OK
        (new JsonResponse([
            'success'   => true,
            'field'     => $field,
            'url'       => $savedUrl,
            'public_id' => $savedPid,
            'message'   => $fileLabel . ' updated successfully.'
        ], 200))->send();
    }

    public function updateUser(array $post): JsonResponse
    {
        $userEntity = $this->getUserEntity();

        $user = $this->repository->findById($userEntity->getId());
        if (!$user) {
            FlashMessage::add('error', 'User not found.');
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        $updatedUserEntity = $this->prepareUpdatedUserEntity($user, $post);

        $validationErrors = $this->validateUserEntity($updatedUserEntity);
        if (!empty($validationErrors)) {
            foreach ($validationErrors as $field => $error) {
                FlashMessage::add('error', "$field: $error");
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validationErrors
            ], 422);
        }

        $ok = $this->repository->update($updatedUserEntity);
        if ($ok === false) {
            FlashMessage::add('error', 'Failed to update profile.');
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to update profile.'
            ], 500);
        }

        FlashMessage::add('success', 'Profile updated successfully.');
        return new JsonResponse([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => [
                'user' => [
                    'id'         => $updatedUserEntity->getId(),
                    'fullname'   => $updatedUserEntity->getFullname(),
                    'email'      => (string)$updatedUserEntity->getEmail(),
                    'photo'      => $updatedUserEntity->getPhoto(),
                    'username'   => $updatedUserEntity->getUsername(),
                    'bio'        => $updatedUserEntity->getBio(),
                    'phone'      => $updatedUserEntity->getPhone(),
                    'status'     => $updatedUserEntity->getStatus(),
                    'coverPhoto' => $updatedUserEntity->getCoverPhoto(),
                ]
            ]
        ], 200);
    }

    private function validateAndResetPassword(string $token, string $newPassword): void
    {
        // Validation du mot de passe
        $errorPwd = UserValidator::validatePassword($newPassword);
        if ($errorPwd) {
            FlashMessage::add('error', $errorPwd);
            RedirectResponse::to("auth/reset-password?token=$token")->send();
            exit;
        }

        $userRepository = new UserRepository();

        // Recherche l'utilisateur correspondant au token
        $user = $userRepository->findUserWithStatsByResetToken($token);

        if (!$user) {
            FlashMessage::add('error', "Invalid or expired reset token.");
            RedirectResponse::to("auth/forgot-password")->send();
            exit;
        }

        // Reset du mot de passe
        $this->processPasswordReset($user, $token, $newPassword);
    }

    private function processPasswordReset(User $user, string $token, string $newPassword): void
    {
        $jwt = new JWT();

        try {
            // Vérifie la validité du token (check() lance une exception si invalide ou expiré)
            $jwt->check($token, ['key' => env('JWT_SECRET')]);
        } catch (\Exception $e) {
            FlashMessage::add('error', $e->getMessage()); // contiendra "JWT token has expired." si expiré
            RedirectResponse::to("auth/forgot-password")->send();
            exit;
        }

        // Hash le mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->setPassword($hashedPassword);

        // Révoque les refresh tokens
        $user->setRefreshToken(null);

        // Mise à jour via le repository
        $this->repository->update($user);

        // Success + redirection
        FlashMessage::add('success', "Your password has been successfully reset.");
        RedirectResponse::to("auth/login")->send();
        exit;
    }

    private function prepareUpdatedUserEntity(User $user, array $post): User
    {
        return new User(
            id: $user->getId(),
            fullname: $post['full_name'] ?? $user->getFullname(),
            email: $post['email'] ? new Email($post['email']) : $user->getEmail(),
            photo: $user->getPhoto(),
            password: $user->getPassword(),
            roles: $user->getRoles(),
            status: 'active',
            verifiedEmail: $user->getVerifiedEmail(),
            coverPhoto: $user->getCoverPhoto(),
            accessToken: $user->getAccessToken(),
            refreshToken: $user->getRefreshToken(),
            bio: $post['bio'] ?? $user->getBio(),
            phone: $post['phone_number'] ?? $user->getPhone(),
            username: $user->getUsername(),
            cityName: $user->getCityName(),
            countryName: $user->getCountryName(),
            countryImageUrl: $user->getCountryImageUrl()
        );
    }

    private function validateUserEntity(User $userEntity): array
    {
        $errors = [];
        $validator = new UserValidator($this->repository);

        $fieldsToValidate = [
            'fullname' => $userEntity->getFullname(),
            'email'    => (string)$userEntity->getEmail(),
            'bio'      => $userEntity->getBio(),
            'phone'    => $userEntity->getPhone(),
            'username' => $userEntity->getUsername(),
        ];

        foreach ($fieldsToValidate as $field => $value) {
            if ($error = $validator->validateField($field, $value)) {
                $errors[$field] = $error;
            }
        }

        return $errors;
    }

    public function resetPassword(array $post): JsonResponse
    {
        try {
            // Vérifie le token CSRF
            Csrf::verifyToken($post['csrf_token'] ?? null);

            $token = trim($post['token'] ?? '');
            $newPassword = trim($post['new_password'] ?? '');

            if ($token === '' || $newPassword === '') {
                $msg = "Reset token or new password is missing.";
                FlashMessage::add('error', $msg);
                return new JsonResponse(['success' => false, 'message' => $msg], 422);
            }

            // Validation du mot de passe
            $errorPwd = UserValidator::validatePassword($newPassword);
            if ($errorPwd) {
                FlashMessage::add('error', $errorPwd);
                return new JsonResponse(['success' => false, 'message' => $errorPwd], 422);
            }

            $userRepository = new UserRepository();
            $user = $userRepository->findUserWithStatsByResetToken($token);

            if (!$user) {
                $msg = "No user found for this reset token.";
                FlashMessage::add('error', $msg);
                return new JsonResponse(['success' => false, 'message' => $msg], 404);
            }

            // Reset du mot de passe
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $user->setPassword($hashedPassword);
            $user->setRefreshToken(null);
            $userRepository->update($user);

            $msg = "Your password has been successfully reset.";
            FlashMessage::add('success', $msg);

            return new JsonResponse([
                'success' => true,
                'message' => $msg,
                'data'    => [
                    'userId' => $user->getId(),
                    'email'  => (string)$user->getEmail()
                ]
            ], 200);
        } catch (\Exception $e) {
            $msg = "Erreur : " . $e->getMessage();
            FlashMessage::add('error', $msg);
            return new JsonResponse(['success' => false, 'message' => $msg], 500);
        }
    }

    public function setValidator(UserValidator $validator): void
    {
        $this->validator = $validator;
    }

    public static function handleImage($file, $directory, $prefix = 'softadastra')
    {
        return PhotoHandler::photo($file, $prefix, $directory);
    }
}
