<?php
declare(strict_types=1);

class Subject
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =====================================================
    // Check Subject Name / Code Exists
    // =====================================================

    public function subjectExists(
        string $name,
        ?string $code = null,
        ?int $excludeId = null
    ): bool {

        $sql = "
            SELECT id
            FROM subjects
            WHERE (
                name = :name
        ";

        $params = [
            ':name' => $name
        ];

        if ($code !== null && $code !== '') {
            $sql .= "
                OR code = :code
            ";

            $params[':code'] = $code;
        }

        $sql .= "
            )
        ";

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
    // Get Subjects
    // =====================================================

    public function getSubjects(
        string $search = '',
        string $status = '',
        int $limit = 10,
        int $offset = 0
    ): array {

        $sql = "
            SELECT
                id,
                name,
                code,
                description,
                status,
                created_at,
                updated_at
            FROM subjects
            WHERE 1 = 1
        ";

        $params = [];


        // Search
        if ($search !== '') {

            $sql .= "
                AND (
                    name LIKE :search_name
                    OR code LIKE :search_code
                )
            ";

            $searchValue = '%' . $search . '%';

            $params[':search_name'] = $searchValue;
            $params[':search_code'] = $searchValue;
        }


        // Status Filter
        if ($status !== '') {

            $allowedStatuses = [
                'active',
                'inactive'
            ];

            if (in_array($status, $allowedStatuses, true)) {

                $sql .= "
                    AND status = :status
                ";

                $params[':status'] = $status;
            }
        }


        // Pagination
        $sql .= "
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset
        ";


        $stmt = $this->pdo->prepare($sql);


        // Bind parameters
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
    // Count Subjects
    // =====================================================

    public function countSubjects(
        string $search = '',
        string $status = ''
    ): int {

        $sql = "
            SELECT COUNT(*)
            FROM subjects
            WHERE 1 = 1
        ";

        $params = [];


        // Search
        if ($search !== '') {

            $sql .= "
                AND (
                    name LIKE :search_name
                    OR code LIKE :search_code
                )
            ";

            $searchValue = '%' . $search . '%';

            $params[':search_name'] = $searchValue;
            $params[':search_code'] = $searchValue;
        }


        // Status Filter
        if ($status !== '') {

            $allowedStatuses = [
                'active',
                'inactive'
            ];

            if (in_array($status, $allowedStatuses, true)) {

                $sql .= "
                    AND status = :status
                ";

                $params[':status'] = $status;
            }
        }


        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);


        return (int) $stmt->fetchColumn();
    }


    // =====================================================
    // Get Subject By ID
    // =====================================================

    public function getSubjectById(int $id): ?array
    {
        $sql = "
            SELECT
                id,
                name,
                code,
                description,
                status,
                created_at,
                updated_at
            FROM subjects
            WHERE id = :id
            LIMIT 1
        ";


        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);


        $subject = $stmt->fetch();


        return $subject ?: null;
    }


    // =====================================================
    // Get Active Subjects
    // =====================================================

    public function getActiveSubjects(): array
    {
        $sql = "
            SELECT
                id,
                name,
                code
            FROM subjects
            WHERE status = 'active'
            ORDER BY name ASC
        ";


        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();


        return $stmt->fetchAll();
    }


    // =====================================================
    // Add Subject
    // =====================================================

    public function addSubject(array $data): bool
    {
        $sql = "
            INSERT INTO subjects
            (
                name,
                code,
                description,
                status
            )
            VALUES
            (
                :name,
                :code,
                :description,
                :status
            )
        ";


        $stmt = $this->pdo->prepare($sql);


        return $stmt->execute([
            ':name'        => $data['name'],
            ':code'        => $data['code'],
            ':description' => $data['description'],
            ':status'      => $data['status']
        ]);
    }


    // =====================================================
    // Update Subject
    // =====================================================

    public function updateSubject(
        int $id,
        array $data
    ): bool {

        $sql = "
            UPDATE subjects SET
                name = :name,
                code = :code,
                description = :description,
                status = :status
            WHERE id = :id
        ";


        $stmt = $this->pdo->prepare($sql);


        return $stmt->execute([
            ':name'        => $data['name'],
            ':code'        => $data['code'],
            ':description' => $data['description'],
            ':status'      => $data['status'],
            ':id'          => $id
        ]);
    }


    // =====================================================
    // Dependent Records
    // =====================================================
    // Deleting a subject CASCADEs its marks, exam_subjects,
    // assignments and teacher assignments — i.e. recorded
    // grades would be destroyed. Marks, exam entries,
    // assignments and subject-level attendance block the
    // deletion instead.
    // =====================================================

    public function countDependents(int $id): array
    {
        $counts = [
            'marks'        => 0,
            'exam_subjects' => 0,
            'assignments'  => 0,
            'attendance'   => 0,
        ];

        $queries = [
            'marks'         => 'SELECT COUNT(*) FROM marks WHERE subject_id = :id',
            'exam_subjects' => 'SELECT COUNT(*) FROM exam_subjects WHERE subject_id = :id',
            'assignments'   => 'SELECT COUNT(*) FROM assignments WHERE subject_id = :id',
            'attendance'    => 'SELECT COUNT(*) FROM attendance WHERE subject_id = :id',
        ];

        foreach ($queries as $key => $sql) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                $counts[$key] = (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log("Subject dependent check ({$key}): " . $e->getMessage());
            }
        }

        return $counts;
    }


    // =====================================================
    // Delete Subject
    // =====================================================

    public function deleteSubject(int $id): bool
    {
        // Check subject exists
        $checkSql = "
            SELECT id
            FROM subjects
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
                DELETE FROM subjects
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