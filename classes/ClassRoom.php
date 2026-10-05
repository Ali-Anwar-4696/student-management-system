<?php

declare(strict_types=1);

class ClassRoom
{
    private PDO $pdo;


    // =====================================================
    // CONSTRUCTOR
    // =====================================================

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    // =====================================================
    // ADD CLASS
    // =====================================================

    public function addClass(array $data): bool
    {
        $sql = "
            INSERT INTO classes
            (
                name,
                description,
                status
            )
            VALUES
            (
                :name,
                :description,
                :status
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':status' => $data['status']
        ]);
    }


    // =====================================================
    // CHECK CLASS NAME EXISTS
    // =====================================================

    public function classNameExists(
        string $name,
        ?int $excludeId = null
    ): bool {

        $sql = "
            SELECT id
            FROM classes
            WHERE name = :name
        ";

        $params = [
            ':name' => $name
        ];

        if ($excludeId !== null) {

            $sql .= "
                AND id != :exclude_id
            ";

            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }


    // =====================================================
    // GET CLASSES
    // =====================================================

    public function getClasses(
        string $search = '',
        string $status = '',
        int $limit = 10,
        int $offset = 0
    ): array {

        $sql = "
            SELECT
                id,
                name,
                description,
                status,
                created_at,
                updated_at

            FROM classes

            WHERE 1 = 1
        ";

        $params = [];


        // SEARCH

        if ($search !== '') {

            $sql .= "
                AND (
                    name LIKE :search_name
                    OR description LIKE :search_description
                )
            ";

            $searchValue = '%' . $search . '%';

            $params[':search_name'] = $searchValue;

            $params[':search_description'] = $searchValue;
        }


        // STATUS FILTER

        if ($status !== '') {

            $allowedStatuses = [
                'active',
                'inactive'
            ];

            if (
                in_array(
                    $status,
                    $allowedStatuses,
                    true
                )
            ) {

                $sql .= "
                    AND status = :status
                ";

                $params[':status'] = $status;
            }
        }


        // ORDER

        $sql .= "
            ORDER BY id DESC
        ";


        // PAGINATION

        $sql .= "
            LIMIT :limit OFFSET :offset
        ";


        $stmt = $this->pdo->prepare($sql);


        foreach ($params as $key => $value) {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }


        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );


        $stmt->execute();

        return $stmt->fetchAll();
    }


    // =====================================================
    // COUNT CLASSES
    // =====================================================

    public function countClasses(
        string $search = '',
        string $status = ''
    ): int {

        $sql = "
            SELECT COUNT(*)
            FROM classes
            WHERE 1 = 1
        ";

        $params = [];


        if ($search !== '') {

            $sql .= "
                AND (
                    name LIKE :search_name
                    OR description LIKE :search_description
                )
            ";

            $searchValue = '%' . $search . '%';

            $params[':search_name'] = $searchValue;

            $params[':search_description'] = $searchValue;
        }


        if ($status !== '') {

            $allowedStatuses = [
                'active',
                'inactive'
            ];

            if (
                in_array(
                    $status,
                    $allowedStatuses,
                    true
                )
            ) {

                $sql .= "
                    AND status = :status
                ";

                $params[':status'] = $status;
            }
        }


        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }


        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }


    // =====================================================
    // GET CLASS BY ID
    // =====================================================

    public function getClassById(
        int $id
    ): ?array {

        $sql = "
            SELECT
                id,
                name,
                description,
                status,
                created_at,
                updated_at

            FROM classes

            WHERE id = :id

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $class = $stmt->fetch();

        return $class ?: null;
    }


    // =====================================================
    // UPDATE CLASS
    // =====================================================

    public function updateClass(
        int $id,
        array $data
    ): bool {

        $sql = "
            UPDATE classes SET

                name = :name,
                description = :description,
                status = :status

            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':status' => $data['status'],
            ':id' => $id
        ]);
    }


    // =====================================================
    // DEPENDENT RECORDS
    // =====================================================
    // Deleting a class CASCADEs its sections, exams,
    // assignments, attendance, result settings and teacher
    // assignments, and SET NULLs its students. Structural
    // children may go with the class, but real academic
    // records (students, exams, assignments, attendance)
    // must block the deletion instead of being destroyed.
    // =====================================================

    public function countDependents(int $id): array
    {
        $counts = [
            'students'    => 0,
            'sections'    => 0,
            'exams'       => 0,
            'assignments' => 0,
            'attendance'  => 0,
        ];

        $queries = [
            'students'    => 'SELECT COUNT(*) FROM students WHERE class_id = :id',
            'sections'    => 'SELECT COUNT(*) FROM sections WHERE class_id = :id',
            'exams'       => 'SELECT COUNT(*) FROM exams WHERE class_id = :id',
            'assignments' => 'SELECT COUNT(*) FROM assignments WHERE class_id = :id',
            'attendance'  => 'SELECT COUNT(*) FROM attendance WHERE class_id = :id',
        ];

        foreach ($queries as $key => $sql) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                $counts[$key] = (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log("Class dependent check ({$key}): " . $e->getMessage());
            }
        }

        return $counts;
    }


    // =====================================================
    // DELETE CLASS
    // =====================================================

    public function deleteClass(
        int $id
    ): bool {

        $checkSql = "
            SELECT id
            FROM classes
            WHERE id = :id
            LIMIT 1
        ";

        $checkStmt = $this->pdo->prepare($checkSql);

        $checkStmt->execute([
            ':id' => $id
        ]);


        if (!$checkStmt->fetch()) {
            return false;
        }


        $this->pdo->beginTransaction();


        try {

            $sql = "
                DELETE FROM classes
                WHERE id = :id
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':id' => $id
            ]);


            $this->pdo->commit();

            return $stmt->rowCount() > 0;


        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}