<?php

namespace Modules\User\Core\Repository;

use Exception;
use Modules\User\Core\Models\BaseRepository;
use Modules\User\Core\Models\Table;
use Modules\User\Core\Models\User;
use Modules\User\Core\Services\UserHelper;
use PDO;
use PDOException;

class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Table::USERS);
    }

    public function getDb(): PDO
    {
        return $this->pdo;
    }

    /**
     * Mappe une ligne SQL (array) vers une entité User.
     * Compatible avec :
     *  - rôle principal:  role_id / role_name
     *  - multi-rôles:     roles_csv (GROUP_CONCAT)
     *  - métriques:       message_count, daily_product_count, total_product_count, average_products_per_week
     *  - localisation:    city_name, country_name, country_image_url
     *  - champs divers:   referred_by, ambassador_points, tokens, dates, etc.
     */
    protected function map(array $data): User
    {
        // Constructeur existant : (fullname, email, photo, password, roleName, status, verified_email, cover_photo)
        $user = new User(
            (string)($data['fullname']        ?? ''),
            (string)($data['email']           ?? ''),
            (string)($data['photo']           ?? ''),
            (string)($data['password']        ?? ''),   // hash ou vide si social login
            (string)($data['role_name']       ?? ''),   // nom de rôle principal (via JOIN roles)
            (string)($data['status']          ?? ''),
            (int)   ($data['verified_email']  ?? 0),
            (string)($data['cover_photo']     ?? '')
        );

        // Identité & méta
        if (isset($data['id']))               $user->setId((int)$data['id']);
        if (isset($data['access_token']))     $user->setAccessToken((string)$data['access_token']);
        if (isset($data['refresh_token']))    $user->setRefreshToken($data['refresh_token'] !== null ? (string)$data['refresh_token'] : null);
        if (isset($data['bio']))              $user->setBio((string)$data['bio']);
        if (isset($data['phone']))            $user->setPhone((string)$data['phone']);
        if (isset($data['username']))         $user->setUsername((string)$data['username']);
        if (isset($data['created_at']))       $user->setCreateAt((string)$data['created_at']);   // conserve tes noms de setters
        if (isset($data['updated_at']))       $user->setUpdateAt((string)$data['updated_at']);
        if (isset($data['referred_by']))      $user->setReferredBy($data['referred_by'] !== null ? (int)$data['referred_by'] : null);
        if (isset($data['ambassador_points'])) $user->setAmbassadorPoints((int)$data['ambassador_points']);

        // Localisation jointe (si présente dans le SELECT)
        if (isset($data['city_name']))        $user->setCityName((string)$data['city_name']);
        if (isset($data['country_name']))     $user->setCountryName((string)$data['country_name']);
        if (isset($data['country_image_url'])) $user->setCountryImageUrl((string)$data['country_image_url']);

        // Métriques (si présentes dans le SELECT)
        if (isset($data['message_count']) && method_exists($user, 'setMessageCount')) {
            $user->setMessageCount((int)$data['message_count']);
        }
        if (isset($data['daily_product_count']) && method_exists($user, 'setDailyProductCount')) {
            $user->setDailyProductCount((int)$data['daily_product_count']);
        }

        // Rôle principal (id + nom)
        if (isset($data['role_id'])) {
            $user->setRoleId((int)$data['role_id']);
        }
        if (isset($data['role_name'])) {
            $user->setRoleName((string)$data['role_name']);
            // compat si du code legacy lit encore getRole()
            if (method_exists($user, 'setRole')) {
                $user->setRole((string)$data['role_name']);
            }
        }

        // Multi-rôles via GROUP_CONCAT -> roles_csv
        if (isset($data['roles_csv'])) {
            $roles = array_values(array_filter(
                array_map('trim', explode(',', (string)$data['roles_csv']))
            ));
            $user->setRoleNames($roles);
        }

        // S'assurer que le rôle principal est présent dans roleNames
        $primary = $user->getRoleName();
        if ($primary) {
            $all = $user->getRoleNames();
            if (!in_array($primary, $all, true)) {
                $all[] = $primary;
                $user->setRoleNames($all);
            }
        }

        return $user;
    }


    public function findAll(?int $limit = null): iterable
    {
        try {
            $sql = "
            SELECT 
                u.*, 
                c.name AS city_name,
                co.name AS country_name,
                co.image_url AS country_image_url,
                COUNT(p.id) AS product_count
            FROM {$this->table} u
            INNER JOIN user_location ul ON ul.user_id = u.id
            INNER JOIN cities c ON c.id = ul.city_id
            INNER JOIN countries co ON co.id = ul.country_id
            INNER JOIN products p ON p.user_id = u.id
            WHERE ul.show_city = 1
            GROUP BY u.id
            HAVING COUNT(p.id) > 1
            ORDER BY u.{$this->id} ASC
        ";

            if (!is_null($limit)) {
                $limit = max(1, $limit);
                $sql .= " LIMIT :limit";
            }

            $stmt = $this->pdo->prepare($sql);

            if (!is_null($limit)) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }

            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    yield $this->map($row);
                } catch (Exception $e) {
                    error_log("Mapping failed: " . $e->getMessage());
                    continue;
                }
            }
        } catch (PDOException $e) {
            error_log("Error in findAll: " . $e->getMessage());
            yield from [];
        }
    }

    public function getUsers(?int $limit = null): iterable
    {
        try {
            $sql = "
            SELECT 
                u.*,
                c.name AS city_name,
                co.name AS country_name,
                co.image_url AS country_image_url,
                COALESCE(p.product_count, 0) AS product_count
            FROM {$this->table} u
            INNER JOIN user_location ul ON ul.user_id = u.id
            INNER JOIN cities c ON c.id = ul.city_id
            INNER JOIN countries co ON co.id = ul.country_id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS product_count
                FROM products
                GROUP BY user_id
            ) p ON p.user_id = u.id
            WHERE ul.show_city = 1
            ORDER BY u.created_at DESC
        ";

            if (!is_null($limit)) {
                $limit = max(1, $limit);
                $sql .= " LIMIT :limit";
            }

            $stmt = $this->pdo->prepare($sql);

            if (!is_null($limit)) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }

            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    /** @var \Domain\Users\User $user */
                    $user = $this->map($row);

                    // Hydrate les champs calculés / jointés
                    if (method_exists($user, 'setProductCount')) {
                        $user->setProductCount((int)($row['product_count'] ?? 0));
                    }
                    if (method_exists($user, 'setCityName')) {
                        $user->setCityName($row['city_name'] ?? null);
                    }
                    if (method_exists($user, 'setCountryImageUrl')) {
                        $user->setCountryImageUrl($row['country_image_url'] ?? null);
                    }

                    yield $user; // ✅ objet, plus d'opérateur +
                } catch (Exception $e) {
                    error_log("Mapping failed: " . $e->getMessage());
                    continue;
                }
            }
        } catch (PDOException $e) {
            error_log("Error in getUsers: " . $e->getMessage());
            yield from [];
        }
    }

    /** Convertit un nom de rôle en id (default: 'user'). */
    private function resolveRoleId(?string $roleName): int
    {
        $name = $roleName ? trim(strtolower($roleName)) : 'user';
        $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = :n LIMIT 1");
        $stmt->execute([':n' => $name]);
        $rid = (int)($stmt->fetchColumn() ?: 0);

        if ($rid <= 0) {
            // fallback sur 'user'
            $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = 'user' LIMIT 1");
            $stmt->execute();
            $rid = (int)($stmt->fetchColumn() ?: 0);
        }
        return max(1, $rid);
    }

    /** Convertit une liste de noms de rôles en ids (ignore les inconnus), ajoute 'user' si vide. */
    private function resolveRoleIds(array $roleNames): array
    {
        $norm = array_values(array_unique(
            array_filter(array_map(
                fn($n) => trim(strtolower((string)$n)),
                $roleNames
            ))
        ));
        if (empty($norm)) $norm = ['user'];

        // Requête IN sécurisée
        $in  = implode(',', array_fill(0, count($norm), '?'));
        $sql = "SELECT id, name FROM roles WHERE name IN ($in)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($norm);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ids = [];
        foreach ($rows as $r) {
            $ids[(string)$r['name']] = (int)$r['id'];
        }

        // Assure la présence d'un id pour 'user'
        if (!isset($ids['user'])) {
            $ids['user'] = $this->resolveRoleId('user');
        }

        // Renvoie sous forme d'array d'ids (ordre selon $norm)
        return array_values(array_map(
            fn($name) => $ids[$name] ?? null,
            $norm
        ));
    }

    public function save(User $user): User
    {
        $accessToken = (string)($user->getAccessToken() ?? '');

        // Récupère le rôle principal par nom
        $roleName = null;
        if (method_exists($user, 'getRoleName')) {
            $roleName = $user->getRoleName();
        } elseif (method_exists($user, 'getRole')) {
            $roleName = $user->getRole(); // compat
        }

        // Récupère les rôles multiples (noms)
        $roleNames = [];
        if (method_exists($user, 'getRoleNames')) {
            $roleNames = (array)$user->getRoleNames();
        }

        // Si un roleName principal existe mais n'est pas dans la liste, on le met en tête
        if ($roleName) {
            $ln = array_map('strtolower', $roleNames);
            if (!in_array(strtolower($roleName), $ln, true)) {
                array_unshift($roleNames, $roleName);
            }
        }

        // Résolution des IDs (ajoute 'user' si liste vide)
        $roleIds = $this->resolveRoleIds($roleNames ?: ($roleName ? [$roleName] : []));
        $primaryRoleId = (int)($roleIds[0] ?? $this->resolveRoleId('user'));

        try {
            $this->pdo->beginTransaction();

            // INSERT user avec role_id (principal)
            $sqlUser = "INSERT INTO " . Table::USERS . " 
            (fullname, email, photo, password, role_id, status, verified_email, cover_photo, access_token, bio, phone, username)
            VALUES 
            (:fullname, :email, :photo, :password, :role_id, :status, :verified_email, :cover_photo, :access_token, :bio, :phone, :username)";

            $this->executeQuery($sqlUser, [
                'fullname'       => $user->getFullname(),
                'email'          => $user->getEmail(),
                'photo'          => $user->getPhoto(),
                'password'       => $user->getPassword(),
                'role_id'        => $primaryRoleId,
                'status'         => $user->getStatus(),
                'verified_email' => (int)$user->getVerifiedEmail(),
                'cover_photo'    => $user->getCoverPhoto(),
                'access_token'   => $accessToken,
                'bio'            => $user->getBio(),
                'phone'          => $user->getPhone(),
                'username'       => $user->getUsername(),
            ]);

            $user->setId((int)$this->pdo->lastInsertId());

            // User location (si tu gardes)
            $sqlLocation = "INSERT INTO " . Table::USER_LOCATION . " (user_id, country_id, city_id, show_city) 
                        VALUES (:user_id, :country_id, :city_id, :show_city)";
            $this->executeQuery($sqlLocation, [
                'user_id'    => $user->getId(),
                'country_id' => 1,
                'city_id'    => 1,
                'show_city'  => 1
            ]);

            // Refléter *tous* les rôles dans user_roles (y compris le principal)
            if (!empty($roleIds)) {
                $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
                foreach ($roleIds as $rid) {
                    if ($rid && $rid > 0) {
                        $stmt->execute([':uid' => $user->getId(), ':rid' => (int)$rid]);
                    }
                }
            } else {
                // sécurité : au moins 'user'
                $ridUser = $this->resolveRoleId('user');
                $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
                $stmt->execute([':uid' => $user->getId(), ':rid' => $ridUser]);
            }

            $this->pdo->commit();
            return $user;
        } catch (\PDOException $e) {
            $info = $e->errorInfo;
            error_log("DB save() SQLSTATE={$info[0]} CODE={$info[1]} MSG={$info[2]}");
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new \Exception("An error occurred while saving the user.");
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new \Exception("An unexpected error occurred while saving the user.");
        }
    }

    public function findOneByUsername(string $username): ?User
    {
        $sql = "SELECT * FROM " . Table::USERS . " WHERE LOWER(username) = LOWER(:username) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            return $this->map($data);
        }

        return null;
    }

    public function findReferrals(int $referrerId): array
    {
        $sql = "SELECT 
    u.*, 
    (
        SELECT COUNT(p.id)
        FROM products p
        WHERE p.user_id = u.id AND p.status = 'active'
    ) AS product_count
FROM " . Table::USERS . " u
WHERE u.referred_by = :referrer_id
ORDER BY u.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['referrer_id' => $referrerId]);

        $users = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user = $this->map($row);
            $user->setAmbassadorPoints($row['ambassador_points'] ?? 0); // 🔥 ajoute cette ligne
            $user->setDailyProductCount($row['product_count'] ?? 0);
            $users[] = $user;
        }
        return $users;
    }

    public function incrementAmbassadorPoints(int $userId): void
    {
        $sql = "UPDATE users SET ambassador_points = ambassador_points + 1 WHERE id = :user_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
    }


    public function ajouterHistoriquePoint(int $userId, int $points, string $reason): void
    {
        $sql = "INSERT INTO ambassador_points_history (user_id, points, reason, created_at)
            VALUES (:user_id, :points, :reason, NOW())";

        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute([
                'user_id' => $userId,
                'points' => $points,
                'reason' => $reason
            ]);
        } catch (PDOException $e) {
            error_log("Erreur PDO dans ajouterHistoriquePoint : " . $e->getMessage());
        }
    }

    public function updatePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT password FROM " . Table::USERS . " WHERE id = :user_id");
            $userId = $user->getId();
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingUser) {
                throw new Exception("User not found.");
            }

            if (empty($existingUser['password'])) {
                return $this->updatePasswordForGoogleUser($user, $newPassword);
            }

            // utiliser la même vérif que le login
            if (!UserHelper::verifyPassword($currentPassword, $existingUser['password'])) {
                throw new Exception("Le mot de passe actuel est incorrect.");
            }

            // hasher avec le même helper que register/login
            $hashedPassword = UserHelper::hashPassword($newPassword);

            $sql = "UPDATE " . Table::USERS . " SET password = :password WHERE id = :user_id";
            $this->executeQuery($sql, [
                'password' => $hashedPassword,
                'user_id' => $userId
            ]);

            return true;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'The current password is incorrect.') !== false) {
                throw new Exception("The current password is incorrect.");
            }
            error_log("Error updating password: " . $e->getMessage());
            return false;
        }
    }

    public function findSellers(int $limit = 2): array
    {
        $limit = max(1, (int)$limit);

        $sql = "SELECT u.*, r.name AS role_name
            FROM {$this->table} u
            JOIN roles r ON r.id = u.role_id
            WHERE r.name = 'user' AND u.status = 'active'
            LIMIT :l";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->map($row);
        }
        return $users;
    }

    public function forgotPassword(User $user, string $newPassword): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT password FROM " . Table::USERS . " WHERE id = :user_id");
            $userId = $user->getId();
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingUser) {
                throw new Exception("User not found.");
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            $sql = "UPDATE " . Table::USERS . " SET password = :password WHERE id = :user_id";
            $this->executeQuery($sql, [
                'password' => $hashedPassword,
                'user_id' => $userId
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error resetting password: " . $e->getMessage());
            return false;
        }
    }

    public function updatePasswordForGoogleUser(User $user, string $newPassword): bool
    {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $sql = "UPDATE " . Table::USERS . " SET password = :password WHERE id = :user_id";
            $this->executeQuery($sql, [
                'password' => $hashedPassword,
                'user_id' => $user->getId()
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error updating password for Google user: " . $e->getMessage());
            return false;
        }
    }

    public function findByEmail(string $email): ?User
    {
        $sql = "SELECT 
                u.*,
                r.name AS role_name,                                        -- rôle principal (users.role_id)
                GROUP_CONCAT(DISTINCT r2.name ORDER BY r2.name) AS roles_csv -- tous les rôles via user_roles
            FROM " . Table::USERS . " u
            LEFT JOIN roles r ON r.id = u.role_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r2 ON r2.id = ur.role_id
            WHERE u.email = :email
            GROUP BY u.id
            LIMIT 1";

        return $this->fetchOne($sql, ['email' => $email]) ?: null;
    }

    public function findByRoles(string $role): ?User
    {
        $sql = "SELECT 
                u.*,
                r.name AS role_name,
                GROUP_CONCAT(DISTINCT r2.name ORDER BY r2.name) AS roles_csv
            FROM " . Table::USERS . " u
            LEFT JOIN roles r        ON r.id = u.role_id
            LEFT JOIN user_roles ur  ON ur.user_id = u.id
            LEFT JOIN roles r2       ON r2.id = ur.role_id
            WHERE (r.name = :role OR r2.name = :role)
            GROUP BY u.id
            LIMIT 1";

        return $this->fetchOne($sql, ['role' => $role]) ?: null;
    }


    public function findById(int $id): ?User
    {
        if ($id <= 0) return null;

        $sql = "SELECT 
            u.*,
            u.role_id,                 
            r.name AS role_name,
            GROUP_CONCAT(DISTINCT r2.name ORDER BY r2.name) AS roles_csv,
            ci.name      AS city_name,
            co.name      AS country_name,
            co.image_url AS country_image_url,
            (SELECT COUNT(*) FROM products p 
            WHERE p.user_id = u.id 
                AND p.created_at >= u.created_at 
                AND p.created_at < CURDATE()) AS daily_product_count,
            (SELECT COUNT(*) FROM products p WHERE p.user_id = u.id) AS total_product_count,
            (SELECT ROUND(IFNULL(COUNT(*) / (DATEDIFF(CURDATE(), u.created_at) / 7), 0), 2)
            FROM products p WHERE p.user_id = u.id) AS average_products_per_week,
            (SELECT COUNT(*) FROM messages 
            WHERE incoming_msg_id = :id OR outgoing_msg_id = :id) AS message_count
        FROM users u
        LEFT JOIN roles r               ON r.id = u.role_id
        LEFT JOIN user_roles ur         ON ur.user_id = u.id
        LEFT JOIN roles r2              ON r2.id = ur.role_id
        LEFT JOIN user_location ul      ON ul.user_id = u.id
        LEFT JOIN cities ci             ON ul.city_id = ci.id
        LEFT JOIN countries co          ON ul.country_id = co.id
        WHERE u.id = :id
        GROUP BY u.id
        LIMIT 1
        ";

        return $this->fetchOne($sql, ['id' => $id]) ?: null;
    }

    public function findByUsername(string $username): ?User
    {
        if (empty($username)) {
            return null;
        }

        $sql = "SELECT 
                u.*, 
                ci.name AS city_name, 
                co.name AS country_name,
                co.image_url AS country_image_url,
                (SELECT COUNT(*) 
                 FROM products p 
                 WHERE p.user_id = u.id AND p.created_at >= u.created_at AND p.created_at < CURDATE()) AS daily_product_count,
                (SELECT COUNT(*) 
                 FROM products p 
                 WHERE p.user_id = u.id) AS total_product_count,
                (SELECT 
                    ROUND(
                        IFNULL(COUNT(*) / (DATEDIFF(CURDATE(), u.created_at) / 7), 0), 
                        2
                    ) 
                 FROM products p 
                 WHERE p.user_id = u.id) AS average_products_per_week,
                (SELECT COUNT(*) 
                 FROM messages 
                 WHERE incoming_msg_id = u.id OR outgoing_msg_id = u.id) AS message_count
            FROM " . Table::USERS . " u
            LEFT JOIN " . Table::USER_LOCATION . " ul ON ul.user_id = u.id
            LEFT JOIN " . Table::CITIES . " ci ON ul.city_id = ci.id
            LEFT JOIN " . Table::COUNTRIES . " co ON ul.country_id = co.id
            WHERE u.username = :username";

        $user = $this->fetchOne($sql, ['username' => $username]);

        return $user ?: null;
    }

    public function update(User $user): void
    {
        try {
            $sql = "UPDATE " . Table::USERS . " SET 
                    fullname = :fullname, 
                    email = :email, 
                    photo = :photo, 
                    password = :password, 
                    status = :status, 
                    bio = :bio,
                    phone = :phone,
                    updated_at = NOW() 
                    WHERE id = :id";

            $this->executeQuery($sql, [
                'id' => $user->getId(),
                'fullname' => $user->getFullname(),
                'email' => $user->getEmail(),
                'photo' => $user->getPhoto(),
                'password' => $user->getPassword(),
                'status' => $user->getStatus(),
                'bio' => $user->getBio(),
                'phone' => $user->getPhone()
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error updating user: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Error in user update process: " . $e->getMessage());
        }
    }

    public function updateAccessToken(User $user): void
    {
        try {
            $sql = "UPDATE " . Table::USERS . " SET 
                    access_token = :access_token,
                    status = :status,
                    updated_at = NOW() 
                    WHERE id = :id";

            $this->executeQuery($sql, [
                'id' => $user->getId(),
                'access_token' => $user->getAccessToken(),
                'status' => $user->getStatus()
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error updating access token: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Error in access token update process: " . $e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        try {
            $sql = "DELETE FROM " . Table::USERS . " WHERE id = :id";
            $this->executeQuery($sql, ['id' => $id]);
        } catch (PDOException $e) {
            throw new Exception("Error deleting user with ID {$id}: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Error in delete process for user with ID {$id}: " . $e->getMessage());
        }
    }

    public function updateField(int $id, string $field, string $newUrl, ?string $publicId = null): bool
    {
        $valid = ['photo', 'cover_photo'];
        if (!in_array($field, $valid, true)) {
            throw new \InvalidArgumentException("Invalid field {$field}");
        }

        $pidCol = $field === 'photo' ? 'photo_public_id' : 'cover_photo_public_id';
        $hasPidCol = $this->columnExists('users', $pidCol);

        if ($hasPidCol) {
            $sql = "UPDATE users SET {$field} = :url, {$pidCol} = :pid, updated_at = NOW() WHERE id = :id";
            return $this->executeQuery($sql, ['id' => $id, 'url' => $newUrl, 'pid' => $publicId]);
        } else {
            $sql = "UPDATE users SET {$field} = :url, updated_at = NOW() WHERE id = :id";
            return $this->executeQuery($sql, ['id' => $id, 'url' => $newUrl]);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $st = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :col");
        $st->execute([':col' => $column]);
        return (bool)$st->fetch();
    }

    public function findByResetToken(string $resetToken): ?User
    {
        $sql = "SELECT u.*
            FROM " . Table::USERS . " u
            WHERE u.refresh_token = :refresh_token";

        return $this->fetchOne($sql, [':refresh_token' => $resetToken]);
    }

    public function updateResetToken(User $user, string $resetToken): bool
    {
        try {
            $sql = "UPDATE " . Table::USERS . " u
                    SET u.refresh_token = :refresh_token
                    WHERE u.email = :email";

            $this->executeQuery($sql, [
                'refresh_token' => $resetToken,
                'email' => $user->getEmail()
            ]);

            return true;
        } catch (PDOException $e) {
            throw new Exception("Error updating reset token for user with email " . $user->getEmail() . ": " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("General error updating reset token for user with email " . $user->getEmail() . ": " . $e->getMessage());
        }
    }

    public function deleteByEmail(string $email): void
    {
        try {
            $sql = "DELETE FROM " . Table::USERS . " WHERE email = :email";
            $this->executeQuery($sql, ['email' => $email]);
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la suppression de l'utilisateur avec l'email {$email} : " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Erreur dans le processus de suppression pour l'utilisateur avec l'email {$email} : " . $e->getMessage());
        }
    }

    // Dans votre UserRepository.php
    public function getFailedAttemptsData(string $email): array
    {
        $stmt = $this->pdo->prepare("
        SELECT 
            COALESCE(u.failed_attempts, la.failed_attempts, 0) AS failed_attempts,
            COALESCE(u.last_failed_login, la.last_failed_login) AS last_failed_login
        FROM users u
        LEFT JOIN login_attempts la ON u.email = la.email
        WHERE u.email = :email
    ");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['failed_attempts' => 0, 'last_failed_login' => null];
    }

    public function incrementFailedAttempts(string $email): void
    {
        // Met à jour les deux tables de manière atomique
        $this->pdo->beginTransaction();

        try {
            // Mise à jour de la table users
            $stmt = $this->pdo->prepare("
            UPDATE users 
            SET 
                failed_attempts = COALESCE(failed_attempts, 0) + 1,
                last_failed_login = NOW()
            WHERE email = :email
        ");
            $stmt->execute(['email' => $email]);

            // Mise à jour de la table login_attempts
            $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (email, failed_attempts, last_failed_login)
            VALUES (:email, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                failed_attempts = failed_attempts + 1,
                last_failed_login = NOW()
        ");
            $stmt->execute(['email' => $email]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function resetFailedAttempts(string $email): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_attempts = 0, last_failed_login = NULL 
            WHERE email = :email
        ");
            $stmt->execute(['email' => $email]);

            $stmt = $this->pdo->prepare("
            DELETE FROM login_attempts WHERE email = :email
        ");
            $stmt->execute(['email' => $email]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function acquireLock($key, $timeout = 5)
    {
        // Version sécurisée avec PDO
        $stmt = $this->pdo->prepare("SELECT GET_LOCK(:key, :timeout)");
        $stmt->execute([
            'key' => $key,
            'timeout' => $timeout
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function releaseLock($key)
    {
        $stmt = $this->pdo->prepare("SELECT RELEASE_LOCK(:key)");
        $stmt->execute(['key' => $key]);
        return (bool)$stmt->fetchColumn();
    }
}
