<?php

class Teacher
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
    // ADD TEACHER
    // =====================================================
    // Existing method preserved.
    // This method can still be used where a teacher profile
    // needs to be inserted manually with an existing user_id.
    // =====================================================

    public function addTeacher(array $data): bool
    {
        $sql = "
            INSERT INTO teachers
            (
                user_id,
                teacher_id,
                name,
                phone,
                email,
                address,
                joining_date,
                status
            )
            VALUES
            (
                :user_id,
                :teacher_id,
                :name,
                :phone,
                :email,
                :address,
                :joining_date,
                :status
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':user_id'       => $data['user_id'],
            ':teacher_id'    => $data['teacher_id'],
            ':name'          => $data['name'],
            ':phone'         => $data['phone'],
            ':email'         => $data['email'],
            ':address'       => $data['address'],
            ':joining_date'  => $data['joining_date'],
            ':status'        => $data['status']
        ]);
    }


    // =====================================================
    // CREATE TEACHER WITH LOGIN ACCOUNT
    // =====================================================
    //
    // Admin creates a teacher.
    //
    // This method:
    //
    // 1. Checks whether email already exists
    // 2. Creates a user account
    // 3. Sets role = teacher
    // 4. Creates teacher profile
    // 5. Links teachers.user_id with users.id
    // 6. Uses transaction for data safety
    //
    // Teacher login will use:
    //
    // The centralized login (auth/login.php) with
    // email + password — the teacher-specific entry
    // page only restricts the role, the dashboard is
    // chosen from the database role.
    //
    // The generated password is only an internal password
    // because users.password cannot be NULL.
    // =====================================================

    public function createTeacherWithAccount(array $data): bool
    {
        // -------------------------------------------------
        // START TRANSACTION
        // -------------------------------------------------

        $this->pdo->beginTransaction();

        try {

            // -------------------------------------------------
            // CHECK REQUIRED EMAIL
            // -------------------------------------------------

            if (
                !isset($data['email']) ||
                trim((string) $data['email']) === ''
            ) {
                throw new RuntimeException(
                    'Teacher Gmail is required.'
                );
            }

            $email = trim((string) $data['email']);


            // -------------------------------------------------
            // CHECK EMAIL FORMAT
            // -------------------------------------------------

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'Please enter a valid email address.'
                );
            }


            // -------------------------------------------------
            // CHECK EMAIL ALREADY EXISTS IN USERS
            // -------------------------------------------------

            $checkUserSql = "
                SELECT id
                FROM users
                WHERE email = :email
                LIMIT 1
            ";

            $checkUserStmt = $this->pdo->prepare(
                $checkUserSql
            );

            $checkUserStmt->execute([
                ':email' => $email
            ]);

            if ($checkUserStmt->fetch()) {
                throw new RuntimeException(
                    'This email is already registered.'
                );
            }


            // -------------------------------------------------
            // CHECK EMAIL ALREADY EXISTS IN TEACHERS
            // -------------------------------------------------

            $checkTeacherEmailSql = "
                SELECT id
                FROM teachers
                WHERE email = :email
                LIMIT 1
            ";

            $checkTeacherEmailStmt = $this->pdo->prepare(
                $checkTeacherEmailSql
            );

            $checkTeacherEmailStmt->execute([
                ':email' => $email
            ]);

            if ($checkTeacherEmailStmt->fetch()) {
                throw new RuntimeException(
                    'This teacher email is already in use.'
                );
            }


            // -------------------------------------------------
            // CHECK TEACHER ID AGAIN
            // -------------------------------------------------
            // We check here as an additional safety measure.
            // The admin page should also check it before calling
            // this method.
            // -------------------------------------------------

            if (
                !isset($data['teacher_id']) ||
                trim((string) $data['teacher_id']) === ''
            ) {
                throw new RuntimeException(
                    'Teacher ID is required.'
                );
            }

            $teacherId = trim(
                (string) $data['teacher_id']
            );

            if ($this->teacherIdExists($teacherId)) {
                throw new RuntimeException(
                    'This Teacher ID already exists.'
                );
            }


            // -------------------------------------------------
            // TEACHER NAME
            // -------------------------------------------------

            if (
                !isset($data['name']) ||
                trim((string) $data['name']) === ''
            ) {
                throw new RuntimeException(
                    'Teacher name is required.'
                );
            }

            $name = trim(
                (string) $data['name']
            );


            // -------------------------------------------------
            // STATUS
            // -------------------------------------------------

            $status = $data['status'] ?? 'active';

            $allowedStatuses = [
                'active',
                'inactive'
            ];

            if (!in_array($status, $allowedStatuses, true)) {
                throw new RuntimeException(
                    'Invalid teacher status.'
                );
            }


            // -------------------------------------------------
            // PASSWORD
            // -------------------------------------------------
            //
            // Preferred: an administrator-supplied initial
            // password (stored only as a password_hash()).
            //
            // Fallback: a random internal password, only used
            // when the caller does not supply one, because
            // users.password is NOT NULL in the database.
            // -------------------------------------------------

            $plainPassword = trim((string) ($data['password'] ?? ''));

            if ($plainPassword !== '') {
                $hashedPassword = password_hash(
                    $plainPassword,
                    PASSWORD_DEFAULT
                );
            } else {
                $hashedPassword = password_hash(
                    bin2hex(random_bytes(32)),
                    PASSWORD_DEFAULT
                );
            }

            if ($hashedPassword === false) {
                throw new RuntimeException(
                    'Unable to create teacher login account.'
                );
            }


            // -------------------------------------------------
            // CREATE USER ACCOUNT
            // -------------------------------------------------

            $userSql = "
                INSERT INTO users
                (
                    name,
                    email,
                    password,
                    role,
                    status
                )
                VALUES
                (
                    :name,
                    :email,
                    :password,
                    'teacher',
                    :status
                )
            ";

            $userStmt = $this->pdo->prepare(
                $userSql
            );

            $userStmt->execute([
                ':name'     => $name,
                ':email'    => $email,
                ':password' => $hashedPassword,
                ':status'   => $status
            ]);


            // -------------------------------------------------
            // GET NEW USER ID
            // -------------------------------------------------

            $userId = (int) $this->pdo->lastInsertId();

            if ($userId <= 0) {
                throw new RuntimeException(
                    'Teacher user account could not be created.'
                );
            }


            // -------------------------------------------------
            // PREPARE TEACHER DATA
            // -------------------------------------------------

            $phone = $data['phone'] ?? null;
            $address = $data['address'] ?? null;
            $joiningDate = $data['joining_date'] ?? null;


            // -------------------------------------------------
            // CREATE TEACHER PROFILE
            // -------------------------------------------------

            $teacherSql = "
                INSERT INTO teachers
                (
                    user_id,
                    teacher_id,
                    name,
                    phone,
                    email,
                    address,
                    joining_date,
                    status
                )
                VALUES
                (
                    :user_id,
                    :teacher_id,
                    :name,
                    :phone,
                    :email,
                    :address,
                    :joining_date,
                    :status
                )
            ";

            $teacherStmt = $this->pdo->prepare(
                $teacherSql
            );

            $teacherStmt->execute([
                ':user_id'      => $userId,
                ':teacher_id'   => $teacherId,
                ':name'         => $name,
                ':phone'        => $phone,
                ':email'        => $email,
                ':address'      => $address,
                ':joining_date' => $joiningDate,
                ':status'       => $status
            ]);


            // -------------------------------------------------
            // COMMIT TRANSACTION
            // -------------------------------------------------

            $this->pdo->commit();

            return true;


        } catch (Throwable $e) {

            // -------------------------------------------------
            // ROLLBACK IF SOMETHING GOES WRONG
            // -------------------------------------------------

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }


    // =====================================================
    // CHECK TEACHER ID
    // =====================================================

    public function teacherIdExists(
        string $teacherId,
        ?int $excludeId = null
    ): bool {

        $sql = "
            SELECT id
            FROM teachers
            WHERE teacher_id = :teacher_id
        ";

        $params = [
            ':teacher_id' => $teacherId
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
    // EMAIL UNIQUE CHECK (LOGIN ACCOUNT)
    // =====================================================
    //
    // Login always matches users.email, and users.email
    // is UNIQUE, so an email collision must be caught
    // before we write.
    // =====================================================

    public function emailTakenByAnotherUser(
        string $email,
        ?int $excludeUserId = null
    ): bool {

        $sql = "
            SELECT id
            FROM users
            WHERE email = :email
        ";

        $params = [
            ':email' => $email
        ];


        if ($excludeUserId !== null && $excludeUserId > 0) {

            $sql .= "
                AND id != :exclude_id
            ";

            $params[':exclude_id'] = $excludeUserId;
        }


        $sql .= " LIMIT 1";


        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    public function getTeachers(
        string $search = '',
        string $status = '',
        int $limit = 10,
        int $offset = 0
    ): array {

        $sql = "
            SELECT
                id,
                user_id,
                teacher_id,
                name,
                phone,
                email,
                address,
                joining_date,
                status,
                created_at,
                updated_at

            FROM teachers

            WHERE 1 = 1
        ";

        $params = [];


        // =================================================
        // SEARCH
        // =================================================

        if ($search !== '') {

            $sql .= "
                AND (
                    teacher_id LIKE :search_teacher_id
                    OR name LIKE :search_name
                    OR phone LIKE :search_phone
                    OR email LIKE :search_email
                )
            ";

            $searchValue = '%' . $search . '%';

            $params[':search_teacher_id'] = $searchValue;
            $params[':search_name'] = $searchValue;
            $params[':search_phone'] = $searchValue;
            $params[':search_email'] = $searchValue;
        }


        // =================================================
        // STATUS FILTER
        // =================================================

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


        // =================================================
        // ORDER
        // =================================================

        $sql .= "
            ORDER BY id DESC
        ";


        // =================================================
        // PAGINATION
        // =================================================

        $sql .= "
            LIMIT :limit OFFSET :offset
        ";


        $stmt = $this->pdo->prepare($sql);


        // =================================================
        // BIND SEARCH/FILTER PARAMETERS
        // =================================================

        foreach ($params as $key => $value) {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }


        // =================================================
        // BIND PAGINATION
        // =================================================

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
    // COUNT TEACHERS
    // =====================================================

    public function countTeachers(
        string $search = '',
        string $status = ''
    ): int {

        $sql = "
            SELECT COUNT(*)
            FROM teachers
            WHERE 1 = 1
        ";

        $params = [];


        // =================================================
        // SEARCH
        // =================================================

        if ($search !== '') {

            $sql .= "
                AND (
                    teacher_id LIKE :count_search_teacher_id
                    OR name LIKE :count_search_name
                    OR phone LIKE :count_search_phone
                    OR email LIKE :count_search_email
                )
            ";

            $searchValue = '%' . $search . '%';

            $params[':count_search_teacher_id'] = $searchValue;
            $params[':count_search_name'] = $searchValue;
            $params[':count_search_phone'] = $searchValue;
            $params[':count_search_email'] = $searchValue;
        }


        // =================================================
        // STATUS FILTER
        // =================================================

        if ($status !== '') {

            $allowedStatuses = [
                'active',
                'inactive'
            ];

            if (in_array($status, $allowedStatuses, true)) {

                $sql .= "
                    AND status = :count_status
                ";

                $params[':count_status'] = $status;
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
    // GET SINGLE TEACHER
    // =====================================================

    public function getTeacherById(int $id): ?array
    {
        $sql = "
            SELECT
                t.id,
                t.user_id,
                t.teacher_id,
                t.name,
                t.phone,
                t.email,
                t.address,
                t.joining_date,
                t.status,
                t.created_at,
                t.updated_at

            FROM teachers AS t

            WHERE t.id = :id

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $teacher = $stmt->fetch();

        return $teacher ?: null;
    }


    // =====================================================
    // GET TEACHER BY USER ID
    // =====================================================

    public function getTeacherByUserId(int $userId): ?array
    {
        $sql = "
            SELECT
                t.id,
                t.user_id,
                t.teacher_id,
                t.name,
                t.phone,
                t.email,
                t.address,
                t.joining_date,
                t.status,
                t.created_at,
                t.updated_at

            FROM teachers AS t

            WHERE t.user_id = :user_id
              AND t.status = 'active'

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $teacher = $stmt->fetch();

        return $teacher ?: null;
    }


    // =====================================================
    // UPDATE TEACHER
    // =====================================================

    public function updateTeacher(
        int $id,
        array $data
    ): bool {

        $started = false;

        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }

        try {

            $sql = "
                UPDATE teachers SET

                    user_id = :user_id,
                    teacher_id = :teacher_id,
                    name = :name,
                    phone = :phone,
                    email = :email,
                    address = :address,
                    joining_date = :joining_date,
                    status = :status

                WHERE id = :id
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':user_id'       => $data['user_id'],
                ':teacher_id'    => $data['teacher_id'],
                ':name'          => $data['name'],
                ':phone'         => $data['phone'],
                ':email'         => $data['email'],
                ':address'       => $data['address'],
                ':joining_date'  => $data['joining_date'],
                ':status'        => $data['status'],
                ':id'            => $id
            ]);

            // -------------------------------------------------
            // KEEP THE LINKED LOGIN ACCOUNT CONSISTENT
            // -------------------------------------------------
            //
            // teachers.email / status are the profile copy.
            // Login always reads users.email + users.status,
            // so both tables must stay in sync.
            // -------------------------------------------------

            $userId = (int) ($data['user_id'] ?? 0);

            if ($userId <= 0 && !empty($data['password'])) {

                // -------------------------------------------------
                // CREATE THE MISSING LOGIN ACCOUNT
                // -------------------------------------------------
                //
                // Legacy teacher records were created without a
                // users row, so the teacher could never log in.
                // The edit form requires an initial password in
                // that case and the account is created here,
                // inside the same transaction.
                // -------------------------------------------------

                $createStmt = $this->pdo->prepare(
                    "INSERT INTO users
                        (name, email, password, role, status)
                     VALUES
                        (:name, :email, :password, 'teacher', :status)"
                );

                $createStmt->execute([
                    ':name'     => $data['name'],
                    ':email'    => $data['email'],
                    ':password' => password_hash(
                        (string) $data['password'],
                        PASSWORD_DEFAULT
                    ),
                    ':status'   => $data['status'],
                ]);

                $userId = (int) $this->pdo->lastInsertId();

                if ($userId > 0) {

                    $linkStmt = $this->pdo->prepare(
                        "UPDATE teachers
                         SET user_id = :user_id
                         WHERE id = :id"
                    );

                    $linkStmt->execute([
                        ':user_id' => $userId,
                        ':id'      => $id,
                    ]);
                }
            }

            if ($userId > 0) {

                $userSql = "
                    UPDATE users SET

                        name = :name,
                        email = :email,
                        status = :status

                ";

                $params = [
                    ':name'   => $data['name'],
                    ':email'  => $data['email'],
                    ':status' => $data['status'],
                    ':id'     => $userId,
                ];

                // -------------------------------------------------
                // OPTIONAL PASSWORD RESET
                // -------------------------------------------------

                if (!empty($data['password'])) {

                    $userSql .= ", password = :password ";

                    $params[':password'] = password_hash(
                        (string) $data['password'],
                        PASSWORD_DEFAULT
                    );
                }

                $userSql .= " WHERE id = :id ";

                $userStmt = $this->pdo->prepare($userSql);
                $userStmt->execute($params);
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
    // Academic history (attendance marked, assignments
    // created, marks entered) must never be destroyed by a
    // routine account removal.
    // =====================================================

    public function hasAcademicRecords(int $id): array
    {
        $counts = [
            'attendance'      => 0,
            'assignments'     => 0,
            'marks'           => 0,
        ];

        // Attendance the teacher marked
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM attendance WHERE marked_by = :id"
            );
            $stmt->execute([':id' => $id]);
            $counts['attendance'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Teacher dependent check (attendance): ' . $e->getMessage());
        }

        // Assignments the teacher created
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM assignments WHERE teacher_id = :id"
            );
            $stmt->execute([':id' => $id]);
            $counts['assignments'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Teacher dependent check (assignments): ' . $e->getMessage());
        }

        // Marks the teacher entered (through the linked login account)
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM marks m
                 INNER JOIN teachers t ON t.user_id = m.created_by
                 WHERE t.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $counts['marks'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Teacher dependent check (marks): ' . $e->getMessage());
        }

        return $counts;
    }


    // =====================================================
    // DEACTIVATE TEACHER (soft delete)
    // =====================================================
    // Keeps teachers, assignments, attendance and marks
    // intact, but stops the account from logging in.
    // =====================================================

    public function deactivateTeacher(int $id): bool
    {
        $checkStmt = $this->pdo->prepare(
            "SELECT id, user_id, status
             FROM teachers
             WHERE id = :id
             LIMIT 1"
        );

        $checkStmt->execute([':id' => $id]);

        $teacher = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            return false;
        }

        $started = false;

        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }

        try {

            $stmt = $this->pdo->prepare(
                "UPDATE teachers
                 SET status = 'inactive',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );

            $stmt->execute([':id' => $id]);

            $userId = (int) ($teacher['user_id'] ?? 0);

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
    // DELETE TEACHER (permanent)
    // =====================================================
    // Only called by the admin UI after
    // hasAcademicRecords() confirmed there is no academic
    // history yet. The linked users row (login account) is
    // removed with it so no orphaned account remains.
    // =====================================================

    public function deleteTeacher(int $id): bool
    {
        // Check teacher exists
        $checkSql = "
            SELECT id, user_id
            FROM teachers
            WHERE id = :id
            LIMIT 1
        ";

        $checkStmt = $this->pdo->prepare($checkSql);

        $checkStmt->execute([
            ':id' => $id
        ]);

        $teacher = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            return false;
        }


        // =================================================
        // REFUSE TO DESTROY ACADEMIC HISTORY
        // =================================================

        foreach ($this->hasAcademicRecords($id) as $table => $count) {

            if ($count > 0) {

                throw new RuntimeException(
                    'This teacher already has academic records ('
                    . $table . ' = ' . $count
                    . ') and cannot be permanently deleted. '
                    . 'Deactivate the account instead.'
                );
            }
        }


        // Start transaction
        $this->pdo->beginTransaction();

        try {

            /*
             * teacher_subjects, teacher_classes,
             * result_settings and assignments follow their
             * ON DELETE CASCADE foreign-key rules and are
             * removed together with the teacher.
             */

            $sql = "
                DELETE FROM teachers
                WHERE id = :id
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':id' => $id
            ]);


            // Remove the orphaned login account as well.
            $userId = (int) ($teacher['user_id'] ?? 0);

            if ($userId > 0) {

                $userStmt = $this->pdo->prepare(
                    "DELETE FROM users
                     WHERE id = :id
                     AND role = 'teacher'"
                );

                $userStmt->execute([':id' => $userId]);
            }


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