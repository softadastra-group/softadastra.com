<?php

declare(strict_types=1);

namespace Modules\User\Core\Services;


use Cloudinary\Api\Upload\UploadApi;
use Dotenv\Exception\InvalidFileException;
use Exception;
use Modules\User\Core\Image\PhotoHandler;
use Modules\User\Core\Models\JWT;
use Modules\User\Core\Models\User;
use Modules\User\Core\Repository\UserRepository;
use Modules\User\Core\Utils\FlashMessage;
use Modules\User\Core\Utils\JsonResponse;
use Modules\User\Core\Utils\RedirectionHelper;
use Modules\User\Core\Validator\UserValidator;

class UserService extends Service
{
    private $repository;
    private $validity = 60 * 60 * 24 * 7;

    public function __construct(UserRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    /** Sécurise et normalise le paramètre `next` (évite open-redirect). */
    private function safeNextFromRequest(string $default = '/'): string
    {
        $next = $_GET['next'] ?? $_POST['next'] ?? ($_SESSION['post_auth_next'] ?? '') ?? '';
        if (!$next) return $default;

        // Chemin relatif autorisé
        if (strpos($next, '//') === false && (substr($next, 0, 1) === '/')) {
            return $next;
        }

        // Même origine autorisée
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $nextHost   = parse_url($next, PHP_URL_HOST) ?? '';
        $nextScheme = parse_url($next, PHP_URL_SCHEME) ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if ($nextHost === $host && ($nextScheme === '' || $nextScheme === $scheme)) {
            return $next;
        }

        return $default;
    }

    /** Ajoute le hash de post-login si absent (pour restauration scroll côté front). */
    private function withAfterLoginHash(string $url): string
    {
        return (str_contains($url, '#')) ? $url : ($url . '#__sa_after_login');
    }

    /** Pose session + cookie + accessToken et retourne le token. */
    private function issueAuthForUser(User $userEntity): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        session_regenerate_id(true);

        // Enregistre en session quelques infos utiles
        $_SESSION['unique_id']  = (int)$userEntity->getId();
        $_SESSION['user_email'] = $userEntity->getEmail();
        $_SESSION['role']       = $userEntity->getRoleName();
        $_SESSION['roles']      = $userEntity->getRoleNames();

        // Génération du token avec ton helper existant
        $token = UserHelper::token($userEntity, $this->validity);

        $userEntity->setAccessToken($token);
        $this->repository->updateAccessToken($userEntity);

        // Détection environnement
        $host    = (string)($_SERVER['HTTP_HOST'] ?? '');
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        $isLocal = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) === 1;

        $cookieDomain = null;
        if (!$isLocal && preg_match('/(^|\.)softadastra\.com$/i', $host)) {
            $cookieDomain = '.softadastra.com';
        }

        $cookieOptions = [
            'expires'  => time() + (int)$this->validity,
            'path'     => '/',
            'secure'   => $isLocal ? false : $isHttps,
            'httponly' => true,
            'samesite' => 'Lax', // passe à 'None' si besoin cross-site
        ];
        if ($cookieDomain) {
            $cookieOptions['domain'] = $cookieDomain;
        }

        setcookie('token', $token, $cookieOptions);

        return $token;
    }

    /** Redirection post-auth vers `next` (sécurisé) avec hash pour le front. */
    private function redirectAfterAuth(string $fallback = '/'): void
    {
        $next = $this->safeNextFromRequest($fallback);
        // Nettoyage de l’intention si on l’avait mise en session
        unset($_SESSION['post_auth_next']);
        header('Location: ' . $this->withAfterLoginHash($next), true, 302);
        exit;
    }

    /** Récupère `next` depuis `state` (Google OAuth) et le stocke en session. */
    private function captureNextFromGoogleState(): void
    {
        if (empty($_GET['state'])) return;
        $decoded = json_decode(base64_decode($_GET['state']), true) ?: [];
        // (Optionnel) vérif CSRF si tu avais stocké un jeton lors de la création de l’URL Google
        if (!empty($decoded['csrf']) && isset($_SESSION['google_oauth_state_csrf'])) {
            if (!hash_equals($_SESSION['google_oauth_state_csrf'], $decoded['csrf'])) {
                return; // CSRF invalide → on ignore
            }
            $_SESSION['google_oauth_state_csrf'] = null; // one-shot
        }
        if (!empty($decoded['next'])) {
            $_SESSION['post_auth_next'] = $decoded['next'];
        } elseif (!empty($_GET['next'])) {
            $_SESSION['post_auth_next'] = $_GET['next'];
        }
    }

    public function google($googleUser)
    {
        try {
            // 1) Données Google normalisées
            $fullname       = (string)($googleUser->name ?? '');
            $email          = strtolower(trim((string)($googleUser->email ?? '')));
            $photo          = (string)($googleUser->picture ?? '');
            $verified_email = (int)($googleUser->verifiedEmail ?? 0);

            if ($email === '') {
                FlashMessage::add('error', 'Google did not return a valid email.');
                RedirectionHelper::redirect('login');
            }

            // 2) Conserver “next” (post-auth)
            $this->captureNextFromGoogleState();

            // 3) Si l’utilisateur existe déjà → login direct
            if ($existing = $this->repository->findByEmail($email)) {
                return $this->loginWithGoogle($existing);
            }

            // 4) Defaults pour un nouvel utilisateur
            $roleName = UserHelper::getRole();     // 'user'
            $status   = UserHelper::getStatus();   // 'active' (selon ton helper)
            $cover    = UserHelper::getCoverPhoto();

            // 5) Construire une entité “tampon” en session
            $userEntity = new User($fullname, $email, $photo, '', $roleName, $status, $verified_email, $cover);
            if (method_exists($userEntity, 'setRoleName')) {
                $userEntity->setRoleName($roleName); // <- important pour resolveRoleId()
            }
            // (optionnel) Injecter le rôle principal dans la liste multi-rôles
            if (method_exists($userEntity, 'setRoleNames')) {
                $userEntity->setRoleNames([$roleName]);
            }
            $userEntity->setStatus('active');

            // 6) Stocker le nécessaire pour la finalisation
            $_SESSION['user_registration'] = [
                'fullname'       => $userEntity->getFullname(),
                'email'          => $userEntity->getEmail(),
                'photo'          => $userEntity->getPhoto(),
                'role_name'      => $userEntity->getRoleName() ?? $roleName,
                'status'         => $userEntity->getStatus(),
                'verified_email' => (int)$userEntity->getVerifiedEmail(),
                'cover_photo'    => $userEntity->getCoverPhoto(),
                'bio'            => $userEntity->getBio(),
            ];

            // 7) Rediriger vers la finalisation
            RedirectionHelper::redirect('finalize-registration');
        } catch (Exception $e) {
            FlashMessage::add('error', 'An error has occurred');
            RedirectionHelper::redirect('login');
        }
    }

    public function finalizeRegistrationPOST(array $post): void
    {
        try {
            // 1) CSRF
            $csrfSession = $_SESSION['csrf_token'] ?? '';
            $csrfPost    = (string)($post['csrf_token'] ?? '');
            if (!$csrfPost || !hash_equals($csrfSession, $csrfPost)) {
                JsonResponse::badRequest('Invalid CSRF token.');
            }

            // 2) Données en session
            $userData = $_SESSION['user_registration'] ?? null;
            if (!$userData) {
                JsonResponse::json(['success' => false, 'error' => 'Please try again.', 'redirect' => '/login'], 400);
            }

            // 3) Téléphone
            $rawPhone     = (string)($post['phone_number'] ?? ($post['phone'] ?? ''));
            $phone_number = $this->normalizeE164($rawPhone);

            // 4) Defaults (role/status/cover/bio)
            $roleName = (string)($userData['role_name'] ?? UserHelper::getRole()); // 'user'
            $status   = UserHelper::getStatus();
            $cover    = UserHelper::getCoverPhoto();
            $bio      = UserHelper::getBio();

            // 5) Construire l’entité finale (Google = pas de password local)
            $userEntity = new User(
                (string)($userData['fullname'] ?? ''),
                (string)($userData['email']    ?? ''),
                (string)($userData['photo']    ?? ''),
                '',                    // pas de password
                $roleName,             // nom de rôle
                $status,
                (int)($userData['verified_email'] ?? 0),
                $cover
            );
            // Rôle principal (important pour resolveRoleId())
            if (method_exists($userEntity, 'setRoleName')) {
                $userEntity->setRoleName($roleName);
            }
            // Multi-rôles : injecter au moins le principal
            if (method_exists($userEntity, 'setRoleNames')) {
                $userEntity->setRoleNames([$roleName]);
            }

            $userEntity->setBio($bio);
            $userEntity->setPhone($phone_number);

            // 6) Validation téléphone
            if ($phoneError = UserValidator::validatePhoneNumber($userEntity->getPhone())) {
                JsonResponse::validationError(['phone_number' => $phoneError]);
            }

            // 7) Username + referral
            $userName = UserHelper::generateUsername($userEntity->getFullname(), $this->repository);
            $userEntity->setUsername($userName);

            $ref = trim(strtolower($_SESSION['referral_username'] ?? ''));
            if ($ref && ($referrer = $this->repository->findOneByUsername($ref))) {
                $userEntity->setReferredBy($referrer->getId());
            }

            // 8) Persist (save() traduira role_name → role_id, plus user_roles)
            $user = $this->repository->save($userEntity);

            // 9) Nettoyage du flow
            unset($_SESSION['user_registration'], $_SESSION['referral_username']);

            // 10) Auth + retour
            $token = $this->issueAuthForUser($user);
            $lastName = UserHelper::lastName($userEntity->getFullname());
            FlashMessage::add('success', 'Welcome, ' . $lastName . ', to your account.');

            JsonResponse::created(
                [
                    'token'    => $token,
                    'redirect' => $this->withAfterLoginHash($this->safeNextFromRequest('/')),
                ],
                'Account created successfully.'
            );
        } catch (\Throwable $e) {
            error_log('FinalizeRegistration error: ' . $e->getMessage());
            JsonResponse::serverError('An error occurred.');
        }
    }

    /** même helper que précédemment (ou place-le en utilitaire partagé) */
    private function normalizeE164(string $raw): string
    {
        $v = trim((string)$raw);
        $v = preg_replace('/[^\d+]/', '', $v);

        if ($v === '') return '';

        // Déjà normalisé
        if (strpos($v, '+256') === 0) {
            $d = preg_replace('/\D/', '', substr($v, 4));
            return '+256' . substr($d, 0, 9);
        }
        if (strpos($v, '+243') === 0) {
            $d = preg_replace('/\D/', '', substr($v, 4));
            return '+243' . substr($d, 0, 9);
        }

        // Uganda variantes
        if (strpos($v, '256') === 0) {
            $d = preg_replace('/\D/', '', substr($v, 3));
            return '+256' . substr($d, 0, 9);
        }
        if (strpos($v, '07') === 0) {
            $d = preg_replace('/\D/', '', substr($v, 1));
            return '+256' . substr($d, 0, 9);
        }
        if (preg_match('/^7\d{8,}$/', $v)) {
            $d = preg_replace('/\D/', '', $v);
            return '+256' . substr($d, 0, 9);
        }

        // DRC variantes
        if (strpos($v, '243') === 0) {
            $d = preg_replace('/\D/', '', substr($v, 3));
            return '+243' . substr($d, 0, 9);
        }
        if (preg_match('/^0[89]\d+$/', $v)) {
            $d = preg_replace('/\D/', '', substr($v, 1));
            return '+243' . substr($d, 0, 9);
        }
        if (preg_match('/^[89]\d+$/', $v)) {
            $d = preg_replace('/\D/', '', $v);
            return '+243' . substr($d, 0, 9);
        }

        return $v; // fallback
    }

    private function roleNameOf($user): ?string
    {
        if (method_exists($user, 'getRoleName') && $user->getRoleName()) {
            return $user->getRoleName();
        }
        if (method_exists($user, 'getRole') && $user->getRole()) {
            return $user->getRole(); // compat legacy
        }
        if (method_exists($user, 'getRoleNames')) {
            $all = (array)$user->getRoleNames();
            if (!empty($all)) {
                return (string)$all[0]; // premier rôle comme “principal”
            }
        }
        return 'user'; // fallback safe
    }

    private function syncAllRolesIfAny($sourceUser, $targetUser): void
    {
        if (method_exists($sourceUser, 'getRoleNames') && method_exists($targetUser, 'setRoleNames')) {
            $roles = array_values(array_filter(array_map('trim', (array)$sourceUser->getRoleNames())));
            if (!empty($roles)) {
                $targetUser->setRoleNames($roles);
            }
        }
        // s'assurer que le principal est dans la liste
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

    public function loginWithGoogle($user)
    {
        try {
            $roleName = $this->roleNameOf($user);

            $userEntity = new User(
                $user->getFullname(),
                $user->getEmail(),
                $user->getPhoto(),
                $user->getPassword(),
                $roleName,
                $user->getStatus(),
                $user->getVerifiedEmail(),
                $user->getCoverPhoto()
            );
            $userEntity->setId($user->getId());
            $userEntity->setStatus('active');

            if (method_exists($user, 'getRoleId') && null !== $user->getRoleId()) {
                $userEntity->setRoleId((int)$user->getRoleId());
            }
            if ($roleName && method_exists($userEntity, 'setRoleName')) {
                $userEntity->setRoleName($roleName);
            }

            // ✅ multi-rôles (si présents)
            $this->syncAllRolesIfAny($user, $userEntity);

            // Auth (si tu veux inclure tous les rôles dans le payload, adapte issueAuthForUser)
            $token = $this->issueAuthForUser($userEntity);

            $lastName = $user->getUsername();
            FlashMessage::add('success', "welcome, $lastName !");

            $this->redirectAfterAuth($this->safeNextFromRequest('/'));
        } catch (Exception $e) {
            FlashMessage::add('error', 'An error occurred.');
            RedirectionHelper::redirect('login');
        }
    }

    public function login($p_email, $p_password): void
    {
        $lockKey = null;

        try {
            $email    = strtolower(trim((string)($p_email ?? '')));
            $password = trim((string)($p_password ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                JsonResponse::badRequest('Invalid email format.');
            }

            $lockKey = 'login_lock_' . md5($email);
            $this->repository->acquireLock($lockKey);

            $failedData = $this->repository->getFailedAttemptsData($email);
            $attempts   = (int)($failedData['failed_attempts'] ?? 0);
            $lastFailed = $failedData['last_failed_login'] ?? null;

            $BLOCK_SECONDS = 600;
            if ($attempts >= 5) {
                $elapsed = $lastFailed ? (time() - strtotime($lastFailed)) : 0;
                if ($elapsed < $BLOCK_SECONDS) {
                    $remainingMin = (int)ceil(($BLOCK_SECONDS - $elapsed) / 60);
                    JsonResponse::json([
                        'success'   => false,
                        'error'     => "Account locked. Try again in {$remainingMin} minute(s).",
                        'blocked'   => true,
                        'remaining' => $remainingMin,
                        'attempts'  => $attempts,
                    ], 429);
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
            if (!$user) {
                $this->repository->incrementFailedAttempts($email);
                JsonResponse::unauthorized('Incorrect email or password.');
            }

            if (!$user->getPassword()) {
                $this->repository->incrementFailedAttempts($email);
                JsonResponse::badRequest('This account uses Google. Please sign in with Google.');
            }

            if (!UserHelper::verifyPassword($password, $user->getPassword())) {
                $this->repository->incrementFailedAttempts($email);
                JsonResponse::unauthorized('Incorrect email or password.');
            }

            $this->repository->resetFailedAttempts($email);

            $roleName = $this->roleNameOf($user);

            $userEntity = new User(
                $user->getFullname(),
                $user->getEmail(),
                $user->getPhoto(),
                $user->getPassword(),
                $roleName,
                $user->getStatus(),
                $user->getVerifiedEmail(),
                $user->getCoverPhoto()
            );
            $userEntity->setId($user->getId());
            $userEntity->setStatus('active');

            if (method_exists($user, 'getRoleId') && null !== $user->getRoleId()) {
                $userEntity->setRoleId((int)$user->getRoleId());
            }
            if ($roleName && method_exists($userEntity, 'setRoleName')) {
                $userEntity->setRoleName($roleName);
            }

            // ✅ multi-rôles (si présents)
            $this->syncAllRolesIfAny($user, $userEntity);

            $token = $this->issueAuthForUser($userEntity);

            $lastName = $user->getUsername();
            FlashMessage::add('success', "Welcome, {$lastName}!");

            $redirect = $this->withAfterLoginHash(
                $this->safeNextFromRequest('/user/dashboard')
            );

            JsonResponse::ok(
                [
                    'token' => $token,
                    'user'  => [
                        'id'       => (int)$user->getId(),
                        'email'    => $user->getEmail(),
                        'username' => $user->getUsername(),
                    ],
                    'redirect' => $redirect,
                ],
                'Login successful.'
            );
        } catch (\Throwable $e) {
            error_log('Erreur de connexion: ' . $e->getMessage());
            JsonResponse::serverError('An unexpected error occurred.');
        } finally {
            if ($lockKey) {
                $this->repository->releaseLock($lockKey);
            }
        }
    }

    public function register($fullname, $email, $password, $phone_number): void
    {
        try {
            // ---- 1) Normalisation entrée
            $fullname      = trim((string)($fullname ?? ''));
            $email         = strtolower(trim((string)($email ?? '')));
            $passwordPlain = trim((string)($password ?? ''));
            $phone_number  = trim((string)($phone_number ?? ''));

            // ---- 2) Garde-fous rapides
            $earlyErrors = [];
            if ($fullname === '') {
                $earlyErrors['fullname'] = 'Full name is required.';
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $earlyErrors['email'] = 'A valid email address is required.';
            }
            if ($err = UserValidator::validatePassword($passwordPlain)) {
                $earlyErrors['password'] = $err;
            }
            if (!empty($earlyErrors)) {
                JsonResponse::validationError($earlyErrors);
                return;
            }

            // ---- 3) Unicité email
            if ($this->repository->findByEmail($email)) {
                JsonResponse::conflict('This email is already taken.');
                return;
            }

            // ---- 4) Defaults / helpers
            $photo          = UserHelper::getPhoto();
            $roleName       = UserHelper::getRole();        // ex: 'user'
            $status         = UserHelper::getStatus();
            $verified_email = UserHelper::getVerifiedEmail();
            $cover          = UserHelper::getCoverPhoto();
            $bio            = UserHelper::getBio();

            // ---- 5) Entité (password en clair ici, hash plus bas)
            $userEntity = new User($fullname, $email, $photo, $passwordPlain, $roleName, $status, $verified_email, $cover);

            // ✅ Important pour la migration: renseigner roleName + roleNames
            if (method_exists($userEntity, 'setRoleName')) {
                $userEntity->setRoleName($roleName);
            }
            if (method_exists($userEntity, 'setRoleNames')) {
                // On initialise la liste avec le rôle principal
                $userEntity->setRoleNames([$roleName]);
            }

            $userEntity->setBio($bio);
            $userEntity->setPhone($phone_number);

            // ---- 6) Validation métier complète
            if ($errors = UserValidator::validate($userEntity)) {
                JsonResponse::validationError((array)$errors);
                return;
            }

            // ---- 7) Statut + hash + nom formaté
            $userEntity->setStatus('active');
            $userEntity->setPassword(UserHelper::hashPassword($passwordPlain));
            $userEntity->setFullname(UserHelper::formatFullName($userEntity->getFullname()));

            // ---- 8) Username unique
            $username = UserHelper::generateUsername($userEntity->getFullname(), $this->repository);
            $userEntity->setUsername($username);

            // ---- 9) Persistance
            // (Le repository traduira role_name -> role_id via resolveRoleId()
            //  et insérera user_roles (N:N) en miroir.)
            $user = $this->repository->save($userEntity);

            // ---- 10) Auth / token
            $token = $this->issueAuthForUser($user);

            // ---- 11) Flash + redirect
            $lastName = UserHelper::lastName($user->getFullname());
            FlashMessage::add('success', 'Welcome, ' . $lastName . ', to your account.');
            $redirect = $this->withAfterLoginHash($this->safeNextFromRequest('/'));

            // ---- 12) Réponse 201
            JsonResponse::created(
                ['token' => $token, 'redirect' => $redirect],
                'Account created successfully.'
            );
        } catch (\Throwable $e) {
            JsonResponse::serverError('An error occurred.', ['reason' => $e->getMessage()]);
        }
    }

    public function updatePhoto(array $files): void
    {
        // 1) Auth
        $user = $this->getUserEntity();
        if (!$user || !$user->getId()) {
            JsonResponse::unauthorized('You must be logged in.');
        }
        $userId = (int) $user->getId();

        // 2) Identifier le champ visé (on n’accepte qu’un seul à la fois)
        $hasPhoto = isset($files['photo']) && is_array($files['photo']);
        $hasCover = isset($files['cover_photo']) && is_array($files['cover_photo']);

        if (!$hasPhoto && !$hasCover) {
            JsonResponse::badRequest('No image was uploaded.');
        }
        if ($hasPhoto && $hasCover) {
            JsonResponse::badRequest('Please upload either "photo" or "cover_photo", not both.');
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
            JsonResponse::badRequest($msg);
        }

        // 4) Validation basique (taille + MIME)
        $maxBytes = 5 * 1024 * 1024; // 5 Mo
        if (!isset($file['size']) || $file['size'] <= 0) {
            JsonResponse::badRequest('Empty file.');
        }
        if ($file['size'] > $maxBytes) {
            JsonResponse::json(['success' => false, 'error' => 'File too large. Max 5 MB.'], 413);
        }

        $tmpPath = $file['tmp_name'] ?? null;
        if (!$tmpPath || !is_uploaded_file($tmpPath)) {
            JsonResponse::badRequest('Invalid temporary file.');
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? @finfo_file($finfo, $tmpPath) : null;
        if ($finfo) @finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!$mime || !in_array($mime, $allowed, true)) {
            JsonResponse::badRequest('Unsupported image type. Allowed: JPG, PNG, WEBP.');
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
                throw new InvalidFileException('Cloudinary upload failed.');
            }
        } catch (\Throwable $e) {
            // 6) Fallback local
            try {
                $filename = $this->handleImage($file, $destDir, $field);
                $savedUrl = '/' . ltrim($filename, '/'); // chemin relatif pour le site
                $savedPid = null;
            } catch (\Throwable $ex) {
                JsonResponse::serverError('Image upload failed on both Cloudinary and local.', [
                    'cloudinary' => $e->getMessage(),
                    'local'      => $ex->getMessage()
                ]);
            }
        }

        // 7) Persistance en BDD
        $ok = $this->repository->updateField($userId, $field, $savedUrl, $savedPid);
        if (!$ok) {
            JsonResponse::serverError('Failed to save the new image path.');
        }

        // 8) Réponse OK
        JsonResponse::ok(['field' => $field, 'url' => $savedUrl, 'public_id' => $savedPid], $fileLabel . ' updated successfully.');
    }

    public function updateUser($post)
    {
        $userEntity = $this->getUserEntity();

        $user = $this->repository->findById($userEntity->getId());
        if (!$user) {
            // 404 clair
            JsonResponse::notFound("User not found.");
        }

        $updatedUserEntity = $this->prepareUpdatedUserEntity($user, $post);

        $validationErrors = $this->validateUserEntity($updatedUserEntity);
        if (!empty($validationErrors)) {
            // 422 standard pour la validation
            JsonResponse::validationError($validationErrors);
        }

        $ok = $this->repository->update($updatedUserEntity);
        if ($ok === false) {
            // Si ton repo peut échouer
            JsonResponse::serverError("Failed to update profile.");
        }

        // 200 OK + message (tu peux renvoyer les data utiles si besoin)
        JsonResponse::ok([], "Profile updated successfully.");
    }

    private function prepareUpdatedUserEntity($user, $post)
    {
        $userEntity = new User(
            $user->getFullname(),
            $user->getEmail(),
            $user->getPhoto(),
            $user->getPassword(),
            $user->getRole(),
            $user->getStatus(),
            $user->getVerifiedEmail(),
            $user->getCoverPhoto()
        );

        $userEntity->setId($user->getId());
        $userEntity->setStatus('active');
        $userEntity->setFullname($post['full_name'] ?? $user->getFullname());
        $userEntity->setEmail($post['email'] ?? $user->getEmail());
        $userEntity->setBio($post['bio'] ?? $user->getBio());
        $userEntity->setPhone($post['phone_number'] ?? $user->getPhone());

        return $userEntity;
    }

    private function validateUserEntity($userEntity)
    {
        $errors = [];

        $fullnameError = UserValidator::validateFullname($userEntity->getFullname());
        if ($fullnameError) {
            $errors[] = $fullnameError;
        }

        $emailError = UserValidator::validateEmail($userEntity->getEmail());
        if ($emailError) {
            $errors[] = $emailError;
        }

        $bioError = UserValidator::validateBio($userEntity->getBio());
        if ($bioError) {
            $errors[] = $bioError;
        }

        $phoneError = UserValidator::validatePhoneNumber($userEntity->getPhone());
        if ($phoneError) {
            $errors[] = $phoneError;
        }

        return $errors;
    }

    public function resetPassword($post)
    {
        try {
            $this->validateCsrfToken($post);

            $token = trim($post['token']);
            $newPassword = trim($post['new_password']);

            if (empty($token) || empty($newPassword)) {
                $_SESSION['message'] = "Reset token or new password is missing.";
                return RedirectionHelper::redirect("auth/reset-password");
            }

            $this->validateAndResetPassword($token, $newPassword);
        } catch (Exception $e) {
            $_SESSION['message'] = "Erreur : " . $e->getMessage();
            RedirectionHelper::redirect("auth/reset-password");
        }
    }

    private function validateCsrfToken($post)
    {
        if (empty($post['csrf_token']) || $post['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['message'] = "Token CSRF invalide.";
            throw new Exception("Token CSRF invalide.");
        }
    }

    private function validateAndResetPassword($token, $newPassword)
    {
        $errorPwd = UserValidator::validatePassword($newPassword);
        if ($errorPwd) {
            echo json_encode(['error' => $errorPwd], JSON_UNESCAPED_UNICODE);
            $_SESSION['message'] = $errorPwd;
            RedirectionHelper::redirect("auth/reset-password?token=$token");
        }

        $userRepository = new UserRepository();
        $user = $userRepository->findByResetToken($token);

        if ($user) {
            $this->processPasswordReset($user, $token, $newPassword);
        } else {
            $_SESSION['message'] = "No user found for this reset token.";
            RedirectionHelper::redirect("auth/forgot-password");
        }
    }

    private function processPasswordReset($user, $token, $newPassword)
    {
        $jwt = new JWT();
        if ($jwt->isExpired($token)) {
            $_SESSION['message'] = "The reset token has expired.";
            RedirectionHelper::redirect("auth/forgot-password");
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->setPassword($hashedPassword);
        $user->setRefreshToken(null);
        $this->repository->update($user);

        FlashMessage::add('success', "Your password has been successfully reset.");
        RedirectionHelper::redirect("login");
    }

    public static function handleImage($file, $directory, $prefix = 'softadastra')
    {
        return PhotoHandler::photo($file, $prefix, $directory);
    }
}
