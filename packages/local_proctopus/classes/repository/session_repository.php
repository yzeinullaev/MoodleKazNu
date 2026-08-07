<?php
namespace local_proctopus\repository;

defined('MOODLE_INTERNAL') || die();

class session_repository implements session_repository_interface {
    public function create(array $fields): int {
        global $DB;
        $now = time();
        $rec = (object) array_merge([
            'attemptid' => null,
            'status' => 'created',
            'proctoring_mode' => '',
            'started_at' => 0,
            'expires_at' => 0,
            'completed_at' => 0,
            'last_callback_at' => 0,
            'result_code' => '',
            'result_summary' => '',
            'correlation_id' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ], $fields);
        // Never store JWT.
        unset($rec->jwt, $rec->token, $rec->secret);
        return (int) $DB->insert_record('local_proctopus_session', $rec);
    }

    public function get_by_id(int $id): ?\stdClass {
        global $DB;
        $r = $DB->get_record('local_proctopus_session', ['id' => $id]);
        return $r ?: null;
    }

    public function get_by_external_session_id(string $external): ?\stdClass {
        global $DB;
        $r = $DB->get_record('local_proctopus_session', ['external_session_id' => $external]);
        return $r ?: null;
    }

    public function get_active_for_user_cm(int $userid, int $cmid): ?\stdClass {
        global $DB;
        $sql = "SELECT * FROM {local_proctopus_session}
                 WHERE userid = ? AND cmid = ? AND status IN ('created','started','redirected','admitted','active')
              ORDER BY id DESC";
        $r = $DB->get_record_sql($sql, [$userid, $cmid], IGNORE_MULTIPLE);
        return $r ?: null;
    }

    public function update_status(int $id, string $status, array $extra = []): void {
        global $DB;
        $rec = (object) array_merge(['id' => $id, 'status' => $status, 'timemodified' => time()], $extra);
        unset($rec->jwt, $rec->token, $rec->secret);
        $DB->update_record('local_proctopus_session', $rec);
    }
}
