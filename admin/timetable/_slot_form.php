<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shared timetable slot form (admin)
|--------------------------------------------------------------------------
| Included by create.php and edit.php. Expects:
|
|   array $errors   validation errors
|   array $old      field values (POST echo or row prefill)
|   array $classes  id/name rows
|   array $sections id/name rows for $old['class_id'] (initial render)
|   array $subjects id/name rows
|   array $teachers id/name rows
|   string $formAction  form action URL
|   string $submitLabel button text
|   array  $dayNames  int => label
*/

if (!isset($old, $formAction, $dayNames)) {
    http_response_code(404);
    exit('Not found.');
}

?>

<form method="POST" action="<?= e($formAction) ?>">

    <?= csrf_field() ?>

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <ul class="mb-0 ps-3">

                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <div class="row g-3">

        <!-- CLASS -->

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="class_id">
                Class <span class="text-danger">*</span>
            </label>

            <select
                class="form-select"
                id="class_id"
                name="class_id"
                required
            >

                <?php foreach ($classes as $cls): ?>

                    <option
                        value="<?= (int) $cls['id'] ?>"
                        <?= (int) ($old['class_id'] ?? 0) === (int) $cls['id'] ? 'selected' : '' ?>
                    >
                        <?= e((string) $cls['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- SECTION -->

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="section_id">
                Section
            </label>

            <select class="form-select" id="section_id" name="section_id">

                <option value="">
                    Whole class (no section)
                </option>

                <?php foreach ($sections as $sec): ?>

                    <option
                        value="<?= (int) $sec['id'] ?>"
                        <?= (int) ($old['section_id'] ?? 0) === (int) $sec['id'] ? 'selected' : '' ?>
                    >
                        <?= e((string) $sec['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- DAY -->

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="day_of_week">
                Day <span class="text-danger">*</span>
            </label>

            <select
                class="form-select"
                id="day_of_week"
                name="day_of_week"
                required
            >

                <?php foreach ($dayNames as $dayNum => $dayLabel): ?>

                    <option
                        value="<?= $dayNum ?>"
                        <?= (int) ($old['day_of_week'] ?? 1) === $dayNum ? 'selected' : '' ?>
                    >
                        <?= e($dayLabel) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- PERIOD -->

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="period">
                Period <span class="text-danger">*</span>
            </label>

            <input
                type="number"
                class="form-control"
                id="period"
                name="period"
                min="1"
                max="50"
                required
                value="<?= e((string) ($old['period'] ?? '')) ?>"
            >

        </div>


        <!-- START / END -->

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="start_time">
                Start Time <span class="text-danger">*</span>
            </label>

            <input
                type="time"
                class="form-control"
                id="start_time"
                name="start_time"
                required
                value="<?= e((string) ($old['start_time'] ?? '')) ?>"
            >

        </div>

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="end_time">
                End Time <span class="text-danger">*</span>
            </label>

            <input
                type="time"
                class="form-control"
                id="end_time"
                name="end_time"
                required
                value="<?= e((string) ($old['end_time'] ?? '')) ?>"
            >

        </div>


        <!-- SUBJECT -->

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="subject_id">
                Subject
            </label>

            <select class="form-select" id="subject_id" name="subject_id">

                <option value="">
                    — No subject —
                </option>

                <?php foreach ($subjects as $sub): ?>

                    <option
                        value="<?= (int) $sub['id'] ?>"
                        <?= (int) ($old['subject_id'] ?? 0) === (int) $sub['id'] ? 'selected' : '' ?>
                    >
                        <?= e((string) $sub['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- TEACHER -->

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="teacher_id">
                Teacher
            </label>

            <select class="form-select" id="teacher_id" name="teacher_id">

                <option value="">
                    — Unassigned —
                </option>

                <?php foreach ($teachers as $te): ?>

                    <option
                        value="<?= (int) $te['id'] ?>"
                        <?= (int) ($old['teacher_id'] ?? 0) === (int) $te['id'] ? 'selected' : '' ?>
                    >
                        <?= e((string) $te['name']) ?>
                        (<?= e((string) ($te['teacher_id'] ?? '')) ?>)
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- ROOM -->

        <div class="col-md-4">

            <label class="form-label fw-semibold" for="room">
                Room
            </label>

            <input
                type="text"
                class="form-control"
                id="room"
                name="room"
                maxlength="50"
                placeholder="e.g. 204"
                value="<?= e((string) ($old['room'] ?? '')) ?>"
            >

        </div>

    </div>


    <div class="d-flex gap-2 mt-4">

        <button type="submit" class="btn btn-primary">
            <?= e($submitLabel) ?>
        </button>

        <a class="btn btn-outline-secondary" href="index.php">
            Cancel
        </a>

    </div>

</form>


<script>
(function () {
    const classSel = document.getElementById('class_id');
    const sectionSel = document.getElementById('section_id');

    if (!classSel || !sectionSel) {
        return;
    }

    classSel.addEventListener('change', function () {

        // Rebuild section options for the newly selected class.
        // Server-side validation still checks that the posted
        // section really belongs to the posted class.
        sectionSel.innerHTML =
            '<option value="">Whole class (no section)</option>';

        const classId = parseInt(classSel.value, 10);

        if (!classId) {
            return;
        }

        fetch('../students/get_sections.php?class_id=' + encodeURIComponent(classId))
            .then(function (res) { return res.json(); })
            .then(function (data) {

                if (!data.success || !Array.isArray(data.sections)) {
                    return;
                }

                data.sections.forEach(function (s) {

                    const opt = document.createElement('option');
                    opt.value = String(s.id);
                    opt.textContent = s.name;
                    sectionSel.appendChild(opt);
                });
            })
            .catch(function () { /* transient — server validates anyway */ });
    });
})();
</script>
