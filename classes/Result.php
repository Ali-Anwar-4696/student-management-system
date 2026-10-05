<?php

declare(strict_types=1);

class Result
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $this->pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
    }


    // =====================================================
    // GET STUDENT RESULT
    // =====================================================

    public function getStudentResult(
        int $studentId,
        int $classId,
        ?int $sectionId
    ): array {

        /*
        |--------------------------------------------------------------------------
        | GET STUDENT SUBJECTS
        |--------------------------------------------------------------------------
        |
        | A subject is part of the result when either:
        |
        | 1. It is configured on an exam of the student's class
        |    (exam_subjects -> exams.class_id)
        |
        | 2. OR the student already has a saved mark for it in the
        |    authoritative `marks` table.
        |
        | Rule 2 matters: marks written by a teacher/admin must never
        | disappear from the result just because an exam subject row was
        | missing or was removed later. The `marks` table is the source
        | of truth for recorded academic marks.
        |
        */

        $subjectSql = "
            SELECT
                subject_id,
                subject_name,
                subject_code
            FROM (
                SELECT DISTINCT
                    s.id AS subject_id,
                    s.name AS subject_name,
                    s.code AS subject_code

                FROM exam_subjects AS es

                INNER JOIN subjects AS s
                    ON s.id = es.subject_id

                INNER JOIN exams AS e
                    ON es.exam_id = e.id

                WHERE e.class_id = :class_id

                  AND s.status = 'active'

                UNION

                SELECT DISTINCT
                    s.id AS subject_id,
                    s.name AS subject_name,
                    s.code AS subject_code

                FROM marks AS m

                INNER JOIN subjects AS s
                    ON s.id = m.subject_id

                INNER JOIN exams AS e
                    ON e.id = m.exam_id

                WHERE m.student_id = :student_id

                  AND e.class_id = :marks_class_id

                  AND s.status = 'active'
            ) AS student_subjects

            ORDER BY subject_name ASC
        ";

        $subjectStmt = $this->pdo->prepare($subjectSql);

        $subjectStmt->execute([
            ':class_id'        => $classId,
            ':marks_class_id'  => $classId,
            ':student_id'      => $studentId
        ]);

        $subjects = $subjectStmt->fetchAll();


        // =================================================
        // RESULT VARIABLES
        // =================================================

        $resultSubjects = [];

        $overallObtained = 0.0;
        $overallTotal = 0.0;

        $completedSubjects = 0;
        $incompleteSubjects = 0;

        $overallPassed = null;


        // =================================================
        // PROCESS EACH SUBJECT
        // =================================================

        foreach ($subjects as $subject) {

            $subjectId =
                (int) $subject['subject_id'];


            // =================================================
            // GET RESULT SETTINGS
            // =================================================

            $settings =
                $this->getSettings(
                    $classId,
                    $sectionId,
                    $subjectId
                );


            // =================================================
            // NO SETTINGS
            // =================================================

            if (!$settings) {

                // Get raw marks even without result settings
                $rawMidterm = $this->getExamPercentage($studentId, $classId, $subjectId, 'midterm');
                $rawFinal = $this->getExamPercentage($studentId, $classId, $subjectId, 'final');

                $rawMarks = [];
                if ($rawMidterm['status'] === 'complete') {
                    $rawMarks[] = [
                        'type' => 'Midterm',
                        'obtained' => $rawMidterm['obtained'],
                        'total' => $rawMidterm['total'],
                        'percentage' => $rawMidterm['percentage'],
                    ];
                }
                if ($rawFinal['status'] === 'complete') {
                    $rawMarks[] = [
                        'type' => 'Final',
                        'obtained' => $rawFinal['obtained'],
                        'total' => $rawFinal['total'],
                        'percentage' => $rawFinal['percentage'],
                    ];
                }

                $resultSubjects[] = [

                    'subject_id' =>
                        $subjectId,

                    'subject_name' =>
                        $subject['subject_name'],

                    'subject_code' =>
                        $subject['subject_code'],

                    'configured' =>
                        false,

                    'status' =>
                        'not_configured',

                    'components' => [

                        'assignment' =>
                            $this->emptyComponent(
                                'Assignments'
                            ),

                        'attendance' =>
                            $this->emptyComponent(
                                'Attendance'
                            ),

                        'midterm' =>
                            $this->emptyComponent(
                                'Midterm'
                            ),

                        'final' =>
                            $this->emptyComponent(
                                'Final'
                            )
                    ],

                    'raw_marks' =>
                        $rawMarks,

                    'total_marks' =>
                        0,

                    'obtained_marks' =>
                        0,

                    'percentage' =>
                        null,

                    'grade' =>
                        '—',

                    'grade_class' =>
                        'neutral',

                    'passed' =>
                        null,

                    'passing_percentage' =>
                        null
                ];

                $incompleteSubjects++;

                continue;
            }


            // =================================================
            // CONFIGURED TOTALS
            // =================================================

            $assignmentTotal =
                $this->safeFloat(
                    $settings['assignment_total'] ?? 0
                );

            $attendanceTotal =
                $this->safeFloat(
                    $settings['attendance_total'] ?? 0
                );

            $midtermTotal =
                $this->safeFloat(
                    $settings['midterm_total'] ?? 0
                );

            $finalTotal =
                $this->safeFloat(
                    $settings['final_total'] ?? 0
                );


            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            |
            | There is NO fixed 100 total here.
            |
            | Example:
            |
            | 10 + 20 + 30 + 50 = 110
            |
            | or:
            |
            | 15 + 25 + 20 + 40 = 100
            |
            */

            $configuredTotal =
                $assignmentTotal +
                $attendanceTotal +
                $midtermTotal +
                $finalTotal;


            // =================================================
            // ASSIGNMENTS
            // =================================================

            $assignmentRaw =
                $this->getAssignmentPercentage(
                    $studentId,
                    $classId,
                    $sectionId,
                    $subjectId
                );


            $assignmentObtained =
                $this->percentageToMarks(
                    $assignmentRaw['percentage'],
                    $assignmentTotal
                );


            // =================================================
            // ATTENDANCE
            // =================================================

            $attendanceRaw =
                $this->getAttendancePercentage(
                    $studentId,
                    $classId,
                    $sectionId,
                    $subjectId
                );


            $attendanceObtained =
                $this->percentageToMarks(
                    $attendanceRaw['percentage'],
                    $attendanceTotal
                );


            // =================================================
            // MIDTERM
            // =================================================

            $midtermRaw =
                $this->getExamPercentage(
                    $studentId,
                    $classId,
                    $subjectId,
                    'midterm'
                );


            $midtermObtained =
                $this->percentageToMarks(
                    $midtermRaw['percentage'],
                    $midtermTotal
                );


            // =================================================
            // FINAL
            // =================================================

            $finalRaw =
                $this->getExamPercentage(
                    $studentId,
                    $classId,
                    $subjectId,
                    'final'
                );


            $finalObtained =
                $this->percentageToMarks(
                    $finalRaw['percentage'],
                    $finalTotal
                );


            // =================================================
            // COMPONENT DATA
            // =================================================

            $componentData = [

                'assignment' => [

                    'label' =>
                        'Assignments',

                    'raw_percentage' =>
                        $assignmentRaw['percentage'],

                    'obtained' =>
                        $assignmentObtained,

                    'total' =>
                        $assignmentTotal,

                    'graded_count' =>
                        $assignmentRaw['graded_count'],

                    'item_count' =>
                        $assignmentRaw['item_count'],

                    'status' =>
                        $assignmentRaw['status']
                ],


                'attendance' => [

                    'label' =>
                        'Attendance',

                    'raw_percentage' =>
                        $attendanceRaw['percentage'],

                    'obtained' =>
                        $attendanceObtained,

                    'total' =>
                        $attendanceTotal,

                    'present' =>
                        $attendanceRaw['present'],

                    'absent' =>
                        $attendanceRaw['absent'],

                    'late' =>
                        $attendanceRaw['late'],

                    'leave' =>
                        $attendanceRaw['leave'],

                    'total_days' =>
                        $attendanceRaw['total_days'],

                    'status' =>
                        $attendanceRaw['status']
                ],


                'midterm' => [

                    'label' =>
                        'Midterm',

                    'raw_percentage' =>
                        $midtermRaw['percentage'],

                    'obtained' =>
                        $midtermObtained,

                    'total' =>
                        $midtermTotal,

                    'obtained_raw' =>
                        $midtermRaw['obtained'],

                    'raw_total' =>
                        $midtermRaw['total'],

                    'status' =>
                        $midtermRaw['status']
                ],


                'final' => [

                    'label' =>
                        'Final',

                    'raw_percentage' =>
                        $finalRaw['percentage'],

                    'obtained' =>
                        $finalObtained,

                    'total' =>
                        $finalTotal,

                    'obtained_raw' =>
                        $finalRaw['obtained'],

                    'raw_total' =>
                        $finalRaw['total'],

                    'status' =>
                        $finalRaw['status']
                ]
            ];


            // =================================================
            // REQUIRED COMPONENTS
            // =================================================

            $requiredStatuses = [];


            if ($assignmentTotal > 0) {

                $requiredStatuses[] =
                    $this->normalizeComponentStatus(
                        $assignmentRaw['status']
                    );
            }


            if ($attendanceTotal > 0) {

                $requiredStatuses[] =
                    $this->normalizeComponentStatus(
                        $attendanceRaw['status']
                    );
            }


            if ($midtermTotal > 0) {

                $requiredStatuses[] =
                    $this->normalizeComponentStatus(
                        $midtermRaw['status']
                    );
            }


            if ($finalTotal > 0) {

                $requiredStatuses[] =
                    $this->normalizeComponentStatus(
                        $finalRaw['status']
                    );
            }


            $hasIncompleteComponent =
                in_array(
                    'incomplete',
                    $requiredStatuses,
                    true
                );


            // =================================================
            // SUBJECT OBTAINED
            // =================================================

            $subjectObtained =
                $assignmentObtained +
                $attendanceObtained +
                $midtermObtained +
                $finalObtained;


            $subjectObtained =
                round(
                    $subjectObtained,
                    2
                );


            // =================================================
            // DEFAULT RESULT
            // =================================================

            $percentage = null;

            $grade = '—';

            $gradeClass = 'neutral';

            $passed = null;


            $passingPercentage =
                $this->safeFloat(
                    $settings['passing_percentage'] ?? 40
                );


            // =================================================
            // COMPLETE RESULT
            // =================================================

            if (
                $configuredTotal > 0 &&
                !$hasIncompleteComponent
            ) {

                $percentage =
                    (
                        $subjectObtained /
                        $configuredTotal
                    ) * 100;


                $percentage =
                    round(
                        $percentage,
                        2
                    );


                $grade =
                    $this->getGrade(
                        $percentage
                    );


                $gradeClass =
                    $this->getGradeClass(
                        $percentage
                    );


                $passed =
                    $percentage >=
                    $passingPercentage;
            }


            // =================================================
            // SUBJECT STATUS
            // =================================================

            if ($hasIncompleteComponent) {

                $subjectStatus =
                    'incomplete';

                $incompleteSubjects++;

            } else {

                $subjectStatus =
                    'complete';

                $completedSubjects++;
            }


            // =================================================
            // OVERALL CALCULATION
            // =================================================

            if (
                $configuredTotal > 0 &&
                !$hasIncompleteComponent
            ) {

                $overallTotal +=
                    $configuredTotal;

                $overallObtained +=
                    $subjectObtained;
            }


            // =================================================
            // STORE SUBJECT
            // =================================================

            $resultSubjects[] = [

                'subject_id' =>
                    $subjectId,

                'subject_name' =>
                    $subject['subject_name'],

                'subject_code' =>
                    $subject['subject_code'],

                'configured' =>
                    true,

                'status' =>
                    $subjectStatus,

                'components' =>
                    $componentData,

                'total_marks' =>
                    round(
                        $configuredTotal,
                        2
                    ),

                'obtained_marks' =>
                    $subjectObtained,

                'percentage' =>
                    $percentage,

                'grade' =>
                    $grade,

                'grade_class' =>
                    $gradeClass,

                'passed' =>
                    $passed,

                'passing_percentage' =>
                    $passingPercentage
            ];
        }


        // =====================================================
        // OVERALL RESULT
        // =====================================================

        $overallPercentage = null;

        $overallGrade = '—';

        $overallGradeClass = 'neutral';


        if (
            $overallTotal > 0 &&
            $incompleteSubjects === 0
        ) {

            $overallPercentage =
                (
                    $overallObtained /
                    $overallTotal
                ) * 100;


            $overallPercentage =
                round(
                    $overallPercentage,
                    2
                );


            $overallGrade =
                $this->getGrade(
                    $overallPercentage
                );


            $overallGradeClass =
                $this->getGradeClass(
                    $overallPercentage
                );


            /*
            |--------------------------------------------------------------------------
            | OVERALL PASS
            |--------------------------------------------------------------------------
            |
            | Overall result is passed only when:
            |
            | 1. All subjects are complete
            | 2. Every completed subject is passed
            | 3. Overall percentage is at least 40%
            |
            */

            $allSubjectsPassed = true;


            foreach ($resultSubjects as $subjectResult) {

                if (
                    $subjectResult['configured'] !== true
                ) {

                    $allSubjectsPassed = false;

                    break;
                }


                if (
                    $subjectResult['passed'] !== true
                ) {

                    $allSubjectsPassed = false;

                    break;
                }
            }


            $overallPassed =
                $allSubjectsPassed &&
                $overallPercentage >= 40;
        }


        // =====================================================
        // RETURN
        // =====================================================

        return [

            'subjects' =>
                $resultSubjects,

            'total_subjects' =>
                count($resultSubjects),

            'completed_subjects' =>
                $completedSubjects,

            'incomplete_subjects' =>
                $incompleteSubjects,

            'overall_obtained' =>
                round(
                    $overallObtained,
                    2
                ),

            'overall_total' =>
                round(
                    $overallTotal,
                    2
                ),

            'overall_percentage' =>
                $overallPercentage,

            'overall_grade' =>
                $overallGrade,

            'overall_grade_class' =>
                $overallGradeClass,

            'overall_passed' =>
                $overallPassed
        ];
    }


    // =====================================================
    // GET RESULT SETTINGS
    // =====================================================

    private function getSettings(
        int $classId,
        ?int $sectionId,
        int $subjectId
    ): ?array {

        // =================================================
        // EXACT SECTION SETTING (highest priority)
        // =================================================

        if ($sectionId !== null) {

            $stmt = $this->pdo->prepare("
                SELECT *
                FROM result_settings

                WHERE class_id = :class_id
                  AND section_id = :section_id
                  AND subject_id = :subject_id

                ORDER BY
                    CASE
                        WHEN status = 'active' THEN 0
                        ELSE 1
                    END,
                    id DESC

                LIMIT 1
            ");

            $stmt->execute([

                ':class_id' =>
                    $classId,

                ':section_id' =>
                    $sectionId,

                ':subject_id' =>
                    $subjectId
            ]);

            $settings =
                $stmt->fetch();


            if ($settings) {

                return $settings;
            }
        }


        // =================================================
        // WHOLE CLASS SETTING (fallback for any section)
        // =================================================

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM result_settings

            WHERE class_id = :class_id
              AND section_id IS NULL
              AND subject_id = :subject_id

            ORDER BY
                CASE
                    WHEN status = 'active' THEN 0
                    ELSE 1
                END,
                id DESC

            LIMIT 1
        ");

        $stmt->execute([

            ':class_id' =>
                $classId,

            ':subject_id' =>
                $subjectId
        ]);

        $settings = $stmt->fetch();

        if ($settings) {
            return $settings;
        }

        // No fallback to other sections — section isolation is required.
        // Teachers must create whole-class settings (section_id NULL)
        // or section-specific settings for each section.

        $settings =
            $stmt->fetch();


        return $settings ?: null;
    }


    // =====================================================
    // ASSIGNMENT PERCENTAGE
    // =====================================================

    private function getAssignmentPercentage(
        int $studentId,
        int $classId,
        ?int $sectionId,
        int $subjectId
    ): array {

        $sql = "
            SELECT

                COUNT(DISTINCT a.id) AS item_count,

                COUNT(
                    DISTINCT
                    CASE
                        WHEN sub.status = 'graded'
                        THEN a.id
                        ELSE NULL
                    END
                ) AS graded_count,

                AVG(
                    CASE
                        WHEN sub.status = 'graded'
                        THEN sub.marks
                        ELSE NULL
                    END
                ) AS percentage

            FROM assignments AS a

            LEFT JOIN submissions AS sub
                ON sub.assignment_id = a.id
                AND sub.student_id = :student_id

            WHERE a.subject_id = :subject_id

              AND a.class_id = :class_id

              AND (
                    a.section_id = :section_id
                    OR a.section_id IS NULL
              )

              AND a.status = 'active'
        ";

        $stmt =
            $this->pdo->prepare($sql);

        $stmt->execute([

            ':student_id' =>
                $studentId,

            ':subject_id' =>
                $subjectId,

            ':class_id' =>
                $classId,

            ':section_id' =>
                $sectionId
        ]);


        $row =
            $stmt->fetch();


        $itemCount =
            (int)
            ($row['item_count'] ?? 0);


        $gradedCount =
            (int)
            ($row['graded_count'] ?? 0);


        $percentage =
            $row['percentage'] !== null
                ? (float) $row['percentage']
                : 0.0;


        // =================================================
        // NO ASSIGNMENTS
        // =================================================

        if ($itemCount === 0) {

            return [

                'percentage' =>
                    0.0,

                'graded_count' =>
                    0,

                'item_count' =>
                    0,

                'status' =>
                    'not_available'
            ];
        }


        // =================================================
        // NOT ALL GRADED
        // =================================================

        if ($gradedCount < $itemCount) {

            return [

                'percentage' =>
                    round(
                        $percentage,
                        2
                    ),

                'graded_count' =>
                    $gradedCount,

                'item_count' =>
                    $itemCount,

                'status' =>
                    'incomplete'
            ];
        }


        // =================================================
        // COMPLETE
        // =================================================

        return [

            'percentage' =>
                round(
                    max(
                        0,
                        min(
                            100,
                            $percentage
                        )
                    ),
                    2
                ),

            'graded_count' =>
                $gradedCount,

            'item_count' =>
                $itemCount,

            'status' =>
                'complete'
        ];
    }


    // =====================================================
    // ATTENDANCE PERCENTAGE
    // =====================================================

    private function getAttendancePercentage(
        int $studentId,
        int $classId,
        ?int $sectionId,
        int $subjectId
    ): array {

        $sql = "
            SELECT

                COUNT(*) AS total_days,

                SUM(
                    CASE
                        WHEN status = 'present'
                        THEN 1
                        ELSE 0
                    END
                ) AS present_days,

                SUM(
                    CASE
                        WHEN status = 'absent'
                        THEN 1
                        ELSE 0
                    END
                ) AS absent_days,

                SUM(
                    CASE
                        WHEN status = 'late'
                        THEN 1
                        ELSE 0
                    END
                ) AS late_days,

                SUM(
                    CASE
                        WHEN status = 'leave'
                        THEN 1
                        ELSE 0
                    END
                ) AS leave_days

            FROM attendance

            WHERE student_id = :student_id

              AND class_id = :class_id

              AND (
                    section_id = :section_id
                    OR section_id IS NULL
              )

              AND subject_id = :subject_id
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            ':student_id' =>
                $studentId,

            ':class_id' =>
                $classId,

            ':section_id' =>
                $sectionId,

            ':subject_id' =>
                $subjectId
        ]);


        $row =
            $stmt->fetch();


        $totalDays =
            (int)
            ($row['total_days'] ?? 0);


        $presentDays =
            (int)
            ($row['present_days'] ?? 0);


        $absentDays =
            (int)
            ($row['absent_days'] ?? 0);


        $lateDays =
            (int)
            ($row['late_days'] ?? 0);


        $leaveDays =
            (int)
            ($row['leave_days'] ?? 0);


        // =================================================
        // NO ATTENDANCE
        // =================================================

        if ($totalDays === 0) {

            return [

                'percentage' =>
                    0.0,

                'present' =>
                    0,

                'absent' =>
                    0,

                'late' =>
                    0,

                'leave' =>
                    0,

                'total_days' =>
                    0,

                'status' =>
                    'not_available'
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | PRESENT + LATE = ATTENDED
        |--------------------------------------------------------------------------
        */

        $attended =
            $presentDays +
            $lateDays;


        $percentage =
            (
                $attended /
                $totalDays
            ) * 100;


        return [

            'percentage' =>
                round(
                    max(
                        0,
                        min(
                            100,
                            $percentage
                        )
                    ),
                    2
                ),

            'present' =>
                $presentDays,

            'absent' =>
                $absentDays,

            'late' =>
                $lateDays,

            'leave' =>
                $leaveDays,

            'total_days' =>
                $totalDays,

            'status' =>
                'complete'
        ];
    }


    // =====================================================
    // EXAM PERCENTAGE
    // =====================================================

    /**
     * Get all individual exam marks for a student by exam type.
     * Returns array of exams with their marks, preserving exact exam_id.
     */
    public function getIndividualExamMarks(
        int $studentId,
        int $classId,
        int $subjectId,
        string $examType
    ): array {

        $normalizedType = $this->normalizeExamType($examType);

        $stmt = $this->pdo->prepare("
            SELECT
                m.id AS mark_id,
                m.exam_id,
                m.obtained_marks,
                m.total_marks,
                e.name AS exam_name,
                e.type AS exam_type,
                e.status AS exam_status
            FROM marks m
            INNER JOIN exams e ON e.id = m.exam_id
            WHERE m.student_id = :student_id
              AND m.subject_id = :subject_id
              AND e.class_id = :class_id
            ORDER BY e.id ASC
        ");

        $stmt->execute([
            ':student_id' => $studentId,
            ':subject_id' => $subjectId,
            ':class_id' => $classId
        ]);

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($this->normalizeExamType((string) ($row['exam_type'] ?? '')) === $normalizedType) {
                $results[] = [
                    'mark_id' => (int) $row['mark_id'],
                    'exam_id' => (int) $row['exam_id'],
                    'exam_name' => $row['exam_name'],
                    'obtained_marks' => (float) $row['obtained_marks'],
                    'total_marks' => (float) $row['total_marks'],
                    'exam_type' => $row['exam_type'],
                    'exam_status' => $row['exam_status'],
                ];
            }
        }

        return $results;
    }

    /**
     * Marks recorded for ONE specific exam for a single student,
     * across every subject of that student's class.
     *
     * Used by the student "Exam-wise Marks" section: each card represents
     * one exam, so the lookup must filter by exam_id (not merely by exam
     * type) and by the real subject of every saved mark.
     */
    public function getStudentMarksByExam(
        int $studentId,
        int $classId,
        int $examId
    ): array {

        $stmt = $this->pdo->prepare("
            SELECT
                m.id AS mark_id,
                m.exam_id,
                m.subject_id,
                m.obtained_marks,
                m.total_marks,
                s.name AS subject_name,
                e.name AS exam_name,
                e.type AS exam_type,
                e.status AS exam_status
            FROM marks m

            INNER JOIN exams e
                ON e.id = m.exam_id

            INNER JOIN subjects s
                ON s.id = m.subject_id

            WHERE m.student_id = :student_id
              AND m.exam_id = :exam_id
              AND e.class_id = :class_id

            ORDER BY s.name ASC
        ");

        $stmt->execute([
            ':student_id' => $studentId,
            ':exam_id'    => $examId,
            ':class_id'   => $classId
        ]);

        $results = [];

        foreach ($stmt->fetchAll() as $row) {
            $results[] = [
                'mark_id'        => (int) $row['mark_id'],
                'exam_id'        => (int) $row['exam_id'],
                'subject_id'     => (int) $row['subject_id'],
                'subject_name'   => $row['subject_name'],
                'obtained_marks' => (float) $row['obtained_marks'],
                'total_marks'    => (float) $row['total_marks'],
                'exam_name'      => $row['exam_name'],
                'exam_type'      => $row['exam_type'],
                'exam_status'    => $row['exam_status'],
            ];
        }

        return $results;
    }

    private function getExamPercentage(
        int $studentId,
        int $classId,
        int $subjectId,
        string $examType
    ): array {

        $normalizedType =
            $this->normalizeExamType(
                $examType
            );


        /*
        |--------------------------------------------------------------------------
        | Get saved marks.
        |
        | Exam does NOT have to be "completed".
        | Actual saved marks are what matter for the result.
        |--------------------------------------------------------------------------
        */

        $stmt = $this->pdo->prepare("
            SELECT

                m.id,
                m.obtained_marks,
                m.total_marks,

                e.id AS exam_id,
                e.type AS exam_type,
                e.class_id,
                e.status AS exam_status

            FROM marks AS m

            INNER JOIN exams AS e
                ON e.id = m.exam_id

            WHERE m.student_id = :student_id

              AND m.subject_id = :subject_id

              AND e.class_id = :class_id

            ORDER BY e.id DESC
        ");


        $stmt->execute([

            ':student_id' =>
                $studentId,

            ':subject_id' =>
                $subjectId,

            ':class_id' =>
                $classId
        ]);


        $rows =
            $stmt->fetchAll();


        $obtained = 0.0;

        $total = 0.0;

        $examCount = 0;

        $usedExamIds = [];


        foreach ($rows as $row) {

            $rowType =
                $this->normalizeExamType(
                    (string)
                    ($row['exam_type'] ?? '')
                );


            if (
                $rowType !==
                $normalizedType
            ) {

                continue;
            }

            // Only use the latest exam of each type
            $examId = (int) $row['exam_id'];
            if (isset($usedExamIds[$examId])) {
                continue;
            }
            $usedExamIds[$examId] = true;


            $rowObtained =
                $this->safeFloat(
                    $row['obtained_marks'] ?? 0
                );


            $rowTotal =
                $this->safeFloat(
                    $row['total_marks'] ?? 0
                );


            // Ignore invalid records

            if ($rowTotal <= 0) {
                continue;
            }


            // Protect against impossible marks

            $rowObtained =
                max(
                    0,
                    min(
                        $rowObtained,
                        $rowTotal
                    )
                );


            $obtained +=
                $rowObtained;

            $total +=
                $rowTotal;

            $examCount++;

            // Only use the latest exam of each type
            break;
        }


        // =================================================
        // NO MARKS
        // =================================================

        if (
            $examCount === 0 ||
            $total <= 0
        ) {

            return [

                'percentage' =>
                    0.0,

                'obtained' =>
                    0.0,

                'total' =>
                    0.0,

                'status' =>
                    'not_available'
            ];
        }


        // =================================================
        // RAW EXAM PERCENTAGE
        // =================================================

        $percentage =
            (
                $obtained /
                $total
            ) * 100;


        return [

            'percentage' =>
                round(
                    max(
                        0,
                        min(
                            100,
                            $percentage
                        )
                    ),
                    2
                ),

            'obtained' =>
                round(
                    $obtained,
                    2
                ),

            'total' =>
                round(
                    $total,
                    2
                ),

            'status' =>
                'complete'
        ];
    }


    // =====================================================
    // NORMALIZE EXAM TYPE
    // =====================================================

    private function normalizeExamType(
        string $type
    ): string {

        $type =
            strtolower(
                trim($type)
            );


        $type =
            preg_replace(
                '/[\s_-]+/',
                '',
                $type
            );


        return $type ?? '';
    }


    // =====================================================
    // NORMALIZE COMPONENT STATUS
    // =====================================================

    private function normalizeComponentStatus(
        string $status
    ): string {

        $status =
            strtolower(
                trim($status)
            );


        if (
            $status ===
            'complete'
        ) {

            return 'complete';
        }


        return 'incomplete';
    }


    // =====================================================
    // PERCENTAGE → MARKS
    // =====================================================

    private function percentageToMarks(
        float $percentage,
        float $componentTotal
    ): float {

        if (
            $componentTotal <= 0
        ) {

            return 0.0;
        }


        $percentage =
            max(
                0,
                min(
                    100,
                    $percentage
                )
            );


        return round(

            (
                $percentage /
                100
            ) *
            $componentTotal,

            2
        );
    }


    // =====================================================
    // GRADE
    // =====================================================

    private function getGrade(
        float $percentage
    ): string {

        if ($percentage >= 90) {
            return 'A+';
        }

        if ($percentage >= 80) {
            return 'A';
        }

        if ($percentage >= 70) {
            return 'B';
        }

        if ($percentage >= 60) {
            return 'C';
        }

        if ($percentage >= 50) {
            return 'D';
        }

        if ($percentage >= 40) {
            return 'E';
        }

        return 'F';
    }


    // =====================================================
    // GRADE CLASS
    // =====================================================

    private function getGradeClass(
        float $percentage
    ): string {

        if ($percentage >= 80) {
            return 'excellent';
        }

        if ($percentage >= 60) {
            return 'good';
        }

        if ($percentage >= 40) {
            return 'average';
        }

        return 'fail';
    }


    // =====================================================
    // EMPTY COMPONENT
    // =====================================================

    private function emptyComponent(
        string $label = ''
    ): array {

        return [

            'label' =>
                $label,

            'raw_percentage' =>
                null,

            'obtained' =>
                0.0,

            'total' =>
                0.0,

            'graded_count' =>
                0,

            'item_count' =>
                0,

            'present' =>
                0,

            'absent' =>
                0,

            'late' =>
                0,

            'leave' =>
                0,

            'total_days' =>
                0,

            'obtained_raw' =>
                0.0,

            'raw_total' =>
                0.0,

            'status' =>
                'not_available'
        ];
    }


    // =====================================================
    // SAFE FLOAT
    // =====================================================

    private function safeFloat(
        mixed $value
    ): float {

        if (
            $value === null ||
            $value === '' ||
            !is_numeric($value)
        ) {

            return 0.0;
        }


        return (float) $value;
    }
}