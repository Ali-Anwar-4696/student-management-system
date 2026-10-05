<?php
declare(strict_types=1);

class Section
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =====================================================
    // ADD SECTION
    // =====================================================

    public function addSection(array $data): bool
    {
        $sql = "
            INSERT INTO sections
            (
                class_id,
                name,
                status
            )
            VALUES
            (
                :class_id,
                :name,
                :status
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':class_id' => $data['class_id'],
            ':name'     => $data['name'],
            ':status'   => $data['status']
        ]);
    }


    // =====================================================
    // CHECK SECTION NAME EXISTS IN SAME CLASS
    // =====================================================

    public function sectionNameExists(
        int $classId,
        string $name,
        ?int $excludeId = null
    ): bool {

        $sql = "
            SELECT id
            FROM sections
            WHERE class_id = :class_id
            AND name = :name
        ";

        $params = [
            ':class_id' => $classId,
            ':name'     => $name
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
    // GET SECTIONS
    // =====================================================

    public function getSections(
        string $search = '',
        string $status = '',
        ?int $classId = null,
        int $limit = 10,
        int $offset = 0
    ): array {

        $sql = "
            SELECT
                sections.id,
                sections.class_id,
                sections.name,
                sections.status,
                sections.created_at,
                sections.updated_at,
                classes.name AS class_name

            FROM sections

            INNER JOIN classes
                ON sections.class_id = classes.id

            WHERE 1 = 1
        ";

        $params = [];

        // Search
        if ($search !== '') {

            $sql .= "
                AND (
                    sections.name LIKE :search_section
                    OR classes.name LIKE :search_class
                )
            ";

            $searchValue = '%' . $search . '%';

            $params[':search_section'] = $searchValue;
            $params[':search_class']   = $searchValue;
        }


        // Status filter
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
                    AND sections.status = :status
                ";

                $params[':status'] = $status;
            }
        }


        // Class filter
        if ($classId !== null) {

            $sql .= "
                AND sections.class_id = :class_id
            ";

            $params[':class_id'] = $classId;
        }


        $sql .= "
            ORDER BY sections.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);


        foreach ($params as $key => $value) {

            if ($key === ':class_id') {

                $stmt->bindValue(
                    $key,
                    $value,
                    PDO::PARAM_INT
                );

            } else {

                $stmt->bindValue(
                    $key,
                    $value,
                    PDO::PARAM_STR
                );
            }
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
    // COUNT SECTIONS
    // =====================================================

    public function countSections(
        string $search = '',
        string $status = '',
        ?int $classId = null
    ): int {

        $sql = "
            SELECT COUNT(*)

            FROM sections

            INNER JOIN classes
                ON sections.class_id = classes.id

            WHERE 1 = 1
        ";

        $params = [];


        // Search
        if ($search !== '') {

            $sql .= "
                AND (
                    sections.name LIKE :search_section
                    OR classes.name LIKE :search_class
                )
            ";

            $searchValue = '%' . $search . '%';

            $params[':search_section'] = $searchValue;
            $params[':search_class']   = $searchValue;
        }


        // Status
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
                    AND sections.status = :status
                ";

                $params[':status'] = $status;
            }
        }


        // Class
        if ($classId !== null) {

            $sql .= "
                AND sections.class_id = :class_id
            ";

            $params[':class_id'] = $classId;
        }


        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {

            if ($key === ':class_id') {

                $stmt->bindValue(
                    $key,
                    $value,
                    PDO::PARAM_INT
                );

            } else {

                $stmt->bindValue(
                    $key,
                    $value,
                    PDO::PARAM_STR
                );
            }
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }


    // =====================================================
    // GET SECTION BY ID
    // =====================================================

    public function getSectionById(
        int $id
    ): ?array {

        $sql = "
            SELECT
                sections.id,
                sections.class_id,
                sections.name,
                sections.status,
                sections.created_at,
                sections.updated_at,
                classes.name AS class_name

            FROM sections

            INNER JOIN classes
                ON sections.class_id = classes.id

            WHERE sections.id = :id

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $section = $stmt->fetch();

        return $section ?: null;
    }


    // =====================================================
    // GET ALL ACTIVE CLASSES
    // =====================================================

    public function getActiveClasses(): array
    {
        $sql = "
            SELECT
                id,
                name

            FROM classes

            WHERE status = 'active'

            ORDER BY name ASC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll();
    }


    // =====================================================
    // UPDATE SECTION
    // =====================================================

    public function updateSection(
        int $id,
        array $data
    ): bool {

        $sql = "
            UPDATE sections SET

                class_id = :class_id,
                name = :name,
                status = :status

            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':class_id' => $data['class_id'],
            ':name'     => $data['name'],
            ':status'   => $data['status'],
            ':id'       => $id
        ]);
    }


    // =====================================================
    // DEPENDENT RECORDS
    // =====================================================
    // Deleting a section CASCADEs assignments, attendance,
    // result settings and teacher assignments, and SET
    // NULLs its students. Students, attendance and
    // assignments block the deletion instead.
    // =====================================================

    public function countDependents(int $id): array
    {
        $counts = [
            'students'    => 0,
            'attendance'  => 0,
            'assignments' => 0,
        ];

        $queries = [
            'students'    => 'SELECT COUNT(*) FROM students WHERE section_id = :id',
            'attendance'  => 'SELECT COUNT(*) FROM attendance WHERE section_id = :id',
            'assignments' => 'SELECT COUNT(*) FROM assignments WHERE section_id = :id',
        ];

        foreach ($queries as $key => $sql) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                $counts[$key] = (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log("Section dependent check ({$key}): " . $e->getMessage());
            }
        }

        return $counts;
    }


    // =====================================================
    // DELETE SECTION
    // =====================================================

    public function deleteSection(
        int $id
    ): bool {

        $checkSql = "
            SELECT id
            FROM sections
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
                DELETE FROM sections
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