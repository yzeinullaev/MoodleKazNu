<?php
defined('MOODLE_INTERNAL') || die();

/** Demo course shortname. */
const LOCAL_KAZNU_COURSE_SHORTNAME = 'SUMMER2026';

/**
 * Demo course record or false.
 */
function local_kaznu_get_demo_course(): ?stdClass {
    global $DB;
    return $DB->get_record('course', ['shortname' => LOCAL_KAZNU_COURSE_SHORTNAME]) ?: null;
}

/**
 * Payment confirmation token (demo).
 */
function local_kaznu_payment_token(): string {
    $token = get_config('local_kaznu', 'paymenttoken');
    return $token ?: 'DEMO-KZN-2026';
}

/**
 * Whether the user is enrolled in the demo course.
 */
function local_kaznu_is_enrolled(int $userid): bool {
    $course = local_kaznu_get_demo_course();
    if (!$course) {
        return false;
    }
    $context = context_course::instance($course->id);
    return is_enrolled($context, $userid);
}

/**
 * Enrol user into the demo course (manual enrolment).
 */
function local_kaznu_enrol_user(stdClass $course, int $userid, string $roleshortname = 'student'): bool {
    global $DB;

    $role = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
    $plugin = enrol_get_plugin('manual');
    $instance = null;

    foreach (enrol_get_instances($course->id, true) as $inst) {
        if ($inst->enrol === 'manual') {
            $instance = $inst;
            break;
        }
    }

    if (!$instance) {
        $id = $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    if ($DB->record_exists('user_enrolments', ['userid' => $userid, 'enrolid' => $instance->id])) {
        return false;
    }

    $plugin->enrol_user($instance, $userid, $role->id);
    return true;
}

/**
 * URL for payment confirmation (used in QR and pay link).
 */
function local_kaznu_confirm_url(): moodle_url {
    return new moodle_url('/local/kaznu/pay.php', [
        'action' => 'confirm',
        'token' => local_kaznu_payment_token(),
    ]);
}
