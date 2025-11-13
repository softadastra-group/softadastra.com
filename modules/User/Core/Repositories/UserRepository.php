<?php

declare(strict_types=1);

namespace Modules\User\Core\Repositories;

use Ivi\Core\ORM\Repository;
use Modules\User\Core\Models\User;
use Modules\User\Core\Factories\UserFactory;
use Modules\User\Core\Helpers\UserHelper;
use Modules\User\Core\ValueObjects\Role;

final class UserRepository extends Repository
{
    protected function modelClass(): string
    {
        return User::class;
    }

    /**
     * Récupère un utilisateur par ID avec ses rôles et infos complémentaires
     */
    public function findById(int $id): ?User
    {
        if ($id <= 0) return null;

        // Récupération du user
        $userRow = User::query()
            ->where('id = ?', $id)
            ->first();
        if (!$userRow) return null;

        // Récupération des rôles
        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $id)
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findByUsername(string $username): ?User
    {
        if (!$username) return null;

        $userRow = User::query()
            ->where('username = ?', $username)
            ->first();
        if (!$userRow) return null;

        // On peut aussi récupérer les rôles
        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findByEmail(string $email): ?User
    {
        if (!$email) return null;

        $userRow = User::query()
            ->where('email = ?', $email)
            ->first();
        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function update(User $user): void
    {
        $user->save(); // La méthode save() de l’ORM gère l’UPDATE automatiquement
    }

    public function updateAccessToken(User $user): void
    {
        $user->save(); // Même principe, si accessToken est défini, il sera mis à jour
    }

    public function delete(int $id): void
    {
        $user = $this->find($id);
        if ($user) {
            $user->delete();
        }
    }

    /**
     * Crée un utilisateur avec ses rôles.
     *
     * @param array $data Données utilisateur
     * @param array<string|Role> $roles Liste de rôles en objets Role ou en noms de rôle
     * @return User
     */
    public function createWithRoles(array $data, array $roles = []): User
    {
        $roleObjects = $this->normalizeRoles($roles);

        // Création du user via le factory
        $user = UserFactory::createFromArray(array_merge($data, ['roles' => $roleObjects]));
        $user->save();

        $userId = $user->getId();
        foreach ($roleObjects as $role) {
            User::query('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $role->getId()
            ]);
            $user->addRole($role);
        }

        return $user;
    }

    /**
     * Synchronise les rôles d’un utilisateur.
     *
     * @param User $user
     * @param array<string|Role> $roles Liste de rôles (noms ou objets Role)
     */
    public function syncRoles(User $user, array $roles): void
    {
        $userId = $user->getId();

        // Supprime tous les anciens rôles
        User::query('user_roles')->where('user_id = ?', $userId)->delete();

        $roleObjects = $this->normalizeRoles($roles);

        // Ajout des nouveaux rôles en base et dans l’objet
        foreach ($roleObjects as $role) {
            User::query('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $role->getId()
            ]);
        }

        $user->clearRoles();
        foreach ($roleObjects as $role) {
            $user->addRole($role);
        }
    }

    /**
     * Normalise une liste de rôles (strings ou objets Role) en objets Role.
     * Ajoute 'user' si aucun rôle valide trouvé.
     *
     * @param array<string|Role> $roles
     * @return Role[]
     */
    private function normalizeRoles(array $roles): array
    {
        $roleObjects = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleRow = User::query('roles')->where('name = ?', strtolower(trim($role)))->first();
                if (!$roleRow) {
                    $roleRow = User::query('roles')->where('name = ?', 'user')->first();
                }
                $roleObjects[] = new Role((int)$roleRow['id'], $roleRow['name']);
            } elseif ($role instanceof Role) {
                $roleObjects[] = $role;
            }
        }

        // Fallback sur 'user' si vide
        if (empty($roleObjects)) {
            $roleRow = User::query('roles')->where('name = ?', 'user')->first();
            $roleObjects[] = new Role((int)$roleRow['id'], $roleRow['name']);
        }

        return $roleObjects;
    }


    public function findUserWithStatsById(int $id): ?User
    {
        $userRow = User::query()
            ->select('users.*')
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->leftJoin('user_location ul', 'ul.user_id = users.id')
            ->leftJoin('cities ci', 'ci.id = ul.city_id')
            ->leftJoin('countries co', 'co.id = ul.country_id')
            ->where('users.id = ?', $id)
            ->groupBy('users.id')
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $id)
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findUserWithStatsByEmail(string $email): ?User
    {
        $userRow = User::query()
            ->select('users.*')
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->leftJoin('user_location ul', 'ul.user_id = users.id')
            ->leftJoin('cities ci', 'ci.id = ul.city_id')
            ->leftJoin('countries co', 'co.id = ul.country_id')
            ->where('users.email = ?', $email)
            ->groupBy('users.id')
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findUserWithStatsByUsername(string $username): ?User
    {
        $userRow = User::query()
            ->select('users.*')
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->leftJoin('user_location ul', 'ul.user_id = users.id')
            ->leftJoin('cities ci', 'ci.id = ul.city_id')
            ->leftJoin('countries co', 'co.id = ul.country_id')
            ->where('users.username = ?', $username)
            ->groupBy('users.id')
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findUserWithStatsByResetToken(string $resetToken): ?User
    {
        $userRow = User::query()
            ->where('users.refresh_token = ?', $resetToken)
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function incrementFailedAttempts(string $email): void
    {
        // Récupère les données actuelles
        $user = User::query()->where('email = ?', $email)->first();
        $loginAttempt = User::query('login_attempts')->where('email = ?', $email)->first();

        // Calcul des nouvelles valeurs
        $failedAttempts = ($user['failed_attempts'] ?? 0) + 1;
        $lastFailedLogin = date('Y-m-d H:i:s');

        // Mise à jour atomique via ORM
        if ($user) {
            User::query()
                ->where('email = ?', $email)
                ->update([
                    'failed_attempts'   => $failedAttempts,
                    'last_failed_login' => $lastFailedLogin
                ]);
        }

        if ($loginAttempt) {
            User::query('login_attempts')
                ->where('email = ?', $email)
                ->update([
                    'failed_attempts'   => $loginAttempt['failed_attempts'] + 1,
                    'last_failed_login' => $lastFailedLogin
                ]);
        } else {
            User::query('login_attempts')->insert([
                'email'             => $email,
                'failed_attempts'   => 1,
                'last_failed_login' => $lastFailedLogin
            ]);
        }
    }

    public function resetFailedAttempts(string $email): void
    {
        // Reset des tentatives dans users
        User::query()
            ->where('email = ?', $email)
            ->update([
                'failed_attempts'   => 0,
                'last_failed_login' => null
            ]);

        // Supprime les entrées login_attempts
        User::query('login_attempts')
            ->where('email = ?', $email)
            ->delete();
    }

    /**
     * Tente d’acquérir un verrou sur une clé.
     * @param string $key
     * @param int $timeout en secondes
     * @return bool true si verrou acquis
     */
    public function acquireLock(string $key, int $timeout = 5): bool
    {
        $start = time();
        while (time() - $start < $timeout) {
            try {
                User::query('locks')->insert([
                    'lock_key' => $key,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                // Insert réussi → verrou acquis
                return true;
            } catch (\Exception $e) {
                // clé déjà existante → attendre et réessayer
                usleep(100_000); // 0.1s
            }
        }
        return false; // timeout
    }

    /**
     * Libère un verrou sur une clé.
     * @param string $key
     * @return bool true si verrou libéré
     */
    public function releaseLock(string $key): bool
    {
        return User::query('locks')
            ->where('lock_key = ?', $key)
            ->delete() > 0;
    }

    /**
     * Récupère tous les utilisateurs avec stats, localisation, rôles et nombre de produits.
     *
     * @param int|null $limit Limite optionnelle du nombre d'utilisateurs
     * @param bool $onlyActiveCities Filtrer les utilisateurs dont la ville est affichée
     * @param int|null $minProducts Nombre minimal de produits (null = aucun filtre)
     * @return iterable<User>
     */
    public function getUsersWithStats(?int $limit = null, bool $onlyActiveCities = true, ?int $minProducts = null): iterable
    {
        $qb = User::query()
            ->select(
                'users.*',
                'c.name AS city_name',
                'co.name AS country_name',
                'co.image_url AS country_image_url',
                'COUNT(p.id) AS product_count'
            )
            ->join('user_location ul', 'ul.user_id = users.id')
            ->join('cities c', 'c.id = ul.city_id')
            ->join('countries co', 'co.id = ul.country_id')
            ->leftJoin('products p', 'p.user_id = users.id')
            ->groupBy('users.id')
            ->orderBy('users.created_at', 'DESC');

        if ($onlyActiveCities) {
            $qb->where('ul.show_city = ?', 1);
        }

        if ($limit !== null) {
            $qb->limit(max(1, $limit));
        }

        foreach ($qb->get() as $row) {
            // Filtrer en PHP si minProducts défini
            if ($minProducts !== null && (($row['product_count'] ?? 0) < $minProducts)) {
                continue;
            }

            try {
                $user = $this->normalizeUserWithRoles($row);
                yield $user;
            } catch (\Exception $e) {
                error_log("User mapping failed: " . $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Récupère et hydrate un utilisateur avec ses rôles et stats.
     *
     * @param array $row Données utilisateur brutes
     * @return User
     */
    private function normalizeUserWithRoles(array $row): User
    {
        // Récupération des rôles
        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $row['id'])
            ->get();

        $user = UserFactory::createFromDb($row, $rolesRows);

        // Hydratation des champs calculés / jointés
        if (method_exists($user, 'setProductCount')) {
            $user->setProductCount((int)($row['product_count'] ?? 0));
        }
        if (method_exists($user, 'setCityName')) {
            $user->setCityName($row['city_name'] ?? null);
        }
        if (method_exists($user, 'setCountryImageUrl')) {
            $user->setCountryImageUrl($row['country_image_url'] ?? null);
        }

        return $user;
    }

    /**
     * Récupère tous les utilisateurs avec au moins 2 produits et infos de localisation.
     *
     * @param int|null $limit Optionnel, limite le nombre de résultats
     * @return iterable<User>
     */
    public function findAll(?int $limit = null): iterable
    {
        return $this->getUsersWithStats($limit, true, 2);
    }

    /**
     * Récupère les utilisateurs récents avec stats, localisation, rôles et nombre de produits.
     *
     * @param int|null $limit Optionnel, limite le nombre de résultats
     * @param bool $onlyActiveCities Filtrer les utilisateurs dont la ville est affichée
     * @return iterable<User>
     */
    public function getUsers(?int $limit = null, bool $onlyActiveCities = true): iterable
    {
        // Appel direct de la fonction centrale avec minProducts = null (aucune limite sur le nombre de produits)
        return $this->getUsersWithStats($limit, $onlyActiveCities, null);
    }

    /**
     * Récupère un utilisateur par son username (insensible à la casse).
     */
    public function findOneByUsername(string $username): ?User
    {
        $userRow = User::query()
            ->where('LOWER(username) = LOWER(?)', $username)
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    /**
     * Met à jour le mot de passe d’un utilisateur.
     *
     * @param User $user
     * @param string $newPassword Nouveau mot de passe
     * @param string|null $currentPassword Ancien mot de passe à vérifier (si nécessaire)
     * @return bool
     * @throws \Exception si l'ancien mot de passe est incorrect
     */
    public function updatePassword(User $user, string $newPassword, ?string $currentPassword = null): bool
    {
        $userRow = User::query()->where('id = ?', $user->getId())->first();
        if (!$userRow) return false;

        // Si mot de passe existant et qu'on demande vérification
        if (!empty($userRow['password']) && $currentPassword !== null) {
            if (!UserHelper::verifyPassword($currentPassword, $userRow['password'])) {
                throw new \Exception("Le mot de passe actuel est incorrect.");
            }
        }

        // Hash et mise à jour
        $hashedPassword = UserHelper::hashPassword($newPassword);
        User::query()->where('id = ?', $user->getId())->update([
            'password' => $hashedPassword
        ]);

        return true;
    }

    /**
     * Reset du mot de passe oublié ou Google user.
     */
    public function resetPassword(User $user, string $newPassword): bool
    {
        // Appelle la même fonction, sans vérifier l'ancien
        return $this->updatePassword($user, $newPassword, null);
    }

    /**
     * Récupère des vendeurs actifs (rôle 'user').
     */
    public function findSellers(int $limit = 2): array
    {
        $limit = max(1, $limit);

        $rows = User::query()
            ->select('users.*')
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->where('r.name = ?', 'user')
            ->where('users.status = ?', 'active')
            ->limit($limit)
            ->get();

        $sellers = [];
        foreach ($rows as $row) {
            $rolesRows = User::query()
                ->select('r2.id, r2.name')
                ->leftJoin('user_roles ur', 'ur.user_id = users.id')
                ->leftJoin('roles r2', 'r2.id = ur.role_id')
                ->where('users.id = ?', $row['id'])
                ->get();

            $sellers[] = UserFactory::createFromDb($row, $rolesRows);
        }

        return $sellers;
    }

    /**
     * Récupère un utilisateur par rôle (rôle principal ou via user_roles).
     */
    public function findByRoles(string $roleName): ?User
    {
        $userRow = User::query()
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('(r.name = ? OR r2.name = ?)', [$roleName, $roleName])
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }
}
