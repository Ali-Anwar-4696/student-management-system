<?php

declare(strict_types=1);

/*
| GET api/timetable.php
| admin:   class_id (required), section_id (optional)
| teacher: own slots + all classes assigned to the teacher
| student: own class + section (empty list when no class assigned)
| parent:  slots of the linked children's classes
*/

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/Timetable.php';
require_once __DIR__ . '/../classes/Student.php';

api_require_get();
api_require_auth();

$role = currentRole();

if (!in_array($role, ['admin', 'teacher', 'student', 'parent'], true)) {
    api_fail(403, 'You do not have access to this endpoint.');
}

$pdo = db();
$timetableObj = new Timetable($pdo);

$classId = api_int('class_id');
$sectionId = api_int('section_id');

$rows = [];

if ($role === 'admin') {

    if ($classId <= 0) {
        api_fail(400, 'class_id is required.');
    }

    $rows = $timetableObj->listForClass(
        $classId,
        $sectionId > 0 ? $sectionId : null
    );

} elseif ($role === 'teacher') {

    $teacher = current_teacher();

    if (!$teacher) {
        api_fail(403, 'No teacher profile linked to your account.');
    }

    $rows = $timetableObj->listForTeacher((int) $teacher['id']);

} elseif ($role === 'student') {

    $me = current_student();

    if (!$me) {
        api_fail(404, 'No student profile linked to your account.');
    }

    $ownClassId = (int) ($me['class_id'] ?? 0);

    if ($ownClassId > 0) {
        $ownSectionId = $me['section_id'] !== null && $me['section_id'] !== ''
            ? (int) $me['section_id']
            : null;

        $rows = $timetableObj->listForStudent($ownClassId, $ownSectionId);
    }

} else {

    // parent: union of the linked children's class grids, deduped
    $studentObj = new Student($pdo);

    $children = $studentObj->getChildrenByParent(
        (int) ($_SESSION['user_id'] ?? 0)
    );

    $seen = [];

    foreach ($children as $child) {

        $childClassId = (int) ($child['class_id'] ?? 0);

        if ($childClassId <= 0) {
            continue;
        }

        $childSectionId = $child['section_id'] !== null
            && $child['section_id'] !== ''
            ? (int) $child['section_id']
            : null;

        foreach ($timetableObj->listForStudent($childClassId, $childSectionId) as $slot) {

            $slotId = (int) $slot['id'];

            if (isset($seen[$slotId])) {
                continue;
            }

            $seen[$slotId] = true;
            $rows[] = $slot;
        }
    }
}

api_ok(['slots' => $rows], ['count' => count($rows)]);
