<?php

declare(strict_types=1);

class Attendance
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
    // CHECK ATTENDANCE EXISTS
    // =====================================================

    public function attendanceExists(
        int $studentId,
        ?int $subjectId,
        string $date
    ): bool {

        $sql = "
            SELECT id
            FROM attendance
            WHERE student_id = :student_id
              AND date = :date
        ";

        $params = [
            ':student_id' => $studentId,
            ':date'       => $date
        ];


        if ($subjectId !== null) {

            $sql .= "
                AND subject_id = :subject_id
            ";

            $params[':subject_id'] = $subjectId;

        } else {

            $sql .= "
                AND subject_id IS NULL
            ";
        }


        $sql .= " LIMIT 1";


        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }


    // =====================================================
    // ADD ATTENDANCE
    // =====================================================

    public function addAttendance(array $data): bool
    {
        $subjectId = null;

        if (
            isset($data['subject_id']) &&
            $data['subject_id'] !== ''
        ) {
            $subjectId = (int) $data['subject_id'];
        }


        if (
            $this->attendanceExists(
                (int) $data['student_id'],
                $subjectId,
                $data['date']
            )
        ) {
            return false;
        }


        $sql = "
            INSERT INTO attendance
            (
                student_id,
                class_id,
                section_id,
                subject_id,
                date,
                status,
                marked_by
            )
            VALUES
            (
                :student_id,
                :class_id,
                :section_id,
                :subject_id,
                :date,
                :status,
                :marked_by
            )
        ";


        $stmt = $this->pdo->prepare($sql);


        return $stmt->execute([
            ':student_id' => $data['student_id'],
            ':class_id'   => $data['class_id'],
            ':section_id' => $data['section_id'],
            ':subject_id' => $subjectId,
            ':date'       => $data['date'],
            ':status'     => $data['status'],
            ':marked_by'  => $data['marked_by']
        ]);
    }


    // =====================================================
    // GET ATTENDANCE BY ID
    // =====================================================

    public function getAttendanceById(
        int $id
    ): ?array {

        $sql = "
            SELECT
                a.id,
                a.student_id,
                a.class_id,
                a.section_id,
                a.subject_id,
                a.date,
                a.status,
                a.marked_by,
                a.created_at,

                s.student_id AS student_code,
                s.name AS student_name,
                s.father_name,

                c.name AS class_name,

                sec.name AS section_name,

                sub.name AS subject_name,
                sub.code AS subject_code,

                t.teacher_id AS teacher_code,
                t.name AS teacher_name

            FROM attendance AS a

            INNER JOIN students AS s
                ON a.student_id = s.id

            INNER JOIN classes AS c
                ON a.class_id = c.id

            LEFT JOIN sections AS sec
                ON a.section_id = sec.id

            LEFT JOIN subjects AS sub
                ON a.subject_id = sub.id

            LEFT JOIN teachers AS t
                ON a.marked_by = t.id

            WHERE a.id = :id

            LIMIT 1
        ";


        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);


        $attendance = $stmt->fetch();

        return $attendance ?: null;
    }


    // =====================================================
    // GET ATTENDANCE RECORDS
    // =====================================================

    public function getAttendance(
        string $search = '',
        string $status = '',
        ?int $classId = null,
        ?int $sectionId = null,
        ?int $subjectId = null,
        string $date = '',
        int $limit = 10,
        int $offset = 0
    ): array {

        /*
        | Without a search term the descriptive joins are pure display
        | decoration. Selecting them after the page of attendance rows has
        | been chosen keeps the query bounded by LIMIT/OFFSET instead of
        | joining and sorting every record in the table.
        |
        | The student/class joins stay INNER so an orphaned attendance row
        | is still never displayed.
        */
        $needsSearchJoins = ($search !== '');

        if ($needsSearchJoins) {

            $sql = "
                SELECT
                    a.id,
                    a.student_id,
                    a.class_id,
                    a.section_id,
                    a.subject_id,
                    a.date,
                    a.status,
                    a.marked_by,
                    a.created_at,

                    s.student_id AS student_code,
                    s.name AS student_name,

                    c.name AS class_name,

                    sec.name AS section_name,

                    sub.name AS subject_name,
                    sub.code AS subject_code,

                    t.name AS teacher_name

                FROM attendance AS a

                INNER JOIN students AS s
                    ON a.student_id = s.id

                INNER JOIN classes AS c
                    ON a.class_id = c.id

                LEFT JOIN sections AS sec
                    ON a.section_id = sec.id

                LEFT JOIN subjects AS sub
                    ON a.subject_id = sub.id

                LEFT JOIN teachers AS t
                    ON a.marked_by = t.id

                WHERE 1 = 1
            ";

        } else {

            $sql = "
                SELECT
                    a.id,
                    a.student_id,
                    a.class_id,
                    a.section_id,
                    a.subject_id,
                    a.date,
                    a.status,
                    a.marked_by,
                    a.created_at,

                    s.student_id AS student_code,
                    s.name AS student_name,

                    c.name AS class_name,

                    sec.name AS section_name,

                    sub.name AS subject_name,
                    sub.code AS subject_code,

                    t.name AS teacher_name

                FROM (
                    SELECT
                        id,
                        student_id,
                        class_id,
                        section_id,
                        subject_id,
                        date,
                        status,
                        marked_by,
                        created_at

                    FROM attendance AS a

                    WHERE 1 = 1
        ";
        }


        $params = [];


        // =================================================
        // SEARCH
        // =================================================

        if ($search !== '') {

            $sql .= "
                AND (
                    s.student_id LIKE :search_student_id
                    OR s.name LIKE :search_student_name
                    OR c.name LIKE :search_class
                    OR sec.name LIKE :search_section
                    OR sub.name LIKE :search_subject
                )
            ";


            $searchValue = '%' . $search . '%';


            $params[':search_student_id'] = $searchValue;
            $params[':search_student_name'] = $searchValue;
            $params[':search_class'] = $searchValue;
            $params[':search_section'] = $searchValue;
            $params[':search_subject'] = $searchValue;
        }


        // =================================================
        // STATUS FILTER
        // =================================================

        if ($status !== '') {

            $allowedStatuses = [
                'present',
                'absent',
                'late',
                'leave'
            ];


            if (
                in_array(
                    $status,
                    $allowedStatuses,
                    true
                )
            ) {

                $sql .= "
                    AND a.status = :status
                ";

                $params[':status'] = $status;
            }
        }


        // =================================================
        // CLASS FILTER
        // =================================================

        if ($classId !== null) {

            $sql .= "
                AND a.class_id = :class_id
            ";

            $params[':class_id'] = $classId;
        }


        // =================================================
        // SECTION FILTER
        // =================================================

        if ($sectionId !== null) {

            $sql .= "
                AND a.section_id = :section_id
            ";

            $params[':section_id'] = $sectionId;
        }


        // =================================================
        // SUBJECT FILTER
        // =================================================

        if ($subjectId !== null) {

            $sql .= "
                AND a.subject_id = :subject_id
            ";

            $params[':subject_id'] = $subjectId;
        }


        // =================================================
        // DATE FILTER
        // =================================================

        if ($date !== '') {

            $sql .= "
                AND a.date = :date
            ";

            $params[':date'] = $date;
        }


        // =================================================
        // PAGINATION
        // =================================================

        if (!$needsSearchJoins) {

            // close the derived page and attach the display columns
            $sql .= "
                    ORDER BY date DESC, id DESC
                    LIMIT :limit OFFSET :offset
                ) AS a

                INNER JOIN students AS s
                    ON a.student_id = s.id

                INNER JOIN classes AS c
                    ON a.class_id = c.id

                LEFT JOIN sections AS sec
                    ON a.section_id = sec.id

                LEFT JOIN subjects AS sub
                    ON a.subject_id = sub.id

                LEFT JOIN teachers AS t
                    ON a.marked_by = t.id
            ";

        } else {

            $sql .= "
                ORDER BY a.date DESC, a.id DESC
                LIMIT :limit OFFSET :offset
            ";
        }


        $stmt = $this->pdo->prepare($sql);


        foreach ($params as $key => $value) {

            if (
                $key === ':class_id' ||
                $key === ':section_id' ||
                $key === ':subject_id'
            ) {

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
    // COUNT ATTENDANCE
    // =====================================================

    public function countAttendance(
        string $search = '',
        string $status = '',
        ?int $classId = null,
        ?int $sectionId = null,
        ?int $subjectId = null,
        string $date = ''
    ): int {

        /*
        | These joins only exist to support the free-text search. When no
        | search term is supplied they forced a four-table join across the
        | whole attendance table just to count rows, which dominated the
        | page's response time at a few hundred thousand records.
        */
        $needsSearchJoins = ($search !== '');

        $sql = "
            SELECT COUNT(*)

            FROM attendance AS a
        ";

        if ($needsSearchJoins) {

            $sql .= "

                INNER JOIN students AS s
                    ON a.student_id = s.id

                INNER JOIN classes AS c
                    ON a.class_id = c.id

                LEFT JOIN sections AS sec
                    ON a.section_id = sec.id

                LEFT JOIN subjects AS sub
                    ON a.subject_id = sub.id
            ";
        }

        $sql .= "

            WHERE 1 = 1
        ";


        $params = [];


        // =================================================
        // SEARCH
        // =================================================

        if ($search !== '') {

            $sql .= "
                AND (
                    s.student_id LIKE :search_student_id
                    OR s.name LIKE :search_student_name
                    OR c.name LIKE :search_class
                    OR sec.name LIKE :search_section
                    OR sub.name LIKE :search_subject
                )
            ";


            $searchValue = '%' . $search . '%';


            $params[':search_student_id'] = $searchValue;
            $params[':search_student_name'] = $searchValue;
            $params[':search_class'] = $searchValue;
            $params[':search_section'] = $searchValue;
            $params[':search_subject'] = $searchValue;
        }


        // =================================================
        // STATUS
        // =================================================

        if ($status !== '') {

            $allowedStatuses = [
                'present',
                'absent',
                'late',
                'leave'
            ];


            if (
                in_array(
                    $status,
                    $allowedStatuses,
                    true
                )
            ) {

                $sql .= "
                    AND a.status = :status
                ";

                $params[':status'] = $status;
            }
        }


        // =================================================
        // CLASS
        // =================================================

        if ($classId !== null) {

            $sql .= "
                AND a.class_id = :class_id
            ";

            $params[':class_id'] = $classId;
        }


        // =================================================
        // SECTION
        // =================================================

        if ($sectionId !== null) {

            $sql .= "
                AND a.section_id = :section_id
            ";

            $params[':section_id'] = $sectionId;
        }


        // =================================================
        // SUBJECT
        // =================================================

        if ($subjectId !== null) {

            $sql .= "
                AND a.subject_id = :subject_id
            ";

            $params[':subject_id'] = $subjectId;
        }


        // =================================================
        // DATE
        // =================================================

        if ($date !== '') {

            $sql .= "
                AND a.date = :date
            ";

            $params[':date'] = $date;
        }


        $stmt = $this->pdo->prepare($sql);


        foreach ($params as $key => $value) {

            if (
                $key === ':class_id' ||
                $key === ':section_id' ||
                $key === ':subject_id'
            ) {

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
    // UPDATE ATTENDANCE
    // =====================================================

    public function updateAttendance(int $id, array $data): bool
{
    $sql = "
        UPDATE attendance
        SET
            date = :date,
            status = :status
        WHERE id = :id
    ";

    $stmt = $this->pdo->prepare($sql);

    return $stmt->execute([
        ':date' => $data['date'],
        ':status' => $data['status'],
        ':id' => $id
    ]);
}

    // =====================================================
    // DELETE ATTENDANCE
    // =====================================================

    public function deleteAttendance(
        int $id
    ): bool {

        $checkSql = "
            SELECT id
            FROM attendance
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


        $sql = "
            DELETE FROM attendance
            WHERE id = :id
        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([
            ':id' => $id
        ]);


        return $stmt->rowCount() > 0;
    }


    // =====================================================
    // GET STUDENTS FOR ATTENDANCE
    // =====================================================

    public function getStudentsForAttendance(
        int $classId,
        ?int $sectionId
    ): array {

        // ==========================================
        // CASE 1: CLASS HAS NO SECTION
        // ==========================================

        if ($sectionId === null) {

            $sql = "
                SELECT
                    s.id,
                    s.student_id,
                    s.name,
                    s.father_name,
                    s.class_id,
                    s.section_id,

                    c.name AS class_name

                FROM students AS s

                INNER JOIN classes AS c
                    ON s.class_id = c.id

                WHERE s.class_id = :class_id
                  AND s.section_id IS NULL
                  AND s.status = 'active'

                ORDER BY s.name ASC
            ";


            $stmt = $this->pdo->prepare($sql);


            $stmt->execute([
                ':class_id' => $classId
            ]);


            return $stmt->fetchAll();
        }


        // ==========================================
        // CASE 2: CLASS WITH SECTION
        // ==========================================

        $sql = "
            SELECT
                s.id,
                s.student_id,
                s.name,
                s.father_name,
                s.class_id,
                s.section_id,

                c.name AS class_name,
                sec.name AS section_name

            FROM students AS s

            INNER JOIN classes AS c
                ON s.class_id = c.id

            INNER JOIN sections AS sec
                ON s.section_id = sec.id

            WHERE s.class_id = :class_id
              AND s.section_id = :section_id
              AND s.status = 'active'

            ORDER BY s.name ASC
        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([
            ':class_id'   => $classId,
            ':section_id' => $sectionId
        ]);


        return $stmt->fetchAll();
    }


    // =====================================================
    // SAVE MULTIPLE ATTENDANCE RECORDS
    // =====================================================

    public function saveAttendanceBatch(
        int $classId,
        ?int $sectionId,
        ?int $subjectId,
        string $date,
        array $students,
        ?int $markedBy
    ): bool {

        try {

            $this->pdo->beginTransaction();


            // ==========================================
            // CHECK EACH STUDENT
            // ==========================================

            foreach ($students as $studentId => $status) {

                $studentId = (int) $studentId;


                // ======================================
                // SECTION-LESS CLASS
                // ======================================

                if ($sectionId === null) {

                    $sql = "
                        SELECT id
                        FROM students
                        WHERE id = :student_id
                          AND class_id = :class_id
                          AND section_id IS NULL
                          AND status = 'active'
                        LIMIT 1
                    ";


                    $stmt = $this->pdo->prepare($sql);


                    $stmt->execute([
                        ':student_id' => $studentId,
                        ':class_id'  => $classId
                    ]);

                }


                // ======================================
                // CLASS WITH SECTION
                // ======================================

                else {

                    $sql = "
                        SELECT id
                        FROM students
                        WHERE id = :student_id
                          AND class_id = :class_id
                          AND section_id = :section_id
                          AND status = 'active'
                        LIMIT 1
                    ";


                    $stmt = $this->pdo->prepare($sql);


                    $stmt->execute([
                        ':student_id' => $studentId,
                        ':class_id'  => $classId,
                        ':section_id' => $sectionId
                    ]);
                }


                // ======================================
                // STUDENT INVALID
                // ======================================

                if ($stmt->fetch() === false) {

                    throw new Exception(
                        "Invalid student selected for this class/section."
                    );
                }


                // ======================================
                // VALIDATE STATUS
                // ======================================

                $allowedStatuses = [
                    'present',
                    'absent',
                    'late',
                    'leave'
                ];


                if (
                    !in_array(
                        $status,
                        $allowedStatuses,
                        true
                    )
                ) {

                    throw new Exception(
                        "Invalid attendance status."
                    );
                }


                // ======================================
                // DUPLICATE CHECK
                // ======================================

                if (
                    $this->attendanceExists(
                        $studentId,
                        $subjectId,
                        $date
                    )
                ) {

                    throw new Exception(
                        "Attendance already exists for one of the selected students on this date."
                    );
                }
            }


            // ==========================================
            // INSERT ATTENDANCE
            // ==========================================

            $sql = "
                INSERT INTO attendance
                (
                    student_id,
                    class_id,
                    section_id,
                    subject_id,
                    date,
                    status,
                    marked_by
                )
                VALUES
                (
                    :student_id,
                    :class_id,
                    :section_id,
                    :subject_id,
                    :date,
                    :status,
                    :marked_by
                )
            ";


            $stmt = $this->pdo->prepare($sql);


            foreach ($students as $studentId => $status) {

                $stmt->execute([
                    ':student_id' => (int) $studentId,
                    ':class_id'   => $classId,
                    ':section_id' => $sectionId,
                    ':subject_id' => $subjectId,
                    ':date'       => $date,
                    ':status'     => $status,
                    ':marked_by'  => $markedBy
                ]);
            }


            // ==========================================
            // COMMIT
            // ==========================================

            $this->pdo->commit();


            return true;


        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }


            throw $e;
        }
    }


    // =====================================================
    // DAILY ATTENDANCE REPORT
    // =====================================================

    public function getDailyAttendance(
        int $studentId,
        string $date
    ): array {

        $sql = "
            SELECT
                a.id,
                a.student_id,
                a.class_id,
                a.section_id,
                a.subject_id,
                a.date,
                a.status,
                a.marked_by,

                s.student_id AS student_code,
                s.name AS student_name,
                s.father_name,

                c.name AS class_name,

                sec.name AS section_name,

                sub.name AS subject_name,
                sub.code AS subject_code

            FROM attendance AS a

            INNER JOIN students AS s
                ON a.student_id = s.id

            INNER JOIN classes AS c
                ON a.class_id = c.id

            LEFT JOIN sections AS sec
                ON a.section_id = sec.id

            LEFT JOIN subjects AS sub
                ON a.subject_id = sub.id

            WHERE a.student_id = :student_id
              AND a.date = :date

            ORDER BY a.id ASC
        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([
            ':student_id' => $studentId,
            ':date'       => $date
        ]);


        return $stmt->fetchAll();
    }


    // =====================================================
    // WEEKLY ATTENDANCE REPORT
    // =====================================================

    public function getWeeklyAttendance(
        int $studentId,
        string $startDate,
        string $endDate
    ): array {

        $sql = "
            SELECT
                a.id,
                a.student_id,
                a.class_id,
                a.section_id,
                a.subject_id,
                a.date,
                a.status,

                c.name AS class_name,

                sec.name AS section_name,

                sub.name AS subject_name,
                sub.code AS subject_code

            FROM attendance AS a

            INNER JOIN classes AS c
                ON a.class_id = c.id

            LEFT JOIN sections AS sec
                ON a.section_id = sec.id

            LEFT JOIN subjects AS sub
                ON a.subject_id = sub.id

            WHERE a.student_id = :student_id
              AND a.date BETWEEN :start_date AND :end_date

            ORDER BY a.date ASC, a.id ASC
        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([
            ':student_id' => $studentId,
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);


        return $stmt->fetchAll();
    }


    // =====================================================
    // MONTHLY ATTENDANCE REPORT
    // =====================================================

    public function getMonthlyAttendance(
        int $studentId,
        string $startDate,
        string $endDate
    ): array {

        $sql = "
            SELECT
                a.id,
                a.student_id,
                a.class_id,
                a.section_id,
                a.subject_id,
                a.date,
                a.status,

                c.name AS class_name,

                sec.name AS section_name,

                sub.name AS subject_name,
                sub.code AS subject_code

            FROM attendance AS a

            INNER JOIN classes AS c
                ON a.class_id = c.id

            LEFT JOIN sections AS sec
                ON a.section_id = sec.id

            LEFT JOIN subjects AS sub
                ON a.subject_id = sub.id

            WHERE a.student_id = :student_id
              AND a.date BETWEEN :start_date AND :end_date

            ORDER BY a.date ASC, a.id ASC
        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([
            ':student_id' => $studentId,
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);


        return $stmt->fetchAll();
    }


    // =====================================================
    // TOTAL ATTENDANCE SUMMARY
    // =====================================================

    public function getAttendanceSummary(
        int $studentId
    ): array {

        $sql = "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'present'
                        THEN 1
                        ELSE 0
                    END
                ) AS present,

                SUM(
                    CASE
                        WHEN status = 'absent'
                        THEN 1
                        ELSE 0
                    END
                ) AS absent,

                SUM(
                    CASE
                        WHEN status = 'late'
                        THEN 1
                        ELSE 0
                    END
                ) AS late,

                SUM(
                    CASE
                        WHEN status = 'leave'
                        THEN 1
                        ELSE 0
                    END
                ) AS leave_count

            FROM attendance

            WHERE student_id = :student_id
        ";


        $stmt = $this->pdo->prepare($sql);


        $stmt->execute([
            ':student_id' => $studentId
        ]);


        $result = $stmt->fetch();


        if (!$result) {

            return [
                'total'       => 0,
                'present'     => 0,
                'absent'      => 0,
                'late'        => 0,
                'leave_count' => 0,
                'percentage'  => 0
            ];
        }


        $total =
            (int) $result['total'];


        $present =
            (int) $result['present'];


        $percentage =
            $total > 0
                ? round(
                    ($present / $total) * 100,
                    2
                )
                : 0;


        return [
            'total'       => $total,
            'present'     => $present,
            'absent'      => (int) $result['absent'],
            'late'        => (int) $result['late'],
            'leave_count' => (int) $result['leave_count'],
            'percentage'  => $percentage
        ];
    }
}