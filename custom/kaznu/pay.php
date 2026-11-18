<?php
/**
 * Payment confirm endpoint + redirect.
 * Summer school is retired — students are sent to the catalogue.
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_capability('local/kaznu:viewpay', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);
$courseid = optional_param('id', 0, PARAM_INT);

if ($courseid > 1) {
    $course = get_course($courseid);
} else {
    $course = local_kaznu_get_demo_course();
}

$landing = new moodle_url('/local/kaznu/landing.php');

// Summer school retired: no enrol / pay for students.
if (!$course || local_kaznu_is_retired_course($course)) {
    redirect(
        $landing,
        get_string('course_retired', 'local_kaznu'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$confirmurl = local_kaznu_confirm_url((int) $course->id);
$huburl = new moodle_url('/local/kaznu/course.php', ['id' => $course->id], 'enrol');

if ($action === 'confirm') {
    if ($token !== local_kaznu_payment_token()) {
        throw new moodle_exception('invalidtoken', 'local_kaznu');
    }

    if (!isloggedin() || isguestuser()) {
        redirect(new moodle_url('/login/index.php', [
            'wantsurl' => $confirmurl->out(false),
        ]));
    }

    local_kaznu_enrol_user($course, (int) $USER->id, 'student');
    redirect(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('enrolsuccess', 'local_kaznu'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

redirect($huburl);
