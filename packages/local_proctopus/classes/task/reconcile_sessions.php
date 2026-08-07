<?php
// This file is part of Moodle - http://moodle.org/

namespace local_proctopus\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Close stale local proctopus sessions against Moodle attempts. No external API.
 */
class reconcile_sessions extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_reconcile_sessions', 'local_proctopus');
    }

    public function execute(): void {
        $plan = \local_proctopus\service\proctoring_session_reconciler::reconcile(null, null, null, true);
        $changed = 0;
        foreach ($plan as $row) {
            if (!empty($row['applied'])) {
                $changed++;
            }
        }
        mtrace('local_proctopus reconcile_sessions: reviewed ' . count($plan) . ', updated ' . $changed);
    }
}
