<?php
/**
 * My Univer — local cache cabinet (profile, subjects, schedule, grades, attendance).
 *
 * Session user only. No userid parameter. No live HTTP on page load.
 *
 * @package local_kaznu
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

use local_kaznu\cache_module;
use local_kaznu\local\univer_datetime;
use local_kaznu\service\student_dashboard_service;

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

global $DB, $USER, $PAGE, $OUTPUT;

$userid = (int) $USER->id;

// Permanent Univer link — not dependent on auth method / current session origin.
$islinked = class_exists(\local_kaznu\access\portal_policy::class)
    ? \local_kaznu\access\portal_policy::can_view_univer_data($USER)
    : $DB->record_exists('local_kaznu_univer_user', ['userid' => $userid]);

$section = optional_param('section', 'overview', PARAM_ALPHA);
// Product aliases.
if ($section === 'assessments') {
    $section = 'grades';
}
// Common tabs for everyone; Univer tabs only if linked in DB (or viewuniverdata).
$commontabs = ['overview', 'courses'];
$linkedtabs = ['schedule', 'grades', 'attendance'];
$allowed = $islinked ? array_merge($commontabs, $linkedtabs) : $commontabs;
if (!in_array($section, $allowed, true)) {
    $section = 'overview';
}

$PAGE->set_url(new moodle_url('/local/kaznu/my.php', ['section' => $section]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('my_cabinet_title', 'local_kaznu'));
$PAGE->set_heading(get_string('my_cabinet_title', 'local_kaznu'));

// Refresh: confirm → logout → unified login → wantsurl with section.
// Note: brief logout is required for live Univer re-sync; failed login does not
// create a second session (old session already ended after confirm).
$refreshconfirm = optional_param('refresh_confirm', 0, PARAM_BOOL);
if ($refreshconfirm && $islinked && confirm_sesskey()) {
    $wants = (new moodle_url('/local/kaznu/my.php', ['section' => $section]))->out(false);
    require_logout();
    redirect(new moodle_url('/login/index.php', ['wantsurl' => $wants]));
}

$showrefreshconfirm = $islinked && optional_param('refresh', 0, PARAM_BOOL);
if ($showrefreshconfirm) {
    $PAGE->set_url(new moodle_url('/local/kaznu/my.php', ['section' => $section, 'refresh' => 1]));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('my_univer_refresh_confirm', 'local_kaznu'),
        new moodle_url('/local/kaznu/my.php', [
            'section' => $section,
            'refresh_confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        new moodle_url('/local/kaznu/my.php', ['section' => $section])
    );
    echo $OUTPUT->footer();
    exit;
}

$cab = (new student_dashboard_service())->get_cabinet($userid, $section);
$profile = $cab['profile'];
// Trust link policy first.
if (!$islinked) {
    $profile = null;
}
$subjects = $cab['subjects'];
$status = $cab['status'];

$refreshaction = '';
if ($islinked) {
    $refreshaction = html_writer::link(
        new moodle_url('/local/kaznu/my.php', [
            'section' => $section,
            'refresh' => 1,
        ]),
        get_string('my_univer_refresh', 'local_kaznu'),
        ['class' => 'btn btn-secondary']
    );
}

/**
 * @param array{freshness:string,lastsuccess:int,lasterror:int,errorcode:string} $st
 */
function local_kaznu_my_freshness_banner(array $st): string {
    $f = $st['freshness'];
    $cls = 'local-kaznu-fresh local-kaznu-fresh-' . $f;
    $msg = match ($f) {
        'fresh' => get_string('my_univer_status_fresh', 'local_kaznu'),
        'stale' => get_string('my_univer_status_stale_data', 'local_kaznu'),
        'unavailable' => get_string('my_univer_status_unavailable', 'local_kaznu'),
        default => get_string('my_univer_status_empty', 'local_kaznu'),
    };
    $when = '';
    if (!empty($st['lastsuccess'])) {
        $when = ' · ' . get_string('my_univer_last_sync', 'local_kaznu', userdate((int) $st['lastsuccess']));
    }
    return html_writer::div(s($msg . $when), $cls);
}

echo $OUTPUT->header();
if ($islinked) {
    echo html_writer::div($refreshaction, 'local-kaznu-my-actions mb-3');
}

// Section nav: active key matches section.
$tabs = [];
foreach ($allowed as $key) {
    $tabs[] = new tabobject(
        $key,
        new moodle_url('/local/kaznu/my.php', ['section' => $key]),
        get_string('my_univer_tab_' . $key, 'local_kaznu')
    );
}
echo $OUTPUT->tabtree($tabs, $section);

// ——— Overview: profile (+ Univer subjects if linked) ———
if ($section === 'overview') {
    echo html_writer::start_div('local-kaznu-my-section local-kaznu-my-profile');
    echo $OUTPUT->heading(get_string('my_univer_profile', 'local_kaznu'), 3);

    echo html_writer::start_div('local-kaznu-my-profile-head d-flex align-items-center mb-3');
    echo html_writer::div($OUTPUT->user_picture($USER, ['size' => 80, 'link' => false]), 'local-kaznu-my-avatar mr-3');
    echo html_writer::div(
        html_writer::tag('div', s(fullname($USER)), ['class' => 'local-kaznu-my-fullname h4 mb-0'])
        . html_writer::tag('div', s($USER->username), ['class' => 'text-muted small']),
        'local-kaznu-my-nameblock'
    );
    echo html_writer::end_div();

    if ($profile) {
        $rows = [
            get_string('my_univer_faculty', 'local_kaznu') => $profile->faculty ?: '—',
            get_string('my_univer_speciality', 'local_kaznu') => $profile->speciality ?: '—',
            get_string('my_univer_course_year', 'local_kaznu') => (string) ((int) $profile->course_number ?: '—'),
            get_string('my_univer_group', 'local_kaznu') => $profile->group_name ?: '—',
        ];
        if ((int) $profile->current_year > 0 || (int) $profile->current_semester > 0) {
            $rows[get_string('my_univer_semester', 'local_kaznu')] = get_string(
                'my_univer_semester_value',
                'local_kaznu',
                (object) [
                    'year' => (int) $profile->current_year,
                    'semester' => (int) $profile->current_semester,
                ]
            );
        }
        echo html_writer::start_tag('dl', ['class' => 'local-kaznu-my-dl']);
        foreach ($rows as $label => $value) {
            if ($value === '—' || $value === '') {
                continue;
            }
            echo html_writer::tag('dt', s($label));
            echo html_writer::tag('dd', s($value));
        }
        echo html_writer::end_tag('dl');

        $lastsync = 0;
        foreach ($status as $st) {
            $lastsync = max($lastsync, (int) ($st['lastsuccess'] ?? 0));
        }
        if ($lastsync <= 0 && !empty($profile->lastsync)) {
            $lastsync = (int) $profile->lastsync;
        }
        if ($lastsync > 0) {
            echo html_writer::tag(
                'p',
                s(get_string('my_univer_last_sync', 'local_kaznu', userdate($lastsync))),
                ['class' => 'local-kaznu-my-sync text-muted small']
            );
        }
    } else {
        echo html_writer::div(
            get_string('my_cabinet_courses_only', 'local_kaznu'),
            'alert alert-info local-kaznu-my-unlinked'
        );
    }
    echo html_writer::end_div();

    if ($islinked) {
        echo html_writer::start_div('local-kaznu-my-section');
        echo $OUTPUT->heading(get_string('my_cabinet_univer_subjects', 'local_kaznu'), 3);
        if (!$subjects) {
            echo html_writer::tag('p', get_string('my_univer_no_subjects', 'local_kaznu'));
        } else {
            // Batch-resolve courses (no N+1 idnumber / exists checks).
            $courseids = [];
            $idnumbers = [];
            foreach ($subjects as $row) {
                $cid = (int) $row->moodlecourseid;
                if ($cid > 1) {
                    $courseids[$cid] = $cid;
                }
                if (!empty($row->course_idnumber)) {
                    $idnumbers[(string) $row->course_idnumber] = (string) $row->course_idnumber;
                }
            }
            $byid = $courseids ? $DB->get_records_list('course', 'id', array_values($courseids), '', 'id') : [];
            $byidn = [];
            if ($idnumbers) {
                list($insql, $inparams) = $DB->get_in_or_equal(array_values($idnumbers), SQL_PARAMS_NAMED, 'idn');
                $found = $DB->get_records_select('course', "idnumber {$insql}", $inparams, '', 'id,idnumber');
                foreach ($found as $c) {
                    $byidn[(string) $c->idnumber] = (int) $c->id;
                }
            }
            $enrolledcache = [];

            $table = new html_table();
            $table->attributes['class'] = 'generaltable local-kaznu-my-subjects';
            $table->head = [
                get_string('my_univer_col_name', 'local_kaznu'),
                get_string('my_univer_col_status', 'local_kaznu'),
                get_string('my_univer_col_moodle', 'local_kaznu'),
                get_string('my_univer_col_action', 'local_kaznu'),
            ];
            foreach ($subjects as $row) {
                $statuskey = 'my_univer_status_' . (string) $row->status;
                $statuslabel = get_string_manager()->string_exists($statuskey, 'local_kaznu')
                    ? get_string($statuskey, 'local_kaznu')
                    : s((string) $row->status);
                $courseid = (int) $row->moodlecourseid;
                $hascourse = $courseid > 1 && isset($byid[$courseid]);
                if (!$hascourse && !empty($row->course_idnumber) && isset($byidn[(string) $row->course_idnumber])) {
                    $courseid = $byidn[(string) $row->course_idnumber];
                    $hascourse = true;
                }
                $canopen = false;
                if ($hascourse) {
                    if (!array_key_exists($courseid, $enrolledcache)) {
                        $ctx = context_course::instance($courseid, IGNORE_MISSING);
                        $enrolledcache[$courseid] = ($ctx && is_enrolled($ctx, $USER, '', true));
                    }
                    $canopen = $enrolledcache[$courseid];
                }
                if ($canopen) {
                    $moodlecell = get_string('my_univer_has_course', 'local_kaznu');
                    $action = html_writer::link(
                        new moodle_url('/local/kaznu/course.php', ['id' => $courseid]),
                        get_string('my_univer_open_course', 'local_kaznu'),
                        ['class' => 'btn btn-primary btn-sm']
                    );
                } else if ($hascourse) {
                    $moodlecell = get_string('my_univer_has_course', 'local_kaznu');
                    $action = '—';
                } else {
                    $moodlecell = get_string('my_univer_course_unpublished', 'local_kaznu');
                    $action = '—';
                }
                $table->data[] = [format_string((string) $row->subjectname), $statuslabel, $moodlecell, $action];
            }
            echo html_writer::table($table);
        }
        echo html_writer::end_div();
    }
}

// ——— Courses (common tab for linked + unlinked) ———
if ($section === 'courses') {
    echo html_writer::start_div('local-kaznu-my-section');
    echo $OUTPUT->heading(get_string('my_cabinet_moodle_courses', 'local_kaznu'), 3);
    $mycourses = enrol_get_my_courses('id,fullname,shortname,visible', 'fullname ASC');
    $visiblecourses = array_filter($mycourses, static fn($c) => (int) $c->id > 1 && (int) $c->visible === 1);
    if (!$visiblecourses) {
        echo html_writer::tag('p', get_string('my_cabinet_no_moodle_courses', 'local_kaznu'));
    } else {
        $t = new html_table();
        $t->attributes['class'] = 'generaltable local-kaznu-my-courses';
        $t->head = [
            get_string('my_univer_col_name', 'local_kaznu'),
            get_string('my_univer_col_action', 'local_kaznu'),
        ];
        foreach ($visiblecourses as $c) {
            $t->data[] = [
                format_string($c->fullname),
                html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $c->id]),
                    get_string('my_univer_open_course', 'local_kaznu'),
                    ['class' => 'btn btn-primary btn-sm']
                ),
            ];
        }
        echo html_writer::table($t);
    }
    echo html_writer::end_div();
}

// ——— Schedule ———
if ($section === 'schedule') {
    echo html_writer::div(get_string('my_univer_readonly_note', 'local_kaznu'), 'local-kaznu-readonly alert alert-info');
    echo local_kaznu_my_freshness_banner($status[cache_module::SCHEDULE] ?? ['freshness' => 'empty', 'lastsuccess' => 0]);

    $weekdaynames = [
        1 => get_string('my_univer_weekday_1', 'local_kaznu'),
        2 => get_string('my_univer_weekday_2', 'local_kaznu'),
        3 => get_string('my_univer_weekday_3', 'local_kaznu'),
        4 => get_string('my_univer_weekday_4', 'local_kaznu'),
        5 => get_string('my_univer_weekday_5', 'local_kaznu'),
        6 => get_string('my_univer_weekday_6', 'local_kaznu'),
        7 => get_string('my_univer_weekday_7', 'local_kaznu'),
    ];
    // Moodle wday: 0=Sun..6=Sat → map to Univer 1=Mon..7=Sun roughly for “today”.
    $moodlew = (int) userdate(time(), '%w');
    $todayuniver = $moodlew === 0 ? 7 : $moodlew;

    echo $OUTPUT->heading(get_string('my_univer_schedule_today', 'local_kaznu'), 3);
    $today = array_values(array_filter($cab['lessons'], static fn($r) => (int) $r->weekday === $todayuniver));
    if (!$today) {
        echo html_writer::tag('p', get_string('my_univer_schedule_empty_today', 'local_kaznu'));
    } else {
        $t = new html_table();
        $t->attributes['class'] = 'generaltable';
        $t->head = [
            get_string('my_univer_col_time', 'local_kaznu'),
            get_string('my_univer_col_subject', 'local_kaznu'),
            get_string('my_univer_col_type', 'local_kaznu'),
            get_string('my_univer_col_room', 'local_kaznu'),
            get_string('my_univer_col_teacher', 'local_kaznu'),
        ];
        foreach ($today as $r) {
            $room = trim(($r->building ? $r->building . ' ' : '') . $r->audience);
            $t->data[] = [
                s($r->timebegin . '–' . $r->timeend),
                format_string((string) $r->subjectname),
                s($r->lesson_type),
                s($room !== '' ? $room : '—'),
                s($r->teacher !== '' ? $r->teacher : '—'),
            ];
        }
        echo html_writer::table($t);
    }

    echo $OUTPUT->heading(get_string('my_univer_schedule_week', 'local_kaznu'), 3);
    if (!$cab['lessons']) {
        echo html_writer::tag('p', get_string('my_univer_schedule_empty', 'local_kaznu'));
    } else {
        $t = new html_table();
        $t->attributes['class'] = 'generaltable';
        $t->head = [
            get_string('my_univer_col_day', 'local_kaznu'),
            get_string('my_univer_col_time', 'local_kaznu'),
            get_string('my_univer_col_subject', 'local_kaznu'),
            get_string('my_univer_col_type', 'local_kaznu'),
            get_string('my_univer_col_room', 'local_kaznu'),
            get_string('my_univer_col_teacher', 'local_kaznu'),
        ];
        foreach ($cab['lessons'] as $r) {
            $room = trim(($r->building ? $r->building . ' ' : '') . $r->audience);
            $t->data[] = [
                s($weekdaynames[(int) $r->weekday] ?? (string) $r->weekday),
                s($r->timebegin . '–' . $r->timeend),
                format_string((string) $r->subjectname),
                s($r->lesson_type),
                s($room !== '' ? $room : '—'),
                s($r->teacher !== '' ? $r->teacher : '—'),
            ];
        }
        echo html_writer::table($t);
    }

    echo local_kaznu_my_freshness_banner($status[cache_module::EXAM] ?? ['freshness' => 'empty', 'lastsuccess' => 0]);
    echo $OUTPUT->heading(get_string('my_univer_exams', 'local_kaznu'), 3);
    if (!$cab['exams']) {
        echo html_writer::tag('p', get_string('my_univer_exams_empty', 'local_kaznu'));
    } else {
        $t = new html_table();
        $t->attributes['class'] = 'generaltable';
        $t->head = [
            get_string('my_univer_col_date', 'local_kaznu'),
            get_string('my_univer_col_time', 'local_kaznu'),
            get_string('my_univer_col_subject', 'local_kaznu'),
            get_string('my_univer_col_type', 'local_kaznu'),
            get_string('my_univer_col_room', 'local_kaznu'),
            get_string('my_univer_col_teacher', 'local_kaznu'),
        ];
        foreach ($cab['exams'] as $r) {
            $room = trim(($r->building ? $r->building . ' ' : '') . $r->audience);
            $t->data[] = [
                s((int) $r->eventdate > 0 ? univer_datetime::format_userdate((int) $r->eventdate) : '—'),
                s($r->eventtime ?: $r->timebegin),
                format_string((string) $r->subjectname),
                s($r->lesson_type),
                s($room !== '' ? $room : '—'),
                s($r->teacher !== '' ? $r->teacher : '—'),
            ];
        }
        echo html_writer::table($t);
    }

    echo local_kaznu_my_freshness_banner($status[cache_module::MIDTERM] ?? ['freshness' => 'empty', 'lastsuccess' => 0]);
    echo $OUTPUT->heading(get_string('my_univer_midterms', 'local_kaznu'), 3);
    if (!$cab['midterms']) {
        echo html_writer::tag('p', get_string('my_univer_midterms_empty', 'local_kaznu'));
    } else {
        $t = new html_table();
        $t->attributes['class'] = 'generaltable';
        $t->head = [
            get_string('my_univer_col_date', 'local_kaznu'),
            get_string('my_univer_col_time', 'local_kaznu'),
            get_string('my_univer_col_subject', 'local_kaznu'),
            get_string('my_univer_col_type', 'local_kaznu'),
            get_string('my_univer_col_room', 'local_kaznu'),
        ];
        foreach ($cab['midterms'] as $r) {
            $room = trim(($r->building ? $r->building . ' ' : '') . $r->audience);
            $t->data[] = [
                s((int) $r->eventdate > 0 ? univer_datetime::format_userdate((int) $r->eventdate) : '—'),
                s($r->eventtime ?: $r->timebegin),
                format_string((string) $r->subjectname),
                s($r->lesson_type),
                s($room !== '' ? $room : '—'),
            ];
        }
        echo html_writer::table($t);
    }
}

// ——— Grades (official Univer assessments; alias assessments) ———
if ($section === 'grades') {
    echo html_writer::div(get_string('my_univer_official_grades_note', 'local_kaznu'), 'local-kaznu-readonly alert alert-info');
    echo local_kaznu_my_freshness_banner($status[cache_module::ASSESSMENT] ?? ['freshness' => 'empty', 'lastsuccess' => 0]);
    echo $OUTPUT->heading(get_string('my_univer_assessments', 'local_kaznu'), 3);
    if (!$cab['assessments']) {
        echo html_writer::tag('p', get_string('my_univer_assessments_empty', 'local_kaznu'));
    } else {
        $t = new html_table();
        $t->attributes['class'] = 'generaltable';
        $t->head = [
            get_string('my_univer_col_subject', 'local_kaznu'),
            get_string('my_univer_col_credit', 'local_kaznu'),
            get_string('my_univer_col_rk1', 'local_kaznu'),
            get_string('my_univer_col_rk2', 'local_kaznu'),
            get_string('my_univer_col_score', 'local_kaznu'),
            get_string('my_univer_col_total', 'local_kaznu'),
            get_string('my_univer_col_gpa', 'local_kaznu'),
            get_string('my_univer_col_letter', 'local_kaznu'),
        ];
        foreach ($cab['assessments'] as $r) {
            $t->data[] = [
                format_string((string) $r->subjectname),
                s($r->credit),
                s($r->resultrk1),
                s($r->resultrk2),
                s($r->result),
                s($r->totalres),
                s($r->gpa),
                s(trim($r->symbol . ' ' . $r->lettertext)),
            ];
        }
        echo html_writer::table($t);
    }
}

// ——— Attendance ———
if ($section === 'attendance') {
    echo html_writer::div(get_string('my_univer_readonly_note', 'local_kaznu'), 'local-kaznu-readonly alert alert-info');
    echo local_kaznu_my_freshness_banner($status[cache_module::ATTENDANCE] ?? ['freshness' => 'empty', 'lastsuccess' => 0]);
    echo $OUTPUT->heading(get_string('my_univer_attendance', 'local_kaznu'), 3);
    echo html_writer::tag('p', get_string('my_univer_attendance_rates_note', 'local_kaznu'), ['class' => 'text-muted']);
    if (!$cab['attendance']) {
        echo html_writer::tag('p', get_string('my_univer_attendance_empty', 'local_kaznu'));
    } else {
        $t = new html_table();
        $t->attributes['class'] = 'generaltable';
        $t->head = [
            get_string('my_univer_col_subject', 'local_kaznu'),
            get_string('my_univer_col_period', 'local_kaznu'),
            get_string('my_univer_col_rate1', 'local_kaznu'),
            get_string('my_univer_col_rate2', 'local_kaznu'),
        ];
        foreach ($cab['attendance'] as $r) {
            $t->data[] = [
                format_string((string) $r->subjectname),
                s($r->period),
                s($r->rate1 !== '' ? $r->rate1 : '—'),
                s($r->rate2 !== '' ? $r->rate2 : '—'),
            ];
        }
        echo html_writer::table($t);
    }
}

echo $OUTPUT->footer();
