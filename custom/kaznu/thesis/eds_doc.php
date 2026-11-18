<?php
/**
 * Download approval XML for signing in eGov mobile (open link from QR on phone).
 *
 * @package local_kaznu
 */

require_once(__DIR__ . '/../../../config.php');

use local_kaznu\service\thesis_service;

require_login();
require_sesskey();

$sys = context_system::instance();
$id = required_param('id', PARAM_INT);

thesis_service::require_enabled();
$sub = thesis_service::get($id);
if (!$sub) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}

$cansupervise = has_capability('local/kaznu:thesissupervise', $sys)
    || has_capability('local/kaznu:thesismanage', $sys)
    || is_siteadmin();
if (!thesis_service::can_access($sub, (int) $USER->id, is_siteadmin(), $cansupervise)) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}
$sig = thesis_service::get_signature($id);
$stubonly = $sub->status === thesis_service::STATUS_APPROVED
    && $sig && ($sig->sigtype ?? '') === 'stub';
if ($sub->status !== thesis_service::STATUS_SUBMITTED && !$stubonly) {
    throw new moodle_exception('thesis_error_locked', 'local_kaznu');
}

$payload = thesis_service::build_eds_sign_payload($id);
$filename = 'farabi_thesis_approval_' . $id . '.xml';

header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $payload['xml'];