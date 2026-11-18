<?php
namespace local_kaznu\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Thesis workflow: AI format review, plagiarism demo, EDS clearance.
 */
final class thesis_service {
    public const FILEAREA = 'thesis_submission';
    public const COMPONENT = 'local_kaznu';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CHECKED = 'checked';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION = 'revision';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public static function is_enabled(): bool {
        return (int) get_config('local_kaznu', 'thesis_enabled') === 1;
    }

    public static function require_enabled(): void {
        if (!self::is_enabled()) {
            throw new \moodle_exception('thesis_disabled', 'local_kaznu');
        }
    }

    public static function ensure_default_ruleset(): int {
        return self::ruleset_id_for_worktype('master');
    }

    public static function ruleset_id_for_worktype(string $worktype): int {
        global $DB;
        $worktype = $worktype === 'dba' ? 'dba' : ($worktype === 'phd_draft' ? 'phd_draft' : $worktype);
        $existing = $DB->get_field('local_kaznu_thesis_rule', 'id', ['worktype' => $worktype, 'enabled' => 1]);
        if ($existing) {
            return (int) $existing;
        }
        $now = time();
        if ($worktype === 'dba') {
            $rules = thesis_dba_analyzer::rules();
            $name = 'DBA KazNU / Farabi IBS (2026)';
        } else {
            $rules = [
                'page' => ['size' => 'A4', 'margin_mm' => ['top' => 20, 'bottom' => 20, 'left' => 30, 'right' => 15]],
                'body' => ['font' => ['Times New Roman', 'TimesNewRoman'], 'size_pt' => 14, 'line_spacing' => 1.5],
                'limits' => ['min_pages' => 20, 'max_pages' => 150],
            ];
            $name = 'Базовый набор Farabi';
        }
        return (int) $DB->insert_record('local_kaznu_thesis_rule', (object) [
            'name' => $name,
            'worktype' => $worktype,
            'enabled' => 1,
            'rulesjson' => json_encode($rules, JSON_UNESCAPED_UNICODE),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /** @return array<string,mixed> */
    public static function rules_for_submission(\stdClass $sub): array {
        global $DB;
        $worktype = (string) ($sub->worktype ?? 'master');
        if ($worktype === 'dba') {
            return thesis_dba_analyzer::rules();
        }
        if (!empty($sub->rulesetid)) {
            $json = $DB->get_field('local_kaznu_thesis_rule', 'rulesjson', ['id' => (int) $sub->rulesetid]);
            $rules = json_decode((string) $json, true);
            if (is_array($rules)) {
                return $rules;
            }
        }
        return [
            'page' => ['margin_mm' => ['top' => 20, 'bottom' => 20, 'left' => 30, 'right' => 15]],
            'body' => ['font' => ['Times New Roman'], 'size_pt' => 14, 'line_spacing' => 1.5],
            'limits' => ['min_pages' => 20, 'max_pages' => 150],
        ];
    }

    public static function empty_report(): array {
        return [
            'version' => 2,
            'disclaimer' => get_string('thesis_disclaimer', 'local_kaznu'),
            'ai' => [
                'status' => 'pending',
                'score' => null,
                'passed' => null,
                'summary' => '',
                'checks' => [],
                'hints' => [],
                'engine' => null,
                'timemodified' => 0,
            ],
            'plagiarism' => [
                'status' => 'pending',
                'originality' => null,
                'similarity' => null,
                'passed' => null,
                'threshold' => 80,
                'sources' => [],
                'engine' => 'farabi-demo',
                'mode' => 'demo',
                'summary' => '',
                'timemodified' => 0,
            ],
            'eds' => [
                'status' => 'waiting',
                'signed' => false,
                'signer' => '',
                'sigtype' => null,
                'timemodified' => 0,
            ],
        ];
    }

    public static function decode_report(?string $json): array {
        $data = json_decode((string) $json, true);
        if (!is_array($data) || empty($data['version'])) {
            $base = self::empty_report();
            if (is_array($data) && !empty($data['checks'])) {
                $base['ai']['status'] = 'done';
                $base['ai']['score'] = (int) ($data['score'] ?? 0);
                $base['ai']['passed'] = !empty($data['passed']);
                $base['ai']['checks'] = $data['checks'];
                $base['ai']['summary'] = (string) ($data['summary_ru'] ?? '');
                $base['ai']['engine'] = (string) ($data['engine'] ?? 'legacy');
            }
            $data = $base;
        } else {
            $data = array_replace_recursive(self::empty_report(), $data);
        }
        return self::normalize_report_labels($data);
    }

  /**
   * Human-readable labels even when lang cache is stale or strings are missing on server.
   */
    public static function normalize_report_labels(array $report): array {
        if (!empty($report['ai']['checks']) && is_array($report['ai']['checks'])) {
            $report['ai']['checks'] = thesis_dba_analyzer::apply_check_labels($report['ai']['checks']);
            $hints = [];
            foreach ($report['ai']['checks'] as $c) {
                if (empty($c['ok'])) {
                    $hints[] = 'Исправьте: ' . ($c['label'] ?? '');
                }
            }
            $report['ai']['hints'] = $hints;
        }
        if (!empty($report['ai']['checklist']) && is_array($report['ai']['checklist'])) {
            $report['ai']['checklist'] = thesis_dba_analyzer::enrich_checklist($report['ai']['checklist']);
        }
        return $report;
    }

    public static function save_report(\stdClass $sub, array $report): void {
        global $DB;
        $sub->reportjson = json_encode(self::normalize_report_labels($report), JSON_UNESCAPED_UNICODE);
        $sub->timemodified = time();
        if (($report['ai']['status'] ?? '') === 'done' && ($report['plagiarism']['status'] ?? '') === 'done'
                && in_array($sub->status, [self::STATUS_DRAFT, self::STATUS_CHECKED, self::STATUS_REVISION], true)) {
            $sub->status = self::STATUS_CHECKED;
        }
        $DB->update_record('local_kaznu_thesis_sub', $sub);
    }

    public static function list_for_user(int $userid): array {
        global $DB;
        return array_values($DB->get_records('local_kaznu_thesis_sub', ['userid' => $userid], 'timemodified DESC'));
    }

    public static function list_for_supervisor(int $supervisorid, bool $manageall = false): array {
        global $DB;
        if ($manageall) {
            return array_values($DB->get_records_select(
                'local_kaznu_thesis_sub',
                "status IN ('submitted','revision','approved','rejected')",
                null,
                'timemodified DESC'
            ));
        }
        return array_values($DB->get_records_select(
            'local_kaznu_thesis_sub',
            "supervisorid = ? AND status IN ('submitted','revision','approved','rejected')",
            [$supervisorid],
            'timemodified DESC'
        ));
    }

    public static function get(int $id): ?\stdClass {
        global $DB;
        $rec = $DB->get_record('local_kaznu_thesis_sub', ['id' => $id]);
        return $rec ?: null;
    }

    public static function log_event(int $submissionid, int $userid, string $event, array $detail = []): void {
        global $DB;
        $DB->insert_record('local_kaznu_thesis_evt', (object) [
            'submissionid' => $submissionid,
            'userid' => $userid,
            'eventname' => $event,
            'detailjson' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            'timecreated' => time(),
        ]);
    }

    public static function create_from_upload(int $userid, string $title, int $supervisorid, string $worktype, array $fileinfo): \stdClass {
        global $DB;
        self::require_enabled();
        $title = trim($title);
        if ($title === '') {
            throw new \invalid_parameter_exception('title');
        }
        if (empty($fileinfo['tmp_name']) || !is_uploaded_file($fileinfo['tmp_name'])) {
            throw new \moodle_exception('thesis_error_nofile', 'local_kaznu');
        }
        $ext = strtolower(pathinfo($fileinfo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'doc', 'pdf'], true)) {
            throw new \moodle_exception('thesis_error_filetype', 'local_kaznu');
        }
        if ((int) $fileinfo['size'] > 50 * 1024 * 1024) {
            throw new \moodle_exception('thesis_error_filesize', 'local_kaznu');
        }

        $now = time();
        $hash = hash_file('sha256', $fileinfo['tmp_name']);
        $id = $DB->insert_record('local_kaznu_thesis_sub', (object) [
            'userid' => $userid,
            'supervisorid' => $supervisorid,
            'rulesetid' => self::ruleset_id_for_worktype($worktype),
            'title' => \core_text::substr($title, 0, 500),
            'worktype' => $worktype,
            'status' => self::STATUS_DRAFT,
            'contenthash' => $hash,
            'reportjson' => json_encode(self::empty_report(), JSON_UNESCAPED_UNICODE),
            'supervisorcomment' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $context = \context_system::instance();
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, self::COMPONENT, self::FILEAREA, $id);
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'filearea' => self::FILEAREA,
            'itemid' => $id,
            'filepath' => '/',
            'filename' => clean_param($fileinfo['name'], PARAM_FILE),
            'userid' => $userid,
        ], $fileinfo['tmp_name']);

        self::log_event($id, $userid, 'upload', ['hash' => $hash, 'ext' => $ext]);
        return self::get($id);
    }

    public static function replace_file(int $submissionid, int $userid, array $fileinfo): \stdClass {
        global $DB;
        $sub = self::get($submissionid);
        if (!$sub || (int) $sub->userid !== $userid) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        if (!in_array($sub->status, [self::STATUS_DRAFT, self::STATUS_CHECKED, self::STATUS_REVISION], true)) {
            throw new \moodle_exception('thesis_error_locked', 'local_kaznu');
        }
        if (empty($fileinfo['tmp_name']) || !is_uploaded_file($fileinfo['tmp_name'])) {
            throw new \moodle_exception('thesis_error_nofile', 'local_kaznu');
        }
        $ext = strtolower(pathinfo($fileinfo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'doc', 'pdf'], true)) {
            throw new \moodle_exception('thesis_error_filetype', 'local_kaznu');
        }

        $hash = hash_file('sha256', $fileinfo['tmp_name']);
        $context = \context_system::instance();
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, self::COMPONENT, self::FILEAREA, $submissionid);
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'filearea' => self::FILEAREA,
            'itemid' => $submissionid,
            'filepath' => '/',
            'filename' => clean_param($fileinfo['name'], PARAM_FILE),
            'userid' => $userid,
        ], $fileinfo['tmp_name']);

        $DB->delete_records('local_kaznu_thesis_sig', ['submissionid' => $submissionid]);
        $sub->contenthash = $hash;
        $sub->reportjson = json_encode(self::empty_report(), JSON_UNESCAPED_UNICODE);
        $sub->status = self::STATUS_DRAFT;
        $sub->timemodified = time();
        $DB->update_record('local_kaznu_thesis_sub', $sub);
        self::log_event($submissionid, $userid, 'reupload', ['hash' => $hash]);
        return self::get($submissionid);
    }

    public static function can_access(\stdClass $sub, int $userid, bool $ismanage, bool $cansupervise): bool {
        if ((int) $sub->userid === $userid) {
            return true;
        }
        if ($ismanage) {
            return true;
        }
        if ($cansupervise && (int) $sub->supervisorid === $userid) {
            return true;
        }
        return false;
    }

    public static function run_ai_check(int $submissionid, int $userid): array {
        $sub = self::get($submissionid);
        if (!$sub || (int) $sub->userid !== $userid) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        $file = self::get_stored_file($submissionid);
        if (!$file) {
            throw new \moodle_exception('thesis_error_nofile', 'local_kaznu');
        }

        $tmp = $file->copy_content_to_temp();
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $parsed = self::parse_document($tmp, $ext, (int) $file->get_filesize());
        @unlink($tmp);

        $ai = self::build_ai_section($parsed, $sub->title, (string) ($sub->worktype ?? 'master'), self::rules_for_submission($sub));
        $report = self::decode_report($sub->reportjson);
        $report['ai'] = $ai;
        self::save_report($sub, $report);
        self::log_event($submissionid, $userid, 'ai_check', ['score' => $ai['score'], 'engine' => $ai['engine']]);
        return $report;
    }

    public static function run_plagiarism_check(int $submissionid, int $userid): array {
        $sub = self::get($submissionid);
        if (!$sub || (int) $sub->userid !== $userid) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        $file = self::get_stored_file($submissionid);
        if (!$file) {
            throw new \moodle_exception('thesis_error_nofile', 'local_kaznu');
        }

        $tmp = $file->copy_content_to_temp();
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $parsed = self::parse_document($tmp, $ext, (int) $file->get_filesize());
        @unlink($tmp);

        $plag = self::build_plagiarism_section($parsed, $sub->contenthash);
        $report = self::decode_report($sub->reportjson);
        $report['plagiarism'] = $plag;
        self::save_report($sub, $report);
        self::log_event($submissionid, $userid, 'plagiarism_check', [
            'originality' => $plag['originality'],
            'mode' => $plag['mode'],
        ]);
        return $report;
    }

    public static function submit_to_supervisor(int $submissionid, int $userid): \stdClass {
        global $DB;
        $sub = self::get($submissionid);
        if (!$sub || (int) $sub->userid !== $userid) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        $report = self::decode_report($sub->reportjson);
        if (($report['ai']['status'] ?? '') !== 'done' || ($report['plagiarism']['status'] ?? '') !== 'done') {
            throw new \moodle_exception('thesis_error_checks_required', 'local_kaznu');
        }
        if ((int) $sub->supervisorid <= 0) {
            throw new \moodle_exception('thesis_error_nosupervisor', 'local_kaznu');
        }
        if (!in_array($sub->status, [self::STATUS_CHECKED, self::STATUS_REVISION, self::STATUS_DRAFT], true)) {
            throw new \moodle_exception('thesis_error_locked', 'local_kaznu');
        }
        $sub->status = self::STATUS_SUBMITTED;
        $sub->timemodified = time();
        $DB->update_record('local_kaznu_thesis_sub', $sub);
        self::log_event($submissionid, $userid, 'submitted');
        return self::get($submissionid);
    }

    public static function build_eds_sign_payload(int $submissionid): array {
        $sub = self::get($submissionid);
        if (!$sub) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        $student = \core_user::get_user((int) $sub->userid);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<farabiThesisApproval>'
            . '<submissionId>' . (int) $sub->id . '</submissionId>'
            . '<title>' . htmlspecialchars($sub->title, ENT_XML1) . '</title>'
            . '<student>' . htmlspecialchars($student ? fullname($student) : '', ENT_XML1) . '</student>'
            . '<hash>' . $sub->contenthash . '</hash>'
            . '<time>' . time() . '</time>'
            . '</farabiThesisApproval>';
        return [
            'xml' => $xml,
            'hash' => $sub->contenthash,
            'title' => $sub->title,
        ];
    }

  /**
   * Approve with NCALayer CMS or eGov mobile .p7s upload.
   */
    public static function supervisor_approve_signed(
        int $submissionid,
        int $supervisorid,
        string $comment,
        string $sigtype,
        string $signature,
        bool $manageall = false
    ): \stdClass {
        global $DB;
        $sub = self::get($submissionid);
        if (!$sub) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        if (!$manageall && (int) $sub->supervisorid !== $supervisorid) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        if (!in_array($sub->status, [self::STATUS_SUBMITTED, self::STATUS_APPROVED], true)) {
            throw new \moodle_exception('thesis_error_locked', 'local_kaznu');
        }
        $existing = self::get_signature($submissionid);
        if ($existing && ($existing->sigtype ?? '') !== 'stub') {
            throw new \moodle_exception('thesis_eds_already_signed', 'local_kaznu');
        }
        $signature = trim($signature);
        if ($signature === '') {
            throw new \moodle_exception('thesis_eds_empty', 'local_kaznu');
        }
        $sigtype = in_array($sigtype, ['ncalayer', 'egov_mobile'], true) ? $sigtype : 'ncalayer';

        $report = self::decode_report($sub->reportjson);
        $sub->status = self::STATUS_APPROVED;
        $signer = \core_user::get_user($supervisorid);
        $DB->delete_records('local_kaznu_thesis_sig', ['submissionid' => $submissionid]);
        $DB->insert_record('local_kaznu_thesis_sig', (object) [
            'submissionid' => $submissionid,
            'signerid' => $supervisorid,
            'sigtype' => $sigtype,
            'filehash' => $sub->contenthash,
            'payload' => \core_text::substr($signature, 0, 65000),
            'certsubject' => ($sigtype === 'egov_mobile' ? 'eGov mobile / ' : 'ЭЦП НУЦ / ') . ($signer ? fullname($signer) : ''),
            'verifyok' => 1,
            'timecreated' => time(),
        ]);
        $report['eds'] = [
            'status' => 'signed',
            'signed' => true,
            'signer' => $signer ? fullname($signer) : '',
            'sigtype' => $sigtype,
            'timemodified' => time(),
        ];
        $sub->supervisorcomment = trim($comment);
        $sub->reportjson = json_encode($report, JSON_UNESCAPED_UNICODE);
        $sub->timemodified = time();
        $DB->update_record('local_kaznu_thesis_sub', $sub);
        self::log_event($submissionid, $supervisorid, 'eds_signed', ['sigtype' => $sigtype]);
        return self::get($submissionid);
    }

    public static function supervisor_decide(int $submissionid, int $supervisorid, string $decision, string $comment, bool $manageall = false): \stdClass {
        global $DB;
        $sub = self::get($submissionid);
        if (!$sub) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        if (!$manageall && (int) $sub->supervisorid !== $supervisorid) {
            throw new \moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        if ($sub->status !== self::STATUS_SUBMITTED) {
            throw new \moodle_exception('thesis_error_locked', 'local_kaznu');
        }

        $decision = strtolower($decision);
        $report = self::decode_report($sub->reportjson);
        if ($decision === 'approve') {
            throw new \moodle_exception('thesis_eds_approve_only_signed', 'local_kaznu');
        } else if ($decision === 'revision') {
            $sub->status = self::STATUS_REVISION;
            $report['eds']['status'] = 'revision';
            self::log_event($submissionid, $supervisorid, 'revision');
        } else if ($decision === 'reject') {
            $sub->status = self::STATUS_REJECTED;
            $report['eds']['status'] = 'rejected';
            self::log_event($submissionid, $supervisorid, 'rejected');
        } else {
            throw new \invalid_parameter_exception('decision');
        }

        $sub->supervisorcomment = trim($comment);
        $sub->reportjson = json_encode($report, JSON_UNESCAPED_UNICODE);
        $sub->timemodified = time();
        $DB->update_record('local_kaznu_thesis_sub', $sub);
        return self::get($submissionid);
    }

    public static function get_stored_file(int $submissionid): ?\stored_file {
        $context = \context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, self::COMPONENT, self::FILEAREA, $submissionid, 'filename', false);
        return $files ? reset($files) : null;
    }

    public static function get_signature(int $submissionid): ?\stdClass {
        global $DB;
        $recs = $DB->get_records('local_kaznu_thesis_sig', ['submissionid' => $submissionid], 'id DESC', '*', 0, 1);
        return $recs ? reset($recs) : null;
    }

    public static function supervisor_options(): array {
        global $DB, $CFG;
        $sys = \context_system::instance();
        $users = get_users_by_capability($sys, 'local/kaznu:thesissupervise', 'u.id,u.firstname,u.lastname,u.username', 'u.lastname ASC');
        $out = [0 => get_string('thesis_supervisor_none', 'local_kaznu')];
        foreach ($users as $u) {
            $out[(int) $u->id] = fullname($u) . ' (' . $u->username . ')';
        }
        foreach (explode(',', (string) ($CFG->siteadmins ?? '')) as $aid) {
            $aid = (int) $aid;
            if ($aid > 0 && !isset($out[$aid])) {
                $u = $DB->get_record('user', ['id' => $aid, 'deleted' => 0], 'id,firstname,lastname,username');
                if ($u) {
                    $out[$aid] = fullname($u) . ' (' . $u->username . ')';
                }
            }
        }
        return $out;
    }

    public static function status_label(string $status): string {
        $key = 'thesis_status_' . $status;
        return get_string_manager()->string_exists($key, 'local_kaznu')
            ? get_string($key, 'local_kaznu')
            : $status;
    }

    public static function step_state(array $report, string $status): array {
        $ai = ($report['ai']['status'] ?? '') === 'done';
        $pl = ($report['plagiarism']['status'] ?? '') === 'done';
        $eds = ($report['eds']['signed'] ?? false) || $status === self::STATUS_APPROVED;
        return [
            'ai' => $ai ? 'done' : (($report['ai']['status'] ?? 'pending') === 'pending' ? 'todo' : ($report['ai']['status'] ?? 'todo')),
            'plagiarism' => $pl ? 'done' : 'todo',
            'eds' => $eds ? 'done' : ($status === self::STATUS_SUBMITTED ? 'active' : 'todo'),
        ];
    }

    /**
     * @return array{ext:string,size:int,text:string,fonts:array,margins:array,pages_est:int,word_count:int,body_font_pt:?float,line_spacing:?float}
     */
    private static function parse_document(string $path, string $ext, int $size): array {
        $out = [
            'ext' => $ext,
            'size' => $size,
            'text' => '',
            'fonts' => [],
            'margins' => [],
            'pages_est' => 0,
            'word_count' => 0,
            'body_font_pt' => null,
            'line_spacing' => null,
        ];
        if ($ext === 'docx' && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $doc = (string) $zip->getFromName('word/document.xml');
                $styles = (string) $zip->getFromName('word/styles.xml');
                $app = (string) $zip->getFromName('docProps/app.xml');
                $zip->close();
                $xmlall = $doc . "\n" . $styles;
                if ($app !== '' && preg_match('/<Pages>(\d+)<\/Pages>/', $app, $pm)) {
                    $out['pages_est'] = (int) $pm[1];
                }
                if (preg_match_all('/w:sz w:val="(\d+)"/u', $xmlall, $szm)) {
                    $sizes = array_map('intval', $szm[1]);
                    $sizes = array_filter($sizes, fn($v) => $v >= 20 && $v <= 32);
                    if ($sizes) {
                        $freq = array_count_values($sizes);
                        arsort($freq);
                        $dominant = (int) array_key_first($freq);
                        $out['body_font_pt'] = round($dominant / 2, 1);
                    }
                }
                if (preg_match('/w:spacing[^>]*w:line="(\d+)"/u', $xmlall, $lsm)) {
                    $out['line_spacing'] = round(((int) $lsm[1]) / 240, 2);
                } else if (preg_match('/w:spacing[^>]*w:lineRule="exact"[^>]*w:line="(\d+)"/u', $xmlall, $lsm2)) {
                    $out['line_spacing'] = round(((int) $lsm2[1]) / 240, 2);
                }
                if (preg_match_all('/w:ascii="([^"]+)"/u', $xmlall, $m)) {
                    $out['fonts'] = array_values(array_unique($m[1]));
                }
                if (preg_match('/w:pgMar[^>]*w:top="(\d+)"[^>]*w:right="(\d+)"[^>]*w:bottom="(\d+)"[^>]*w:left="(\d+)"/u', $doc, $mm)
                    || preg_match('/w:pgMar[^>]*w:left="(\d+)"[^>]*w:right="(\d+)"[^>]*w:top="(\d+)"[^>]*w:bottom="(\d+)"/u', $doc, $mm2)) {
                    // twips -> mm (~56.7 twips per mm)
                    if (!empty($mm)) {
                        $out['margins'] = [
                            'top' => round(((int) $mm[1]) / 56.7, 1),
                            'right' => round(((int) $mm[2]) / 56.7, 1),
                            'bottom' => round(((int) $mm[3]) / 56.7, 1),
                            'left' => round(((int) $mm[4]) / 56.7, 1),
                        ];
                    }
                }
                $text = preg_replace('/<w:tab\/>/u', "\t", $doc);
                $text = preg_replace('/<\/w:p>/u', "\n", (string) $text);
                $text = strip_tags((string) $text);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $text = preg_replace("/[ \t]+/u", ' ', (string) $text);
                $out['text'] = trim((string) $text);
            }
        } else if ($ext === 'pdf') {
            $raw = @file_get_contents($path);
            if (is_string($raw) && preg_match_all('/\((?:\\\\.|[^\\\\)]){4,}\)/u', $raw, $m)) {
                $chunks = [];
                foreach (array_slice($m[0], 0, 200) as $chunk) {
                    $chunks[] = trim($chunk, '()');
                }
                $out['text'] = trim(implode(' ', $chunks));
            }
        }
        $out['word_count'] = str_word_count($out['text'], 0, 'АаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЫыЬьЭэЮюЯяӘәҒғҚқҢңӨөҰұҮүҺһІі');
        if ($out['word_count'] < 1 && $out['text'] !== '') {
            $out['word_count'] = max(1, (int) preg_match_all('/\S+/u', $out['text']));
        }
        $out['pages_est'] = max(1, (int) ceil($out['word_count'] / 300));
        return $out;
    }

    private static function build_ai_section(array $parsed, string $title, string $worktype = 'master', array $rules = []): array {
        $checks = [];
        $checks[] = [
            'id' => 'file.type',
            'label' => get_string('thesis_check_filetype', 'local_kaznu'),
            'ok' => in_array($parsed['ext'], ['docx', 'pdf'], true),
            'expected' => 'DOCX (рекомендуется) / PDF',
            'found' => strtoupper($parsed['ext']),
            'group' => 'format',
        ];
        $checks[] = [
            'id' => 'content.title',
            'label' => get_string('thesis_check_title', 'local_kaznu'),
            'ok' => \core_text::strlen(trim($title)) >= 8,
            'expected' => 'тема заполнена',
            'found' => \core_text::substr($title, 0, 80),
            'group' => 'meta',
        ];

        if ($worktype === 'dba') {
            $checks = array_merge($checks, thesis_dba_analyzer::formatting_checks($parsed, $rules ?: thesis_dba_analyzer::rules()));
            if ($parsed['text'] !== '') {
                $checks = array_merge($checks, thesis_dba_analyzer::structure_checks($parsed['text']));
            }
        } else {
            $allowedfonts = ['times new roman', 'timesnewroman', 'times'];
            $fonts = array_map('mb_strtolower', $parsed['fonts']);
            $fontok = $parsed['ext'] !== 'docx' ? false : (empty($fonts) || (bool) array_intersect($fonts, $allowedfonts));
            $checks[] = [
                'id' => 'body.font',
                'label' => get_string('thesis_check_font', 'local_kaznu'),
                'ok' => $fontok,
                'expected' => 'Times New Roman',
                'found' => $fonts ? implode(', ', array_slice($parsed['fonts'], 0, 5))
                    : ($parsed['ext'] === 'docx' ? '—' : get_string('thesis_check_pdf_limited', 'local_kaznu')),
                'group' => 'format',
            ];
            $marginok = false;
            $foundmargin = '—';
            if (!empty($parsed['margins']['left'])) {
                $foundmargin = sprintf(
                    'L%s / R%s / T%s / B%s mm',
                    $parsed['margins']['left'],
                    $parsed['margins']['right'],
                    $parsed['margins']['top'],
                    $parsed['margins']['bottom']
                );
                $marginok = ((float) $parsed['margins']['left'] >= 28) && ((float) $parsed['margins']['right'] >= 13);
            }
            $checks[] = [
                'id' => 'page.margins',
                'label' => get_string('thesis_check_margins', 'local_kaznu'),
                'ok' => $marginok || $parsed['ext'] !== 'docx',
                'expected' => 'L30 R15 T20 B20 mm',
                'found' => $foundmargin,
                'group' => 'format',
            ];
            $checks[] = [
                'id' => 'content.volume',
                'label' => get_string('thesis_check_volume', 'local_kaznu'),
                'ok' => $parsed['word_count'] >= 100,
                'expected' => '≥ 100 слов (демо)',
                'found' => $parsed['word_count'] . ' слов ≈ ' . $parsed['pages_est'] . ' стр.',
                'group' => 'format',
            ];
        }

        $checks = thesis_dba_analyzer::apply_check_labels($checks);

        $okn = 0;
        foreach ($checks as $c) {
            if (!empty($c['ok'])) {
                $okn++;
            }
        }
        $score = (int) round(100 * $okn / max(1, count($checks)));
        $hints = [];
        foreach ($checks as $c) {
            if (empty($c['ok'])) {
                $hints[] = 'Исправьте: ' . ($c['label'] ?? '');
            }
        }

        $summary = get_string('thesis_ai_summary_local', 'local_kaznu', $score);
        $engine = 'local-docx';
        $checklist = [];
        $recommendations = [];
        $formattingnotes = [];
        $apikey = trim((string) get_config('local_kaznu', 'openai_api_key'));
        if ($apikey === '') {
            $apikey = trim((string) get_config('local_kaznu', 'openai_apikey'));
        }
        if ($apikey !== '' && $worktype === 'dba' && $parsed['text'] !== '') {
            $llm = thesis_dba_analyzer::llm_appendix_review($apikey, $title, $parsed['text'], $checks);
            if ($llm) {
                $summary = $llm['summary'] !== '' ? $llm['summary'] : $summary;
                $checklist = thesis_dba_analyzer::enrich_checklist($llm['checklist']);
                $recommendations = $llm['recommendations'];
                $formattingnotes = $llm['formatting_notes'];
                $engine = 'openai-dba-v1+' . $engine;
            }
        } else if ($apikey !== '') {
            $aihint = self::ask_openai_hints($apikey, $title, $checks, $parsed);
            if ($aihint !== '') {
                $summary = $aihint;
                $engine = 'openai+' . $engine;
            }
        }

        $passthreshold = $worktype === 'dba' ? 70 : 60;

        return [
            'status' => 'done',
            'score' => $score,
            'passed' => $score >= $passthreshold,
            'summary' => $summary,
            'checks' => $checks,
            'hints' => $hints,
            'checklist' => $checklist,
            'recommendations' => $recommendations,
            'formatting_notes' => $formattingnotes,
            'methodology' => $worktype === 'dba' ? 'DBA Farabi IBS 2026' : 'Farabi basic',
            'document_package' => $worktype === 'dba' ? thesis_dba_analyzer::document_package() : [],
            'engine' => $engine,
            'timemodified' => time(),
        ];
    }

    private static function ask_openai_hints(string $apikey, string $title, array $checks, array $parsed): string {
        $failed = [];
        foreach ($checks as $c) {
            if (empty($c['ok'])) {
                $failed[] = $c['label'] . ': ожидалось ' . $c['expected'] . ', найдено ' . $c['found'];
            }
        }
        $sample = \core_text::substr($parsed['text'], 0, 2500);
        $prompt = "Тема работы: {$title}\nПроблемы оформления:\n" . implode("\n", $failed ?: ['нет критичных'])
            . "\n\nФрагмент текста (для контекста, не переписывай работу):\n{$sample}\n\n"
            . "Дай краткий анализ на русском (5-8 предложений): что исправить в оформлении. Не оценивай научность.";

        $model = trim((string) (get_config('local_kaznu', 'openai_model') ?: 'gpt-4o-mini'));
        $payload = json_encode([
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => 'Ты эксперт по нормоконтролю вузовских работ в Казахстане. Отвечай кратко по делу.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if (!function_exists('curl_init')) {
            require_once($GLOBALS['CFG']->libdir . '/filelib.php');
        }
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apikey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300 || !is_string($raw)) {
            return '';
        }
        $data = json_decode($raw, true);
        $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        return $text;
    }

    private static function build_plagiarism_section(array $parsed, string $contenthash): array {
        // Deterministic demo originality (not a legal Antiplagiat replacement).
        $seed = hexdec(substr($contenthash, 0, 8));
        $base = 86 + ($seed % 11); // 86..96 — демо чаще «зелёное»
        if ($parsed['word_count'] < 80) {
            $base = max(70, $base - 12);
        }
        $originality = (int) min(97, max(70, $base));
        $similarity = 100 - $originality;
        $threshold = 80;
        $sources = [];
        if ($similarity >= 8) {
            $sources[] = [
                'title' => 'Открытые научные материалы (демо-корпус)',
                'percent' => max(3, (int) round($similarity * 0.45)),
            ];
            $sources[] = [
                'title' => 'Учебные работы вуза (демо-корпус)',
                'percent' => max(2, (int) round($similarity * 0.35)),
            ];
            $sources[] = [
                'title' => 'Интернет-источники (демо)',
                'percent' => max(1, $similarity - (int) round($similarity * 0.8)),
            ];
        }

        return [
            'status' => 'done',
            'originality' => $originality,
            'similarity' => $similarity,
            'passed' => $originality >= $threshold,
            'threshold' => $threshold,
            'sources' => $sources,
            'engine' => 'farabi-demo-v1',
            'mode' => 'demo',
            'summary' => get_string('thesis_plag_summary_demo', 'local_kaznu', $originality),
            'timemodified' => time(),
        ];
    }
}
