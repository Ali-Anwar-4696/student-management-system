<?php

declare(strict_types=1);

class Auth
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    // =====================================================
    // REGISTER NEW USER
    // =====================================================

    /**
     * Register a new user.
     *
     * Student registration:
     *
     * users.status   = active
     * students.status = pending
     *
     * Student must be approved by admin before
     * accessing the normal student dashboard.
     *
     * Father name is optional here because the current
     * students table requires father_name NOT NULL.
     * Therefore an empty string is stored when not provided.
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $role = 'student',
        string $fatherName = ''
    ): array {

        $name = trim($name);
        $email = strtolower(trim($email));
        $fatherName = trim($fatherName);


        // =================================================
        // VALIDATION
        // =================================================

        if ($name === '') {

            return [
                'success' => false,
                'message' => 'Name is required.',
            ];
        }


        if ($email === '') {

            return [
                'success' => false,
                'message' => 'Email is required.',
            ];
        }


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            return [
                'success' => false,
                'message' => 'Please enter a valid email address.',
            ];
        }


        if (strlen($password) < 6) {

            return [
                'success' => false,
                'message' => 'Password must be at least 6 characters.',
            ];
        }


        if (strlen($name) > 100) {

            return [
                'success' => false,
                'message' => 'Name must not exceed 100 characters.',
            ];
        }


        if (strlen($fatherName) > 100) {

            return [
                'success' => false,
                'message' => 'Father name must not exceed 100 characters.',
            ];
        }


        /*
         * Public registration may only create student accounts.
         * Admin and teacher accounts are created by administrators.
         */
        if ($role !== 'student') {
            return [
                'success' => false,
                'message' => 'Invalid user role.',
            ];
        }


        // =================================================
        // CHECK DUPLICATE EMAIL
        // =================================================

        try {

            $stmt = $this->pdo->prepare(
                'SELECT id
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );

            $stmt->execute([
                ':email' => $email,
            ]);


            if ($stmt->fetch()) {

                return [
                    'success' => false,
                    'message' => 'An account with this email already exists.',
                ];
            }


            // =================================================
            // START TRANSACTION
            // =================================================

            $this->pdo->beginTransaction();


            // =================================================
            // HASH PASSWORD
            // =================================================

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // =================================================
            // CREATE USER ACCOUNT
            // =================================================

            $stmt = $this->pdo->prepare(
                'INSERT INTO users
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
                        :role,
                        :status
                    )'
            );


            $stmt->execute([
                ':name'     => $name,
                ':email'    => $email,
                ':password' => $hashedPassword,
                ':role'     => $role,

                /*
                 * Login account remains active.
                 *
                 * Student approval is controlled by
                 * students.status, NOT users.status.
                 */
                ':status'   => 'active',
            ]);


            $userId = (int) $this->pdo->lastInsertId();


            // =================================================
            // CREATE STUDENT PROFILE
            // =================================================

            $studentId = null;


            if ($role === 'student') {

                $studentId = $this->generateStudentId();


                $stmt = $this->pdo->prepare(
                    'INSERT INTO students
                        (
                            user_id,
                            student_id,
                            name,
                            father_name,
                            email,
                            admission_date,
                            status
                        )
                     VALUES
                        (
                            :user_id,
                            :student_id,
                            :name,
                            :father_name,
                            :email,
                            :admission_date,
                            :status
                        )'
                );


                $stmt->execute([
                    ':user_id'        => $userId,
                    ':student_id'     => $studentId,
                    ':name'           => $name,

                    /*
                     * students.father_name is NOT NULL.
                     */
                    ':father_name'    => $fatherName,

                    ':email'          => $email,
                    ':admission_date' => date('Y-m-d'),

                    /*
                     * IMPORTANT:
                     * Newly registered students are pending.
                     */
                    ':status'         => 'pending',
                ]);
            }


            // =================================================
            // COMMIT
            // =================================================

            $this->pdo->commit();


            return [
                'success'    => true,
                'message'    => 'Registration successful. Your account is waiting for admin approval.',
                'user_id'    => $userId,
                'student_id' => $studentId,
                'status'     => $role === 'student'
                    ? 'pending'
                    : 'active',
            ];


        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {

                $this->pdo->rollBack();
            }


            error_log(
                'Registration Error: ' .
                $e->getMessage()
            );


            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.',
            ];
        }
    }


    // =====================================================
    // PARENT ACCOUNTS (admin-initiated)
    // =====================================================

    /**
     * Look up a user row by email (any role).
     * Returns null when the email is unknown.
     */
    public function findUserByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, role, status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([':email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Create a parent login account (role = 'parent') on behalf of an
     * administrator linking a student on the edit screen.
     *
     * Mirrors register()'s validation, but the role is fixed to
     * 'parent' here — this method is never reachable from the public
     * registration form.
     */
    public function createParentAccount(
        string $name,
        string $email,
        string $password
    ): array {

        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '') {
            return [
                'success' => false,
                'message' => 'Parent name is required.',
            ];
        }

        if ($email === '') {
            return [
                'success' => false,
                'message' => 'Parent email is required.',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Please enter a valid parent email address.',
            ];
        }

        if (strlen($password) < 6) {
            return [
                'success' => false,
                'message' => 'Parent password must be at least 6 characters.',
            ];
        }

        if (strlen($name) > 100) {
            return [
                'success' => false,
                'message' => 'Parent name must not exceed 100 characters.',
            ];
        }

        try {

            $existing = $this->findUserByEmail($email);

            if ($existing !== null) {
                return [
                    'success' => false,
                    'message' => 'An account with this email already exists.',
                ];
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO users
                    (name, email, password, role, status)
                 VALUES
                    (:name, :email, :password, :role, :status)'
            );

            $stmt->execute([
                ':name'     => $name,
                ':email'    => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':role'     => 'parent',
                ':status'   => 'active',
            ]);

            return [
                'success' => true,
                'message' => 'Parent account created.',
                'user_id' => (int) $this->pdo->lastInsertId(),
            ];

        } catch (Throwable $e) {

            error_log('Parent account creation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Could not create the parent account. Please try again.',
            ];
        }
    }


    // =====================================================
    // LOGIN (the single centralized authentication service)
    // =====================================================

    /**
     * Authenticate email + password.
     *
     * This is the ONE authentication path used by every login
     * entry point (main login and role-specific login pages).
     *
     * Security properties:
     *
     *  - login throttling via login_is_locked() / login_register_failure();
     *  - password_hash() + password_verify() only — plaintext is never
     *    stored, compared or logged;
     *  - the password is verified BEFORE any account state (e.g. an
     *    inactive status) is revealed, so wrong-password attempts on
     *    unknown or disabled accounts cannot be used to probe the user
     *    table;
     *  - one generic failure message ("Invalid email or password.") for
     *    unknown email, wrong password and role mismatch;
     *  - the role is read from the DATABASE (users.role). No request
     *    parameter can influence it;
     *  - $expectedRole (optional) lets a role-specific login page
     *    RESTRICT which role may pass through it. It can only ever
     *    DENY — it can never grant a role the database does not have,
     *    so tampering with the entry parameter cannot escalate
     *    privileges;
     *  - the session ID is regenerated (session_regenerate_id(true))
     *    before any identity is written to the session (fixation
     *    defence), and role-specific session context is derived
     *    server-side.
     *
     * users.status controls whether the login account itself is
     * enabled; students.status controls student approval (a pending
     * student may still log in and is routed to the pending state).
     *
     * MFA hook point (future): a completed second factor for
     * privileged roles (admin) would be enforced here — after password
     * verification and BEFORE the session is established. The reserved
     * config flag is admin_mfa_enabled in config/app.php. Do not ship
     * a partial MFA flow.
     */
    public function login(
        string $email,
        string $password,
        ?string $expectedRole = null
    ): array {

        $email = strtolower(trim($email));


        if ($email === '' || $password === '') {

            return [
                'success' => false,
                'message' => 'Email and password are required.',
            ];
        }

        if (function_exists('login_is_locked') && login_is_locked($email)) {
            return [
                'success' => false,
                'message' => 'Too many login attempts. Please wait and try again.',
            ];
        }


        $stmt = $this->pdo->prepare(
            'SELECT
                id,
                name,
                email,
                password,
                role,
                status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );


        $stmt->execute([
            ':email' => $email,
        ]);


        $user = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$user) {
            if (function_exists('login_register_failure')) {
                login_register_failure($email);
            }

            return [
                'success' => false,
                'message' => 'Invalid email or password.',
            ];
        }


        // =================================================
        // PASSWORD
        //
        // Verified FIRST: the response must not reveal
        // anything about the account (existence or status)
        // until the caller has proven the password.
        // =================================================

        if (!password_verify($password, $user['password'])) {
            if (function_exists('login_register_failure')) {
                login_register_failure($email);
            }

            return [
                'success' => false,
                'message' => 'Invalid email or password.',
            ];
        }


        // =================================================
        // USER ACCOUNT STATUS
        // =================================================

        if ($user['status'] !== 'active') {

            return [
                'success' => false,
                'message' => 'Your account is inactive. Please contact administration.',
            ];
        }


        // =================================================
        // KNOWN ROLE (users.role enum whitelist)
        // =================================================

        $role = (string) $user['role'];

        if (!in_array($role, ['admin', 'teacher', 'student', 'parent'], true)) {

            return [
                'success' => false,
                'message' => 'Invalid account role.',
            ];
        }


        // =================================================
        // ROLE-SPECIFIC LOGIN PAGE RESTRICTION
        //
        // A role-specific entry page may only RESTRICT.
        // A mismatch produces the same generic failure as a
        // wrong password — it never reveals which role the
        // account actually holds, and it never logs the user
        // in. The session is not touched on this path.
        // =================================================

        if ($expectedRole !== null && $expectedRole !== $role) {
            if (function_exists('login_register_failure')) {
                login_register_failure($email);
            }

            return [
                'success' => false,
                'message' => 'Invalid email or password.',
            ];
        }


        // =================================================
        // TEACHER PROFILE (server-side check + context)
        // =================================================

        $teacherProfile = null;

        if ($role === 'teacher') {

            $stmt = $this->pdo->prepare(
                'SELECT id, teacher_id, name, status
                 FROM teachers
                 WHERE user_id = :user_id
                 LIMIT 1'
            );

            $stmt->execute([
                ':user_id' => (int) $user['id'],
            ]);

            $teacherProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (
                $teacherProfile === null ||
                $teacherProfile['status'] !== 'active'
            ) {

                return [
                    'success' => false,
                    'message' => 'Your teacher account is inactive or not linked. Contact administration.',
                ];
            }
        }


        // =================================================
        // SESSION
        // =================================================

        if (session_status() === PHP_SESSION_NONE) {

            session_start();
        }


        session_regenerate_id(true);


        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];


        // Clear any earlier failed-attempt counters for this key.
        if (function_exists('login_register_success')) {
            login_register_success($email);
        }


        // =================================================
        // TEACHER SESSION CONTEXT
        //
        // Derived from the database row, never from the
        // request. Pages such as teacher/exams.php and
        // teacher/marks.php read teacher_profile_id.
        // =================================================

        if ($role === 'teacher' && $teacherProfile !== null) {

            $_SESSION['teacher_profile_id'] = (int) $teacherProfile['id'];
            $_SESSION['teacher_record_id'] = (int) $teacherProfile['id'];
            $_SESSION['teacher_id'] = (string) $teacherProfile['teacher_id'];
            $_SESSION['teacher_name'] = (string) $teacherProfile['name'];
        }


        // =================================================
        // STUDENT PROFILE
        // =================================================

        $studentStatus = null;
        $studentId = null;


        if ($user['role'] === 'student') {

            /*
             * Make sure old student accounts have a
             * linked student profile.
             *
             * IMPORTANT:
             * Newly created profile becomes PENDING,
             * never ACTIVE automatically.
             */
            $this->ensureStudentProfile(
                (int) $user['id'],
                (string) $user['name'],
                (string) $user['email']
            );


            // =============================================
            // GET STUDENT STATUS
            // =============================================

            $stmt = $this->pdo->prepare(
                'SELECT
                    id,
                    status
                 FROM students
                 WHERE user_id = :user_id
                 LIMIT 1'
            );


            $stmt->execute([
                ':user_id' => (int) $user['id'],
            ]);


            $student = $stmt->fetch(PDO::FETCH_ASSOC);


            if ($student) {

                $studentId = (int) $student['id'];
                $studentStatus = (string) $student['status'];

                $_SESSION['student_id'] = $studentId;
                $_SESSION['student_status'] = $studentStatus;
            }
        }


        // =================================================
        // LOGIN RESPONSE
        // =================================================

        return [
            'success'        => true,
            'message'        => 'Login successful.',
            'user_id'        => (int) $user['id'],
            'name'           => $user['name'],
            'email'          => $user['email'],
            'role'           => $user['role'],
            'student_id'     => $studentId,
            'student_status' => $studentStatus,
        ];
    }


    // =====================================================
    // TEACHER LOGIN — REMOVED (duplicate authentication path)
    // =====================================================
    //
    // Auth::teacherLogin() (email + Teacher ID + password) was a
    // second, independent credential-verification path that:
    //
    //   - bypassed the shared login throttling, and
    //   - duplicated password/status verification logic.
    //
    // Teachers now authenticate through the centralized
    // Auth::login() above — like every other role — and the
    // teacher-specific login page only RESTRICTS which role may
    // pass through it (expected role = 'teacher'). Teacher IDs
    // are identifiers, not secrets, and never were a second
    // authentication factor.


    // =====================================================
    // ENSURE STUDENT PROFILE
    // =====================================================

    /**
     * Make sure an existing student user has a linked
     * students table record.
     *
     * IMPORTANT:
     * If an old student account has no student profile,
     * create it as PENDING.
     *
     * It must NOT automatically become ACTIVE.
     */
    private function ensureStudentProfile(
        int $userId,
        string $name,
        string $email
    ): void {

        $stmt = $this->pdo->prepare(
            'SELECT id
             FROM students
             WHERE user_id = :user_id
             LIMIT 1'
        );


        $stmt->execute([
            ':user_id' => $userId,
        ]);


        if ($stmt->fetch()) {

            return;
        }


        try {

            $studentId = $this->generateStudentId();


            $stmt = $this->pdo->prepare(
                'INSERT INTO students
                    (
                        user_id,
                        student_id,
                        name,
                        father_name,
                        email,
                        admission_date,
                        status
                    )
                 VALUES
                    (
                        :user_id,
                        :student_id,
                        :name,
                        :father_name,
                        :email,
                        :admission_date,
                        :status
                    )'
            );


            $stmt->execute([
                ':user_id'        => $userId,
                ':student_id'     => $studentId,
                ':name'           => trim($name),

                /*
                 * Required because students.father_name
                 * is NOT NULL.
                 */
                ':father_name'    => '',

                ':email'          => strtolower(trim($email)),
                ':admission_date' => date('Y-m-d'),

                /*
                 * IMPORTANT:
                 * Never auto-approve here.
                 */
                ':status'         => 'pending',
            ]);


        } catch (Throwable $e) {

            error_log(
                'Student Profile Creation Error: ' .
                $e->getMessage()
            );
        }
    }


    // =====================================================
    // GENERATE STUDENT ID
    // =====================================================

    /**
     * Generate a unique student ID.
     *
     * Example:
     * STD-00001
     * STD-00002
     * STD-00003
     */
    private function generateStudentId(): string
    {
        $stmt = $this->pdo->query(
            'SELECT student_id
             FROM students
             WHERE student_id LIKE "STD-%"
             ORDER BY id DESC
             LIMIT 1'
        );


        $last = $stmt->fetchColumn();


        if ($last) {

            $number = (int) preg_replace(
                '/[^0-9]/',
                '',
                (string) $last
            );

            $number++;

        } else {

            $number = 1;
        }


        do {

            $studentId = 'STD-' . str_pad(
                (string) $number,
                5,
                '0',
                STR_PAD_LEFT
            );


            $check = $this->pdo->prepare(
                'SELECT id
                 FROM students
                 WHERE student_id = :student_id
                 LIMIT 1'
            );


            $check->execute([
                ':student_id' => $studentId,
            ]);


            $exists = $check->fetchColumn();


            if ($exists) {

                $number++;
            }

        } while ($exists);


        return $studentId;
    }


    // =====================================================
    // IS LOGGED IN
    // =====================================================

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id'])
            && (int) $_SESSION['user_id'] > 0;
    }


    // =====================================================
    // LOGOUT
    // =====================================================

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {

            session_start();
        }


        $_SESSION = [];


        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();


            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'] ?? '',
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }


        session_destroy();
    }


    // =====================================================
    // REQUIRE LOGIN
    // =====================================================

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {

            header('Location: ../auth/login.php');
            exit;
        }
    }
}