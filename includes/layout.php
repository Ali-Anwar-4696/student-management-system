<?php

declare(strict_types=1);

function layout_start(string $title, string $active = ''): void
{
    $role = $_SESSION['user_role'] ?? '';
    $name = $_SESSION['user_name'] ?? 'User';
    $nav = [];

    /*
    |--------------------------------------------------------------------------
    | ADMIN NAVIGATION
    |--------------------------------------------------------------------------
    */

    if ($role === 'admin') {

        $nav = [
            'dashboard' => [
                url('admin/dashboard.php'),
                'Dashboard',
                'speedometer2'
            ],

            'students' => [
                url('admin/students/index.php'),
                'Students',
                'people'
            ],

            'teachers' => [
                url('admin/teachers/index.php'),
                'Teachers',
                'person-badge'
            ],

            'classes' => [
                url('admin/classes/index.php'),
                'Classes',
                'building'
            ],

            'sections' => [
                url('admin/sections/index.php'),
                'Sections',
                'grid'
            ],

            'subjects' => [
                url('admin/subjects/index.php'),
                'Subjects',
                'book'
            ],

            'attendance' => [
                url('admin/attendance/index.php'),
                'Attendance',
                'calendar-check'
            ],

            'timetable' => [
                url('admin/timetable/index.php'),
                'Timetable',
                'calendar3-week'
            ],

            'assignments' => [
                url('admin/assignments/index.php'),
                'Assignments',
                'journal-plus'
            ],

            'exams' => [
                url('admin/exams/index.php'),
                'Exams',
                'journal-text'
            ],

            'marks' => [
                url('admin/marks/index.php'),
                'Marks',
                'clipboard-data'
            ],

            'results' => [
                url('admin/results/index.php'),
                'Results',
                'award'
            ],

            'fees' => [
                url('admin/fees/index.php'),
                'Fees',
                'cash-stack'
            ],

            'reports' => [
                url('admin/reports/index.php'),
                'Reports',
                'graph-up'
            ],

            'notifications' => [
                url('admin/notifications/index.php'),
                'Notifications',
                'bell'
            ],
        ];

    /*
    |--------------------------------------------------------------------------
    | TEACHER NAVIGATION
    |--------------------------------------------------------------------------
    */

    } elseif ($role === 'teacher') {

        $nav = [
            'dashboard' => [
                url('teacher/dashboard.php'),
                'Dashboard',
                'speedometer2'
            ],

            'assignments' => [
                url('teacher/assignments.php'),
                'Assignments',
                'journal-plus'
            ],

            'submissions' => [
                url('teacher/submissions.php'),
                'Submissions',
                'inbox'
            ],

            'students' => [
                url('teacher/students.php'),
                'My Students',
                'people'
            ],

            'attendance' => [
                url('teacher/attendance.php'),
                'Attendance',
                'calendar-check'
            ],

            'timetable' => [
                url('teacher/timetable.php'),
                'Timetable',
                'calendar3-week'
            ],

            'exams' => [
                url('teacher/exams.php'),
                'Exams',
                'journal-text'
            ],

            'marks' => [
                url('teacher/marks.php'),
                'Marks',
                'clipboard-data'
            ],

            'notifications' => [
                url('notifications.php'),
                'Notifications',
                'bell'
            ],

            'password' => [
                url('teacher/change-password.php'),
                'Change Password',
                'key'
            ],
        ];

    /*
    |--------------------------------------------------------------------------
    | PARENT NAVIGATION
    |--------------------------------------------------------------------------
    */

    } elseif ($role === 'parent') {

        $nav = [
            'dashboard' => [
                url('parent/dashboard.php'),
                'Dashboard',
                'speedometer2'
            ],

            'children' => [
                url('parent/dashboard.php#children'),
                'My Children',
                'people'
            ],

            'notifications' => [
                url('notifications.php'),
                'Notifications',
                'bell'
            ],
        ];

    /*
    |--------------------------------------------------------------------------
    | STUDENT NAVIGATION
    |--------------------------------------------------------------------------
    */

    } else {

        $nav = [
            'dashboard' => [
                url('student/dashboard.php'),
                'Dashboard',
                'speedometer2'
            ],

            'assignments' => [
                url('student/assignments.php'),
                'Assignments',
                'journal-text'
            ],

            'submissions' => [
                url('student/submissions.php'),
                'My Submissions',
                'inbox'
            ],

            'attendance' => [
                url('student/attendance.php'),
                'Attendance',
                'calendar-check'
            ],

            'timetable' => [
                url('student/timetable.php'),
                'Timetable',
                'calendar3-week'
            ],

            'results' => [
                url('student/results.php'),
                'Results',
                'award'
            ],

            'fees' => [
                url('student/fees.php'),
                'Fees',
                'cash-stack'
            ],

            'profile' => [
                url('student/profile.php'),
                'Profile',
                'person'
            ],

            'notifications' => [
                url('notifications.php'),
                'Notifications',
                'bell'
            ],
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | HTML HEAD
    |--------------------------------------------------------------------------
    */

    echo '<!DOCTYPE html>';

    echo '<html lang="en">';

    echo '<head>';

    /*
    |--------------------------------------------------------------------------
    | THEME — applied before first paint to avoid a flash of the
    | wrong theme. Reads the user's choice from localStorage and
    | falls back to the OS preference.
    |--------------------------------------------------------------------------
    */

    echo '<script>
    (function () {
        try {
            var t = localStorage.getItem("app_theme");
            if (t !== "dark" && t !== "light") {
                t = (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches)
                    ? "dark"
                    : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", t);
        } catch (err) {}
    })();
    </script>';

    echo '<meta charset="UTF-8">';

    echo '<meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >';

    echo '<meta
        name="theme-color"
        content="#4f46e5"
    >';

    echo '<title>'
        . e($title)
        . ' | StudentHub'
        . '</title>';


    /*
    |--------------------------------------------------------------------------
    | Bootstrap
    |--------------------------------------------------------------------------
    */

    echo '<link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >';


    /*
    |--------------------------------------------------------------------------
    | Bootstrap Icons
    |--------------------------------------------------------------------------
    */

    echo '<link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >';


    /*
    |--------------------------------------------------------------------------
    | Application CSS
    |--------------------------------------------------------------------------
    */

    echo '<link
        href="' . e(url('assets/css/app.css')) . '"
        rel="stylesheet"
    >';


    /*
    |--------------------------------------------------------------------------
    | RESPONSIVE APP SHELL CSS
    |--------------------------------------------------------------------------
    */

    echo <<<CSS

<style>

/* =========================================================
   GLOBAL APP RESPONSIVENESS
========================================================= */

html {
    width: 100%;
    min-width: 0;
    overflow-x: hidden;
}

body.app-body {
    width: 100%;
    min-width: 0;
    margin: 0;
    overflow-x: hidden;
    background: #f5f7fb;
}

*,
*::before,
*::after {
    box-sizing: border-box;
}


/* =========================================================
   APP SHELL
========================================================= */

.app-shell {
    width: 100%;
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    position: relative;
    overflow-x: hidden;
}


/* =========================================================
   SIDEBAR
========================================================= */

.app-sidebar {
    position: fixed;
    z-index: 1100;

    top: 0;
    left: 0;
    bottom: 0;

    width: 260px;

    overflow-x: hidden;
    overflow-y: auto;

    background:
        linear-gradient(
            180deg,
            #171a3a 0%,
            #11142e 100%
        );

    box-shadow:
        8px 0 30px rgba(15, 23, 42, .08);

    scrollbar-width: thin;

    transition:
        transform .28s ease,
        box-shadow .28s ease;
}


/* =========================================================
   BRAND
========================================================= */

.app-sidebar .brand {
    height: 76px;

    display: flex;
    align-items: center;

    padding: 0 22px;

    color: #fff;

    text-decoration: none;

    font-size: 20px;
    font-weight: 800;

    white-space: nowrap;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}

.app-sidebar .brand i {
    width: 39px;
    height: 39px;

    margin-right: 10px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    font-size: 18px;

    box-shadow:
        0 8px 20px
        rgba(99,102,241,.25);
}


/* =========================================================
   NAVIGATION
========================================================= */

.app-sidebar .nav {
    padding: 18px 12px 25px;
    gap: 5px;
}

.app-sidebar .nav-link {
    min-height: 45px;

    display: flex;
    align-items: center;

    padding: 11px 13px;

    border-radius: 13px;

    color:
        rgba(255,255,255,.66);

    font-size: 13px;
    font-weight: 600;

    text-decoration: none;

    white-space: nowrap;

    transition:
        background .2s ease,
        color .2s ease,
        transform .2s ease;
}

.app-sidebar .nav-link i {
    width: 25px;

    margin-right: 9px;

    font-size: 16px;

    text-align: center;
}

.app-sidebar .nav-link:hover {
    color: #fff;

    background:
        rgba(255,255,255,.07);

    transform: translateX(2px);
}

.app-sidebar .nav-link.active {
    color: #fff;

    background:
        linear-gradient(
            135deg,
            rgba(99,102,241,.95),
            rgba(124,58,237,.9)
        );

    box-shadow:
        0 8px 22px
        rgba(79,70,229,.22);
}


/* =========================================================
   MAIN AREA
========================================================= */

.app-main {
    width: calc(100% - 260px);

    min-width: 0;

    margin-left: 260px;

    min-height: 100vh;
    min-height: 100dvh;

    display: flex;
    flex-direction: column;
}


/* =========================================================
   TOP BAR
========================================================= */

.app-topbar {
    min-height: 76px;

    width: 100%;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    padding:
        14px 28px;

    background:
        rgba(255,255,255,.96);

    border-bottom:
        1px solid
        #e8ebf3;

    position: sticky;
    top: 0;

    z-index: 900;

    backdrop-filter: blur(12px);
}

.app-topbar h1 {
    color: #182033;
    font-weight: 800;

    white-space: nowrap;

    overflow: hidden;
    text-overflow: ellipsis;
}


/* =========================================================
   TOPBAR USER AREA
========================================================= */

.app-user-area {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 10px;
}

.app-user-info {
    max-width: 220px;

    overflow: hidden;

    color: #6b7280;

    font-size: 12px;

    white-space: nowrap;

    text-overflow: ellipsis;
}

.app-logout {
    flex: 0 0 auto;

    border-radius: 10px !important;

    font-weight: 600;
}


/* =========================================================
   MOBILE MENU BUTTON
========================================================= */

.app-menu-toggle {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: none;

    align-items: center;
    justify-content: center;

    padding: 0;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    background: #fff;

    color: #374151;

    font-size: 20px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.05);

    cursor: pointer;
}

.app-menu-toggle:hover {
    background: #f8fafc;
}


/* =========================================================
   SIDEBAR OVERLAY
========================================================= */

.app-sidebar-overlay {
    display: none;

    position: fixed;

    inset: 0;

    z-index: 1050;

    background:
        rgba(15,23,42,.48);

    backdrop-filter:
        blur(2px);

    opacity: 0;

    transition:
        opacity .25s ease;
}


/* =========================================================
   CONTENT
========================================================= */

.app-content {
    width: 100%;
    min-width: 0;

    flex: 1;

    padding:
        26px 28px 35px;

    overflow-x: hidden;
}


/* =========================================================
   FLASH MESSAGE
========================================================= */

.app-content > .alert {
    border-radius: 14px;

    border: 0;

    box-shadow:
        0 5px 20px
        rgba(15,23,42,.05);
}


/* =========================================================
   TABLE RESPONSIVENESS
========================================================= */

.app-content .table-responsive {
    max-width: 100%;

    overflow-x: auto;

    -webkit-overflow-scrolling: touch;
}


/* =========================================================
   IMAGES / MEDIA
========================================================= */

.app-content img,
.app-content video,
.app-content iframe {
    max-width: 100%;
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 991.98px) {

    .app-sidebar {
        width: 250px;

        transform:
            translateX(-105%);
    }

    .app-sidebar.mobile-open {
        transform:
            translateX(0);

        box-shadow:
            12px 0 40px
            rgba(15,23,42,.20);
    }

    .app-sidebar-overlay.mobile-visible {
        display: block;

        opacity: 1;
    }

    .app-main {
        width: 100%;

        margin-left: 0;
    }

    .app-menu-toggle {
        display: inline-flex;
    }

    .app-topbar {
        padding:
            13px 20px;
    }

    .app-content {
        padding:
            22px 20px 30px;
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 767.98px) {

    .app-topbar {
        min-height: 64px;

        padding:
            10px 14px;

        gap: 10px;
    }

    .app-topbar > div:first-child {
        min-width: 0;

        display: flex;
        align-items: center;

        gap: 10px;
    }

    .app-topbar h1 {
        max-width: 45vw;

        font-size: 15px !important;
    }

    .app-user-area {
        gap: 7px;
    }

    .app-user-info {
        display: none;
    }

    .app-logout {
        padding:
            7px 10px !important;

        font-size: 11px !important;
    }

    .app-content {
        width: 100%;

        padding:
            14px 12px 25px;
    }
}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 480px) {

    .app-sidebar {
        width: min(285px, 86vw);
    }

    .app-topbar {
        padding:
            9px 10px;
    }

    .app-menu-toggle {
        width: 39px;
        height: 39px;

        flex-basis: 39px;

        border-radius: 11px;

        font-size: 18px;
    }

    .app-topbar h1 {
        max-width: 42vw;

        font-size: 14px !important;
    }

    .app-logout {
        min-height: 38px;

        padding:
            7px 9px !important;
    }

    .app-content {
        padding:
            10px 9px 22px;
    }
}


/* =========================================================
   VERY SMALL PHONES — 320px / 360px
========================================================= */

@media (max-width: 360px) {

    .app-topbar {
        gap: 6px;

        padding:
            8px;
    }

    .app-topbar h1 {
        max-width: 36vw;

        font-size: 13px !important;
    }

    .app-logout {
        padding:
            6px 8px !important;

        font-size: 10px !important;
    }

    .app-content {
        padding:
            8px 7px 20px;
    }
}


/* =========================================================
   SAFE MOBILE TEXT
========================================================= */

@media (max-width: 767.98px) {

    .app-content .row {
        --bs-gutter-x: 1rem;
    }

    .app-content .card,
    .app-content .card-box,
    .app-content .student-ui {
        max-width: 100%;
    }

    .app-content .btn {
        max-width: 100%;
    }
}


/* =========================================================
   REDUCE MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    .app-sidebar,
    .app-sidebar-overlay,
    .app-sidebar .nav-link,
    * {
        transition: none !important;
    }
}

</style>

CSS;


    /*
    |--------------------------------------------------------------------------
    | DARK MODE OVERRIDES
    | Driven by data-bs-theme="dark" on <html> (toggled in the topbar).
    | Bootstrap 5.3 handles forms/badges/alerts natively; these rules
    | cover the app shell plus common page primitives (cards, tables,
    | headings), including pages that hard-code white backgrounds.
    |--------------------------------------------------------------------------
    */

    echo <<<CSS_DARK

<style>

    [data-bs-theme="dark"] body.app-body {
        background: #0b1120;
        color: #dbe2ea;
    }

    [data-bs-theme="dark"] .app-topbar {
        background: rgba(15, 20, 38, .96);
        border-bottom-color: #1f2937;
    }

    [data-bs-theme="dark"] .app-topbar h1 {
        color: #f1f5f9;
    }

    [data-bs-theme="dark"] .app-user-info {
        color: #9ca3af;
    }

    [data-bs-theme="dark"] .app-content h1,
    [data-bs-theme="dark"] .app-content h2,
    [data-bs-theme="dark"] .app-content h3,
    [data-bs-theme="dark"] .app-content h4,
    [data-bs-theme="dark"] .app-content h5,
    [data-bs-theme="dark"] .app-content h6,
    [data-bs-theme="dark"] .app-content .h5,
    [data-bs-theme="dark"] .app-content strong {
        color: #f1f5f9;
    }

    [data-bs-theme="dark"] .card,
    [data-bs-theme="dark"] .card-header,
    [data-bs-theme="dark"] .bg-white {
        background-color: #111827 !important;
        border-color: #1f2937 !important;
        color: #dbe2ea;
    }

    [data-bs-theme="dark"] .table,
    [data-bs-theme="dark"] .table > :not(caption) > * > * {
        background-color: transparent;
        color: #dbe2ea;
        border-color: #1f2937 !important;
    }

    [data-bs-theme="dark"] .table-light,
    [data-bs-theme="dark"] .table-light > * {
        --bs-table-bg: #0f172a;
        --bs-table-color: #e5e7eb;
        background-color: #0f172a !important;
        color: #e5e7eb;
    }

    [data-bs-theme="dark"] .table-hover > tbody > tr:hover > * {
        background-color: rgba(255,255,255,.05);
        color: #f1f5f9;
    }

    [data-bs-theme="dark"] a {
        color: #8ab4f8;
    }

    [data-bs-theme="dark"] .btn-outline-secondary {
        color: #cbd5e1;
        border-color: #475569;
    }

    [data-bs-theme="dark"] .btn-outline-secondary:hover {
        background: #334155;
        color: #fff;
    }

    [data-bs-theme="dark"] .text-muted {
        color: #94a3b8 !important;
    }

    [data-bs-theme="dark"] .form-control,
    [data-bs-theme="dark"] .form-select {
        background-color: #0f172a;
        border-color: #334155;
        color: #e2e8f0;
    }

    [data-bs-theme="dark"] .form-control::placeholder {
        color: #64748b;
    }

    [data-bs-theme="dark"] .alert {
        border-color: #1f2937;
    }

    [data-bs-theme="dark"] .app-theme-toggle i {
        color: #fbbf24;
    }

</style>

CSS_DARK;


    echo '</head>';

    echo '<body class="app-body">';


    /*
    |--------------------------------------------------------------------------
    | APP SHELL
    |--------------------------------------------------------------------------
    */

    echo '<div class="app-shell">';


    /*
    |--------------------------------------------------------------------------
    | SIDEBAR
    |--------------------------------------------------------------------------
    */

    echo '<aside
        class="app-sidebar"
        id="appSidebar"
        aria-label="Main navigation"
    >';


    /*
    |--------------------------------------------------------------------------
    | BRAND
    |--------------------------------------------------------------------------
    */

    echo '<a
        class="brand"
        href="' . e(
            $nav['dashboard'][0]
            ?? url('auth/login.php')
        ) . '"
    >';

    echo '<i class="bi bi-mortarboard-fill"></i>';

    echo 'StudentHub';

    echo '</a>';


    /*
    |--------------------------------------------------------------------------
    | NAVIGATION
    |--------------------------------------------------------------------------
    */

    echo '<nav class="nav flex-column">';

    foreach ($nav as $key => $item) {

        $cls = $key === $active
            ? 'nav-link active'
            : 'nav-link';

        echo '<a
            class="' . e($cls) . '"
            href="' . e($item[0]) . '"
        >';

        echo '<i class="bi bi-'
            . e($item[2])
            . '"></i>';

        echo '<span>';

        echo e($item[1]);

        echo '</span>';

        echo '</a>';
    }

    echo '</nav>';

    echo '</aside>';


    /*
    |--------------------------------------------------------------------------
    | MOBILE SIDEBAR OVERLAY
    |--------------------------------------------------------------------------
    */

    echo '<div
        class="app-sidebar-overlay"
        id="appSidebarOverlay"
        aria-hidden="true"
    ></div>';


    /*
    |--------------------------------------------------------------------------
    | MAIN AREA
    |--------------------------------------------------------------------------
    */

    echo '<div class="app-main">';


    /*
    |--------------------------------------------------------------------------
    | TOP BAR
    |--------------------------------------------------------------------------
    */

    echo '<header class="app-topbar">';


    /*
    |--------------------------------------------------------------------------
    | LEFT SIDE
    |--------------------------------------------------------------------------
    */

    echo '<div>';

    /*
     * Mobile menu button
     */

    echo '<button
        type="button"
        class="app-menu-toggle"
        id="appMenuToggle"
        aria-label="Open navigation"
        aria-controls="appSidebar"
        aria-expanded="false"
    >';

    echo '<i class="bi bi-list"></i>';

    echo '</button>';


    /*
     * Page title
     */

    echo '<h1 class="h5 mb-0 d-inline-block">';

    echo e($title);

    echo '</h1>';

    echo '</div>';


    /*
    |--------------------------------------------------------------------------
    | USER AREA
    |--------------------------------------------------------------------------
    */

    echo '<div class="app-user-area">';


    /*
     * User information
     */

    echo '<span class="app-user-info">';

    echo e($name);

    echo ' · ';

    echo e(ucfirst($role));

    echo '</span>';


    /*
     * Dark mode toggle
     */

    echo '<button
        type="button"
        class="btn btn-sm btn-outline-secondary app-theme-toggle"
        id="appThemeToggle"
        aria-label="Toggle dark mode"
        title="Toggle dark mode"
    >';

    echo '<i class="bi bi-moon-stars" id="appThemeToggleIcon"></i>';

    echo '</button>';


    /*
     * Logout
     */

    echo '<a
        class="btn btn-sm btn-outline-secondary app-logout"
        href="' . e(url('auth/logout.php')) . '"
    >';

    echo '<i class="bi bi-box-arrow-right me-1"></i>';

    echo '<span>Logout</span>';

    echo '</a>';


    echo '</div>';

    echo '</header>';


    /*
    |--------------------------------------------------------------------------
    | THEME TOGGLE BEHAVIOUR
    |--------------------------------------------------------------------------
    */

    echo '<script>
    (function () {
        var btn = document.getElementById("appThemeToggle");
        if (!btn) {
            return;
        }

        function currentTheme() {
            return document.documentElement.getAttribute("data-bs-theme") === "dark" ? "dark" : "light";
        }

        function syncIcon() {
            var icon = document.getElementById("appThemeToggleIcon");
            if (icon) {
                icon.className = currentTheme() === "dark" ? "bi bi-sun" : "bi bi-moon-stars";
            }
        }

        syncIcon();

        btn.addEventListener("click", function () {
            var next = currentTheme() === "dark" ? "light" : "dark";

            document.documentElement.setAttribute("data-bs-theme", next);

            try {
                localStorage.setItem("app_theme", next);
            } catch (err) {}

            var meta = document.querySelector("meta[name=theme-color]");
            if (meta) {
                meta.setAttribute("content", next === "dark" ? "#0b1120" : "#4f46e5");
            }

            syncIcon();
        });
    })();
    </script>';


    /*
    |--------------------------------------------------------------------------
    | PAGE CONTENT
    |--------------------------------------------------------------------------
    */

    echo '<main class="app-content">';


    /*
    |--------------------------------------------------------------------------
    | FLASH MESSAGE
    |--------------------------------------------------------------------------
    */

    $flash = flash_get();

    if ($flash) {

        $type = in_array(
            $flash['type'],
            [
                'success',
                'danger',
                'warning',
                'info'
            ],
            true
        )
            ? $flash['type']
            : 'info';

        echo '<div class="alert alert-'
            . e($type)
            . '">';

        echo e($flash['message']);

        echo '</div>';
    }
}


/*
|--------------------------------------------------------------------------
| LAYOUT END
|--------------------------------------------------------------------------
*/

function layout_end(): void
{
    echo '</main>';

    echo '</div>';

    echo '</div>';


    /*
    |--------------------------------------------------------------------------
    | Bootstrap JavaScript
    |--------------------------------------------------------------------------
    */

    echo '<script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>';


    /*
    |--------------------------------------------------------------------------
    | MOBILE SIDEBAR JAVASCRIPT
    |--------------------------------------------------------------------------
    */

    echo <<<JS

<script>

(function () {

    'use strict';

    const sidebar = document.getElementById('appSidebar');

    const overlay = document.getElementById('appSidebarOverlay');

    const toggle = document.getElementById('appMenuToggle');


    if (!sidebar || !overlay || !toggle) {
        return;
    }


    function openSidebar() {

        sidebar.classList.add('mobile-open');

        overlay.classList.add('mobile-visible');

        toggle.setAttribute(
            'aria-expanded',
            'true'
        );

        toggle.setAttribute(
            'aria-label',
            'Close navigation'
        );

        toggle.innerHTML =
            '<i class="bi bi-x-lg"></i>';

        document.body.style.overflow = 'hidden';
    }


    function closeSidebar() {

        sidebar.classList.remove('mobile-open');

        overlay.classList.remove('mobile-visible');

        toggle.setAttribute(
            'aria-expanded',
            'false'
        );

        toggle.setAttribute(
            'aria-label',
            'Open navigation'
        );

        toggle.innerHTML =
            '<i class="bi bi-list"></i>';

        document.body.style.overflow = '';
    }


    toggle.addEventListener(
        'click',
        function () {

            if (
                sidebar.classList.contains(
                    'mobile-open'
                )
            ) {

                closeSidebar();

            } else {

                openSidebar();

            }

        }
    );


    overlay.addEventListener(
        'click',
        closeSidebar
    );


    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {

                closeSidebar();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Close sidebar after navigation on mobile
    |--------------------------------------------------------------------------
    */

    sidebar
        .querySelectorAll('.nav-link')
        .forEach(function (link) {

            link.addEventListener(
                'click',
                function () {

                    if (
                        window.innerWidth <= 991
                    ) {

                        closeSidebar();

                    }

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Reset mobile menu when returning to desktop
    |--------------------------------------------------------------------------
    */

    window.addEventListener(
        'resize',
        function () {

            if (window.innerWidth > 991) {

                closeSidebar();

            }

        }
    );

})();

</script>

JS;


    echo '</body>';

    echo '</html>';
}

