<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shared weekly timetable grid renderer
|--------------------------------------------------------------------------
|
| usage:
|   require_once __DIR__ . '/../includes/timetable_view.php';
|   timetable_grid($rows, [
|       'editable'    => true,   // admin: edit/delete controls
|       'show_class'  => false,  // show class + section per cell
|       'show_teacher'=> true,   // show teacher name per cell
|   ]);
|
| Rows come from Timetable::listForClass/listForTeacher/listForStudent.
|
*/

if (!function_exists('timetable_grid')) {

    function timetable_grid(array $rows, array $options = []): void
    {
        $editable = (bool) ($options['editable'] ?? false);
        $showClass = (bool) ($options['show_class'] ?? false);
        $showTeacher = (bool) ($options['show_teacher'] ?? false);

        // Where the inline delete form posts (callers can preserve
        // their current filter query string here).
        $deleteAction = (string) ($options['delete_action'] ?? 'index.php');

        $dayNames = (array) ($options['day_names'] ?? []);

        if ($dayNames === []) {
            $dayNames = [
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
                7 => 'Sunday',
            ];
        }

        // ---- index cells, collect periods and days actually used ----
        $cells = [];
        $periods = [];
        $daysUsed = [];

        foreach ($rows as $row) {
            $d = (int) ($row['day_of_week'] ?? 0);
            $p = (int) ($row['period'] ?? 0);

            if ($d < 1 || $d > 7 || $p < 1) {
                continue;
            }

            $cells[$d . '-' . $p] = $row;
            $periods[$p] = true;
            $daysUsed[$d] = true;
        }

        ksort($periods);

        $dayList = array_keys($daysUsed);
        sort($dayList);

        // Empty grid still needs sensible columns/rows.
        if ($dayList === []) {
            $dayList = [1, 2, 3, 4, 5];
        }

        $periodList = $periods === [] ? [1] : array_keys($periods);

        $hasRows = $rows !== [];

        echo '<div class="table-responsive">';

        echo '<table class="table table-bordered align-middle mb-0" style="min-width: 760px;">';

        // ---- header ----
        echo '<thead class="table-light"><tr>'
            . '<th style="min-width: 88px;">Period</th>';

        foreach ($dayList as $d) {
            echo '<th class="text-center">'
                . e((string) ($dayNames[$d] ?? ('Day ' . $d)))
                . '</th>';
        }

        echo '</tr></thead><tbody>';

        // ---- body ----
        foreach ($periodList as $p) {

            echo '<tr>';
            echo '<th scope="row" class="text-nowrap align-middle">'
                . 'Period ' . $p
                . '</th>';

            foreach ($dayList as $d) {

                $cell = $cells[$d . '-' . $p] ?? null;

                if ($cell === null) {
                    echo '<td class="text-center text-muted">&mdash;</td>';
                    continue;
                }

                $subjectName = (string) ($cell['subject_name'] ?? '');
                $room = (string) ($cell['room'] ?? '');
                $sectionName = (string) ($cell['section_name'] ?? '');
                $teacherName = (string) ($cell['teacher_name'] ?? '');

                $startTime = substr((string) $cell['start_time'], 0, 5);
                $endTime = substr((string) $cell['end_time'], 0, 5);

                echo '<td class="p-1">';

                echo '<div class="border rounded p-2 h-100" style="background: rgba(59,130,246,.05);">';

                echo '<div class="fw-semibold" style="font-size:.85rem;">'
                    . e($subjectName !== '' ? $subjectName : 'Lesson')
                    . '</div>';

                echo '<div class="text-muted" style="font-size:.75rem;">'
                    . e($startTime . ' – ' . $endTime);

                if ($room !== '') {
                    echo ' &middot; ' . e($room);
                }

                echo '</div>';

                if ($showClass) {
                    echo '<div class="text-muted" style="font-size:.75rem;">'
                        . e((string) ($cell['class_name'] ?? ''));

                    if ($sectionName !== '') {
                        echo ' &ndash; ' . e($sectionName);
                    }

                    echo '</div>';
                } elseif ($sectionName !== '') {

                    echo '<span class="badge bg-secondary" style="font-size:.68rem;">'
                        . e($sectionName)
                        . '</span>';
                }

                if ($showTeacher && $teacherName !== '') {
                    echo '<div class="text-muted" style="font-size:.75rem;">'
                        . e($teacherName)
                        . '</div>';
                }

                if ($editable) {

                    $slotId = (int) $cell['id'];

                    echo '<div class="d-flex gap-1 mt-1">';

                    echo '<a class="btn btn-sm btn-outline-primary py-0" '
                        . 'style="font-size:.7rem;" '
                        . 'href="edit.php?id=' . $slotId . '">Edit</a>';

                    echo '<form method="POST" action="' . e($deleteAction) . '" '
                        . 'style="display:inline;" '
                        . 'onsubmit="return confirm(\'Delete this timetable slot?\');">';

                    echo csrf_field();
                    echo '<input type="hidden" name="action" value="delete">';
                    echo '<input type="hidden" name="id" value="' . $slotId . '">';

                    echo '<button type="submit" class="btn btn-sm btn-outline-danger py-0" '
                        . 'style="font-size:.7rem;">Delete</button>';

                    echo '</form>';

                    echo '</div>';
                }

                echo '</div>';
                echo '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table>';

        if (!$hasRows) {
            echo '<div class="text-center text-muted py-4">'
                . 'No timetable slots have been scheduled yet.'
                . '</div>';
        }

        echo '</div>';
    }

}
