<?php

declare(strict_types=1);

class Student
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    // =====================================================
    // ADD STUDENT
    // =====================================================
    // If $data['password'] is set, a users login account is
    // created in the same transaction and linked through
    // students.user_id, so admin-created students can sign
    // in with secure credentials.
    // =====================================================

    public function addStudent(array $data): bool
    {
        $password = (string) ($data['password'] ?? '');

        $started = false;

        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }

        try {

            $userId = null;

            if ($password !== '') {

                // A login needs an email address.
                $email = (string) ($data['email'] ?? '');

                if ($email === '') {
                    throw new RuntimeException(
                        'A login account requires an email address.'
                    );
                }

                $status = (string) ($data['status'] ?? 'pending');

                // pending/active students may log in (pending
                // students still see the awaiting-approval
                // screen); everything else is blocked.
                $userStatus = in_array($status, ['pending', 'active'], true)
                    ? 'active'
                    : 'inactive';

                $userStmt = $this->pdo->prepare(
                    "INSERT INTO users
                        (name, email, password, role, status)
                     VALUES
                        (:name, :email, :password, 'student', :status)"
                );

                $userStmt->execute([
                    ':name'     => $data['name'],
                    ':email'    => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':status'   => $userStatus,
                ]);

                $userId = (int) $this->pdo->lastInsertId();
            }

            $sql = "INSERT INTO students
                    (
                        user_id,
                        student_id,
                        name,
                        father_name,
                        date_of_birth,
                        gender,
                        phone,
                        email,
                        address,
                        class_id,
                        section_id,
                        admission_date,
                        status
                    )
                    VALUES
                    (
                        :user_id,
                        :student_id,
                        :name,
                        :father_name,
                        :date_of_birth,
                        :gender,
                        :phone,
                        :email,
                        :address,
                        :class_id,
                        :section_id,
                        :admission_date,
                        :status
                    )";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':user_id'        => $userId,
                ':student_id'     => $data['student_id'],
                ':name'           => $data['name'],
                ':father_name'    => $data['father_name'],
                ':date_of_birth'  => $data['date_of_birth'],
                ':gender'         => $data['gender'],
                ':phone'          => $data['phone'],
                ':email'          => $data['email'],
                ':address'        => $data['address'],
                ':class_id'       => $data['class_id'],
                ':section_id'     => $data['section_id'],
                ':admission_date' => $data['admission_date'],
                ':status'         => $data['status']
            ]);

            if ($started) {
                $this->pdo->commit();
            }

            return true;

        } catch (Throwable $e) {

            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }


    // =====================================================
    // EMAIL ALREADY USED BY A LOGIN ACCOUNT?
    // =====================================================

    public function emailTakenByAnotherUser(
        string $email,
        ?int $excludeUserId = null
    ): bool {
        $sql = "SELECT id FROM users WHERE email = :email";

        $params = [':email' => strtolower(trim($email))];

        if ($excludeUserId !== null) {
            $sql .= " AND id <> :exclude";
            $params[':exclude'] = $excludeUserId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }


    // =====================================================
    // CHECK STUDENT ID
    // =====================================================

    public function studentIdExists(
        string $studentId,
        ?int $excludeId = null
    ): bool {

        $sql = "SELECT id
                FROM students
                WHERE student_id = :student_id";

        $params = [
            ':student_id' => $studentId
        ];

        if ($excludeId !== null) {

            $sql .= " AND id != :exclude_id";

            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }


    // =====================================================
    // GET STUDENTS
    // =====================================================

    public function getStudents(
        string $search = '',
        string $status = '',
        string $classId = '',
        int $limit = 10,
        int $offset = 0
    ): array {

        $sql = "SELECT
                    s.id,
                    s.user_id,
                    s.student_id,
                    s.name,
                    s.father_name,
                    s.date_of_birth,
                    s.gender,
                    s.phone,
                    s.email,
                    s.address,
                    s.class_id,
                    s.section_id,
                    s.admission_date,
                    s.status,
                    s.created_at,

                    c.name AS class_name,
                    sec.name AS section_name

                FROM students AS s

                LEFT JOIN classes AS c
                    ON s.class_id = c.id

                LEFT JOIN sections AS sec
                    ON s.section_id = sec.id

                WHERE 1 = 1";

        $params = [];


        // =================================================
        // SEARCH
        // =================================================

        if ($search !== '') {

            $sql .= " AND (
                        s.student_id LIKE :search_student_id
                        OR s.name LIKE :search_name
                        OR s.father_name LIKE :search_father
                        OR s.phone LIKE :search_phone
                        OR s.email LIKE :search_email
                    )";

            $searchValue = '%' . $search . '%';

            $params[':search_student_id'] = $searchValue;
            $params[':search_name'] = $searchValue;
            $params[':search_father'] = $searchValue;
            $params[':search_phone'] = $searchValue;
            $params[':search_email'] = $searchValue;
        }


        // =================================================
        // STATUS FILTER
        // =================================================

        if ($status !== '') {

            $allowedStatuses = [
                'pending',
                'active',
                'inactive',
                'graduated',
                'left'
            ];

            if (in_array($status, $allowedStatuses, true)) {

                $sql .= " AND s.status = :status";

                $params[':status'] = $status;
            }
        }


        // =================================================
        // CLASS FILTER
        // =================================================

        if (
            $classId !== '' &&
            ctype_digit($classId) &&
            (int) $classId > 0
        ) {

            $sql .= " AND s.class_id = :class_id";

            $params[':class_id'] = (int) $classId;
        }


        // =================================================
        // ORDER
        // =================================================

        /*
         * Pending students appear first.
         */

        $sql .= " ORDER BY
                    CASE
                        WHEN s.status = 'pending' THEN 0
                        ELSE 1
                    END,
                    s.id DESC";


        // =================================================
        // PAGINATION
        // =================================================

        $sql .= " LIMIT :limit OFFSET :offset";


        // =================================================
        // PREPARE
        // =================================================

        $stmt = $this->pdo->prepare($sql);


        // =================================================
        // BIND PARAMETERS
        // =================================================

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


        // =================================================
        // EXECUTE
        // =================================================

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // =====================================================
    // COUNT STUDENTS
    // =====================================================

    public function countStudents(
        string $search = '',
        string $status = '',
        string $classId = ''
    ): int {

        $sql = "SELECT COUNT(*)

                FROM students AS s

                LEFT JOIN classes AS c
                    ON s.class_id = c.id

                WHERE 1 = 1";

        $params = [];


        // =================================================
        // SEARCH
        // =================================================

        if ($search !== '') {

            $sql .= " AND (
                        s.student_id LIKE :count_search_student_id
                        OR s.name LIKE :count_search_name
                        OR s.father_name LIKE :count_search_father
                        OR s.phone LIKE :count_search_phone
                        OR s.email LIKE :count_search_email
                    )";

            $searchValue = '%' . $search . '%';

            $params[':count_search_student_id'] = $searchValue;
            $params[':count_search_name'] = $searchValue;
            $params[':count_search_father'] = $searchValue;
            $params[':count_search_phone'] = $searchValue;
            $params[':count_search_email'] = $searchValue;
        }


        // =================================================
        // STATUS FILTER
        // =================================================

        if ($status !== '') {

            $allowedStatuses = [
                'pending',
                'active',
                'inactive',
                'graduated',
                'left'
            ];

            if (in_array($status, $allowedStatuses, true)) {

                $sql .= " AND s.status = :count_status";

                $params[':count_status'] = $status;
            }
        }


        // =================================================
        // CLASS FILTER
        // =================================================

        if (
            $classId !== '' &&
            ctype_digit($classId) &&
            (int) $classId > 0
        ) {

            $sql .= " AND s.class_id = :count_class_id";

            $params[':count_class_id'] = (int) $classId;
        }


        // =================================================
        // PREPARE
        // =================================================

        $stmt = $this->pdo->prepare($sql);


        // =================================================
        // BIND
        // =================================================

        foreach ($params as $key => $value) {

            if ($key === ':count_class_id') {

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


        // =================================================
        // EXECUTE
        // =================================================

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }


    // =====================================================
    // GET ACTIVE CLASSES
    // =====================================================

    public function getClasses(): array
    {
        $sql = "SELECT
                    id,
                    name

                FROM classes

                WHERE status = :status

                ORDER BY name ASC";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':status' => 'active'
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // =====================================================
    // GET ACTIVE SECTIONS
    // =====================================================

    public function getSectionsByClass(
        int $classId
    ): array {

        if ($classId <= 0) {
            return [];
        }

        /*
         * First try normal structure:
         * sections.class_id + sections.status
         */

        try {

            $sql = "SELECT
                        id,
                        name,
                        class_id

                    FROM sections

                    WHERE class_id = :class_id
                    AND status = 'active'

                    ORDER BY name ASC";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':class_id' => $classId
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            /*
             * Fallback if sections table does not
             * have a status column.
             */

            try {

                $sql = "SELECT
                            id,
                            name,
                            class_id

                        FROM sections

                        WHERE class_id = :class_id

                        ORDER BY name ASC";

                $stmt = $this->pdo->prepare($sql);

                $stmt->execute([
                    ':class_id' => $classId
                ]);

                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (PDOException $e2) {

                error_log(
                    'Sections Loading Error: ' .
                    $e2->getMessage()
                );

                return [];
            }
        }
    }


    // =====================================================
    // GET SINGLE STUDENT BY USER ID
    // =====================================================

    public function getStudentByUserId(
        int $userId
    ): ?array {

        $sql = "
            SELECT
                s.id,
                s.user_id,
                s.student_id,
                s.name,
                s.father_name,
                s.date_of_birth,
                s.gender,
                s.phone,
                s.email,
                s.address,
                s.class_id,
                s.section_id,
                s.admission_date,
                s.status,

                c.name AS class_name,
                sec.name AS section_name

            FROM students AS s

            LEFT JOIN classes AS c
                ON s.class_id = c.id

            LEFT JOIN sections AS sec
                ON s.section_id = sec.id

            WHERE s.user_id = :user_id

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        return $student ?: null;
    }


    // =====================================================
    // LINK USER ACCOUNT
    // =====================================================

    public function linkUserAccount(
        int $studentId,
        int $userId
    ): bool {

        $stmt = $this->pdo->prepare(
            'UPDATE students
             SET user_id = :user_id
             WHERE id = :id
             AND (user_id IS NULL OR user_id = :user_id2)'
        );

        return $stmt->execute([
            ':user_id'  => $userId,
            ':user_id2' => $userId,
            ':id'       => $studentId,
        ]);
    }


    // =====================================================
    // GET SINGLE STUDENT BY ID
    // =====================================================

    public function getStudentById(
        int $id
    ): ?array {

        $sql = "
            SELECT
                s.id,
                s.user_id,
                s.student_id,
                s.name,
                s.father_name,
                s.date_of_birth,
                s.gender,
                s.phone,
                s.email,
                s.address,
                s.class_id,
                s.section_id,
                s.admission_date,
                s.status,
                s.parent_user_id,
                s.created_at,
                s.updated_at,

                c.name AS class_name,
                sec.name AS section_name

            FROM students AS s

            LEFT JOIN classes AS c
                ON s.class_id = c.id

            LEFT JOIN sections AS sec
                ON s.section_id = sec.id

            WHERE s.id = :id

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        return $student ?: null;
    }


    // =====================================================
    // APPROVE / ASSIGN STUDENT
    // =====================================================

    public function approveStudent(
        int $studentId,
        int $classId,
        ?int $sectionId = null
    ): bool {

        /*
        |--------------------------------------------------------------------------
        | Basic Validation
        |--------------------------------------------------------------------------
        */

        if (
            $studentId <= 0 ||
            $classId <= 0
        ) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Check Class
        |--------------------------------------------------------------------------
        |
        | Selected class must exist and be active.
        |
        */

        try {

            $classStmt = $this->pdo->prepare(
                "SELECT id
                 FROM classes
                 WHERE id = :id
                 AND status = 'active'
                 LIMIT 1"
            );

            $classStmt->execute([
                ':id' => $classId
            ]);

        } catch (PDOException $e) {

            /*
             * Fallback if classes table does not
             * have a status column.
             */

            try {

                $classStmt = $this->pdo->prepare(
                    "SELECT id
                     FROM classes
                     WHERE id = :id
                     LIMIT 1"
                );

                $classStmt->execute([
                    ':id' => $classId
                ]);

            } catch (PDOException $e2) {

                error_log(
                    'Student Approval Class Error: ' .
                    $e2->getMessage()
                );

                return false;
            }
        }


        if (!$classStmt->fetchColumn()) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Section Validation
        |--------------------------------------------------------------------------
        |
        | Section is OPTIONAL.
        |
        | If sectionId is NULL:
        |     Student can be approved without section.
        |
        | If sectionId is provided:
        |     It must belong to the selected class.
        |
        */

        if (
            $sectionId !== null &&
            $sectionId > 0
        ) {

            try {

                $sectionStmt = $this->pdo->prepare(
                    "SELECT id
                     FROM sections
                     WHERE id = :section_id
                     AND class_id = :class_id
                     AND status = 'active'
                     LIMIT 1"
                );

                $sectionStmt->execute([
                    ':section_id' => $sectionId,
                    ':class_id'   => $classId
                ]);

            } catch (PDOException $e) {

                /*
                 * Fallback if sections table does not
                 * have a status column.
                 */

                try {

                    $sectionStmt = $this->pdo->prepare(
                        "SELECT id
                         FROM sections
                         WHERE id = :section_id
                         AND class_id = :class_id
                         LIMIT 1"
                    );

                    $sectionStmt->execute([
                        ':section_id' => $sectionId,
                        ':class_id'   => $classId
                    ]);

                } catch (PDOException $e2) {

                    error_log(
                        'Student Approval Section Error: ' .
                        $e2->getMessage()
                    );

                    return false;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Section Must Exist
            |--------------------------------------------------------------------------
            */

            if (!$sectionStmt->fetchColumn()) {
                return false;
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | No Section
            |--------------------------------------------------------------------------
            |
            | Explicitly store NULL.
            |
            */

            $sectionId = null;
        }


        /*
        |--------------------------------------------------------------------------
        | Check Student
        |--------------------------------------------------------------------------
        |
        | Only pending students can use the approval workflow.
        |
        */

        $checkStmt = $this->pdo->prepare(
            "SELECT id, user_id
             FROM students
             WHERE id = :id
             AND status = 'pending'
             LIMIT 1"
        );

        $checkStmt->execute([
            ':id' => $studentId
        ]);


        $pendingStudent = $checkStmt->fetch(PDO::FETCH_ASSOC);


        if (!$pendingStudent) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Approve Student (atomic: profile + login account)
        |--------------------------------------------------------------------------
        */

        $this->pdo->beginTransaction();

        try {

            $stmt = $this->pdo->prepare(
                "UPDATE students

                 SET
                    class_id = :class_id,
                    section_id = :section_id,
                    status = 'active',
                    updated_at = CURRENT_TIMESTAMP

                 WHERE id = :id
                 AND status = 'pending'"
            );


            $stmt->execute([
                ':class_id'   => $classId,
                ':section_id' => $sectionId,
                ':id'         => $studentId
            ]);


            /*
            |--------------------------------------------------------------------------
            | ACTIVATE THE LINKED LOGIN ACCOUNT
            |--------------------------------------------------------------------------
            |
            | Approval must always leave the student with a working
            | login account (students.status = active and the
            | users row active).
            |
            */

            $approvedUserId = (int) ($pendingStudent['user_id'] ?? 0);

            if ($approvedUserId > 0) {

                $userStmt = $this->pdo->prepare(
                    "UPDATE users
                     SET status = 'active'
                     WHERE id = :id"
                );

                $userStmt->execute([
                    ':id' => $approvedUserId
                ]);
            }

            $this->pdo->commit();

        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }


        /*
        |--------------------------------------------------------------------------
        | Verify Final State
        |--------------------------------------------------------------------------
        |
        | We don't depend on rowCount().
        |
        | MySQL can return 0 affected rows when values
        | are already the same.
        |
        */

        $verifyStmt = $this->pdo->prepare(
            "SELECT id
             FROM students
             WHERE id = :id
             AND class_id = :class_id
             AND status = 'active'

             AND (
                 section_id = :section_id
                 OR (
                     section_id IS NULL
                     AND :section_id_null IS NULL
                 )
             )

             LIMIT 1"
        );


        $verifyStmt->execute([
            ':id'              => $studentId,
            ':class_id'        => $classId,
            ':section_id'      => $sectionId,
            ':section_id_null' => $sectionId
        ]);


        return $verifyStmt->fetchColumn() !== false;
    }


    // =====================================================
    // UPDATE STUDENT
    // =====================================================

    public function updateStudent(
        int $id,
        array $data
    ): bool {

        // =================================================
        // LOAD CURRENT STATE (lifecycle + account sync)
        // =================================================

        $currentStmt = $this->pdo->prepare(
            "SELECT id, status, user_id
             FROM students
             WHERE id = :id
             LIMIT 1"
        );

        $currentStmt->execute([':id' => $id]);

        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            return false;
        }

        $currentStatus = (string) $current['status'];
        $newStatus     = (string) ($data['status'] ?? $currentStatus);

        // =================================================
        // APPROVAL LIFECYCLE ENFORCEMENT
        // =================================================
        //
        // Registered -> Pending -> Admin Approval -> Active
        //
        // Editing a profile must never activate a pending
        // account, and an existing student can never be
        // moved back to pending.
        // =================================================

        if ($currentStatus === 'pending' && $newStatus !== 'pending') {
            throw new RuntimeException(
                'A pending student can only be activated through the approval workflow.'
            );
        }

        if ($currentStatus !== 'pending' && $newStatus === 'pending') {
            throw new RuntimeException(
                'An existing student cannot be moved back to pending.'
            );
        }

        $started = false;

        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }

        try {

            $sql = "UPDATE students SET

                        student_id = :student_id,
                        name = :name,
                        father_name = :father_name,
                        date_of_birth = :date_of_birth,
                        gender = :gender,
                        phone = :phone,
                        email = :email,
                        address = :address,
                        class_id = :class_id,
                        section_id = :section_id,
                        admission_date = :admission_date,
                        status = :status

                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([

                ':student_id'     => $data['student_id'],
                ':name'           => $data['name'],
                ':father_name'    => $data['father_name'],
                ':date_of_birth'  => $data['date_of_birth'],
                ':gender'         => $data['gender'],
                ':phone'          => $data['phone'],
                ':email'          => $data['email'],
                ':address'        => $data['address'],
                ':class_id'       => $data['class_id'],
                ':section_id'     => $data['section_id'],
                ':admission_date' => $data['admission_date'],
                ':status'         => $newStatus,
                ':id'             => $id

            ]);

            // =================================================
            // KEEP THE LOGIN ACCOUNT CONSISTENT
            // =================================================
            //
            // pending/active  -> account may log in
            //                    (pending students still see
            //                     the "awaiting approval" screen)
            // inactive/graduated/left -> account is blocked
            //
            // If the student has no login account yet and a
            // password was supplied, the account is created
            // here (admin-created students) and linked through
            // students.user_id.
            // =================================================

            $userId = (int) ($current['user_id'] ?? 0);

            $password = (string) ($data['password'] ?? '');

            $userStatus = in_array(
                $newStatus,
                ['pending', 'active'],
                true
            ) ? 'active' : 'inactive';

            if ($userId <= 0 && $password !== '') {

                $accountEmail = (string) ($data['email'] ?? '');

                if ($accountEmail === '') {

                    throw new RuntimeException(
                        'A login account requires an email address.'
                    );
                }

                $createStmt = $this->pdo->prepare(
                    "INSERT INTO users
                        (name, email, password, role, status)
                     VALUES
                        (:name, :email, :password, 'student', :status)"
                );

                $createStmt->execute([
                    ':name'     => $data['name'],
                    ':email'    => $accountEmail,
                    ':password' => password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    ),
                    ':status'   => $userStatus,
                ]);

                $userId = (int) $this->pdo->lastInsertId();

                if ($userId > 0) {

                    $linkStmt = $this->pdo->prepare(
                        "UPDATE students
                         SET user_id = :user_id
                         WHERE id = :id"
                    );

                    $linkStmt->execute([
                        ':user_id' => $userId,
                        ':id'      => $id,
                    ]);
                }

            } elseif ($userId > 0) {

                // Existing account: keep login data in sync.

                $userSql = "UPDATE users SET

                        name = :name,
                        status = :status";

                $userParams = [
                    ':name'   => $data['name'],
                    ':status' => $userStatus,
                ];

                // users.email is the login identifier; only
                // sync it when the profile actually has one.
                $profileEmail = (string) ($data['email'] ?? '');

                if ($profileEmail !== '') {

                    $userSql .= ", email = :email";
                    $userParams[':email'] = $profileEmail;
                }

                if ($password !== '') {

                    $userSql .= ", password = :password";
                    $userParams[':password'] = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );
                }

                $userSql .= " WHERE id = :id";
                $userParams[':id'] = $userId;

                $userStmt = $this->pdo->prepare($userSql);
                $userStmt->execute($userParams);
            }

            if ($started) {
                $this->pdo->commit();
            }

            return true;

        } catch (Throwable $e) {

            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }


    // =====================================================
    // ACADEMIC DEPENDENTS
    // =====================================================
    //
    // Historical academic data must never be destroyed by a
    // routine account removal. The admin UI uses this to decide
    // between "deactivate" (always safe) and "permanent delete"
    // (only allowed when no academic record exists yet).
    // =====================================================

    public function hasAcademicRecords(int $id): array
    {
        $counts = [
            'marks'       => 0,
            'attendance'  => 0,
            'submissions' => 0,
            'fees'        => 0,
            'fee_payments'=> 0,
        ];

        foreach (array_keys($counts) as $table) {

            try {

                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*)
                     FROM {$table}
                     WHERE student_id = :id"
                );

                $stmt->execute([':id' => $id]);

                $counts[$table] = (int) $stmt->fetchColumn();

            } catch (PDOException $e) {

                error_log(
                    'Dependent check failed for ' . $table . ': ' . $e->getMessage()
                );
            }
        }

        return $counts;
    }


    // =====================================================
    // DEACTIVATE STUDENT (soft delete)
    // =====================================================
    //
    // Keeps students, marks, attendance, submissions, fees and
    // notifications intact, but stops the account from logging in.
    // =====================================================

    public function deactivateStudent(int $id): bool
    {
        $checkStmt = $this->pdo->prepare(
            "SELECT id, user_id, status
             FROM students
             WHERE id = :id
             LIMIT 1"
        );

        $checkStmt->execute([':id' => $id]);

        $student = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return false;
        }

        $started = false;

        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }

        try {

            $stmt = $this->pdo->prepare(
                "UPDATE students
                 SET status = 'inactive',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );

            $stmt->execute([':id' => $id]);

            $userId = (int) ($student['user_id'] ?? 0);

            if ($userId > 0) {

                $userStmt = $this->pdo->prepare(
                    "UPDATE users
                     SET status = 'inactive'
                     WHERE id = :id"
                );

                $userStmt->execute([':id' => $userId]);
            }

            if ($started) {
                $this->pdo->commit();
            }

            return true;

        } catch (Throwable $e) {

            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }


    // =====================================================
    // DELETE STUDENT (permanent)
    // =====================================================
    //
    // Only called by the admin UI after hasAcademicRecords()
    // confirmed the student has NO academic records yet.
    // Removing a student with marks/attendance history would
    // cascade-delete that history through the foreign keys.
    //
    // The linked users row (login account) is removed with it.
    // =====================================================

    public function deleteStudent(
        int $id
    ): bool {

        // =================================================
        // CHECK STUDENT EXISTS
        // =================================================

        $checkSql = "
            SELECT id, user_id
            FROM students
            WHERE id = :id
            LIMIT 1
        ";

        $checkStmt = $this->pdo->prepare($checkSql);

        $checkStmt->execute([
            ':id' => $id
        ]);

        $student = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return false;
        }


        // =================================================
        // REFUSE TO DESTROY ACADEMIC HISTORY
        // =================================================

        $dependentCounts = $this->hasAcademicRecords($id);

        foreach ($dependentCounts as $table => $count) {

            if ($count > 0) {

                throw new RuntimeException(
                    'This student already has academic records ('
                    . $table . ' = ' . $count
                    . ') and cannot be permanently deleted. '
                    . 'Deactivate the account instead.'
                );
            }
        }


        // =================================================
        // START TRANSACTION
        // =================================================

        $this->pdo->beginTransaction();

        try {

            $userId = (int) ($student['user_id'] ?? 0);

            $sql = "
                DELETE FROM students
                WHERE id = :id
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':id' => $id
            ]);

            // Remove the orphaned login account as well.
            if ($userId > 0) {

                $userStmt = $this->pdo->prepare(
                    "DELETE FROM users
                     WHERE id = :id
                     AND role = 'student'"
                );

                $userStmt->execute([':id' => $userId]);
            }


            // =================================================
            // COMMIT
            // =================================================

            $this->pdo->commit();

            return $stmt->rowCount() > 0;

        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PARENT LINKAGE
    |--------------------------------------------------------------------------
    */

    /**
     * Students linked to the given parent login (users.id).
     *
     * A parent may have several children, so parent_user_id is a
     * plain (non-unique) FK on students.
     */
    public function getChildrenByParent(int $parentUserId): array
    {
        if ($parentUserId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                s.id,
                s.student_id,
                s.name,
                s.email,
                s.father_name,
                s.gender,
                s.date_of_birth,
                s.phone,
                s.status,
                s.class_id,
                s.section_id,
                s.parent_user_id,
                c.name AS class_name,
                sec.name AS section_name
            FROM students s
            LEFT JOIN classes c ON c.id = s.class_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            WHERE s.parent_user_id = :parent_id
            ORDER BY s.name ASC
        ");

        $stmt->execute([':parent_id' => $parentUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A single child, only when it is actually linked to the given
     * parent — this is the authorization check for child detail pages.
     */
    public function getAuthorizedChild(
        int $parentUserId,
        int $studentId
    ): ?array {
        if ($parentUserId <= 0 || $studentId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                s.id,
                s.student_id,
                s.name,
                s.email,
                s.father_name,
                s.gender,
                s.date_of_birth,
                s.phone,
                s.status,
                s.class_id,
                s.section_id,
                s.parent_user_id,
                c.name AS class_name,
                sec.name AS section_name
            FROM students s
            LEFT JOIN classes c ON c.id = s.class_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            WHERE s.parent_user_id = :parent_id
              AND s.id = :student_id
            LIMIT 1
        ");

        $stmt->execute([
            ':parent_id' => $parentUserId,
            ':student_id' => $studentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Link or unlink a parent login account to a student
     * (NULL = unlink). Throws PDOException on failure.
     */
    public function setParentLink(
        int $studentId,
        ?int $parentUserId
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE students
            SET parent_user_id = :parent_user_id
            WHERE id = :id
        ");

        $stmt->execute([
            ':parent_user_id' => $parentUserId,
            ':id' => $studentId,
        ]);
    }
}

