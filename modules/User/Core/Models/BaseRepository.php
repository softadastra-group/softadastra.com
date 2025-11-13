<?php

namespace Modules\User\Core\Models;

use Exception;
use Ivi\Core\ORM\Connection;
use PDO;
use PDOException;

abstract class BaseRepository
{
    protected PDO $pdo;
    protected string $table;
    protected string $id = 'id';

    public function __construct(string $table)
    {
        $this->pdo = Connection::instance();
        $this->table = $table;
    }

    public function executeQuery(string $sql, array $parameters): bool
    {
        $stmt = $this->pdo->prepare($sql);
        try {
            return $stmt->execute($parameters);
        } catch (PDOException $e) {
            error_log("Error executing query: " . $e->getMessage());
            throw new PDOException("Error executing query: " . $e->getMessage(), (int) $e->getCode());
        }
    }

    protected function fetchOne(string $sql, array $parameters): ?object
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parameters);
            $entity = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($entity) {
                return $this->map($entity);
            }
        } catch (PDOException $e) {
            error_log("Error in fetchOne: " . $e->getMessage());
            throw new PDOException("Error retrieving: " . $e->getMessage(), (int) $e->getCode());
        }
        return null;
    }

    public function findAll(?int $limit = null): iterable
    {
        try {
            $sql = "SELECT * FROM {$this->table} ORDER BY {$this->id} ASC";

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

    abstract protected function map(array $data): object;

    protected function pdo(): PDO
    {
        return $this->pdo;
    }

    protected function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }
}
