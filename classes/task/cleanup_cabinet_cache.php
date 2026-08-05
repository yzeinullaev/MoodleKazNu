<?php
namespace local_kaznu\task;

use local_kaznu\repository\assessment_repository;
use local_kaznu\repository\attendance_repository;
use local_kaznu\repository\cache_state_repository;
use local_kaznu\repository\schedule_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Retention purge for Phase 5 technical cache. No Univer HTTP.
 *
 * Never deletes:
 * - rows younger than retention;
 * - rows for a user's current academic period (from local_kaznu_univer_user).
 */
class cleanup_cabinet_cache extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_cleanup_cabinet_cache', 'local_kaznu');
    }

    public function execute(): void {
        $days = (int) (get_config('local_kaznu', 'univer_cache_retention_days') ?: 180);
        $days = max(30, $days);
        $before = time() - ($days * DAYSECS);
        $s = (new schedule_repository())->purge_older_than($before);
        $a = (new assessment_repository())->purge_older_than($before);
        $t = (new attendance_repository())->purge_older_than($before);
        $c = (new cache_state_repository())->purge_older_than($before);
        mtrace("cleanup_cabinet_cache schedule=$s assessment=$a attendance=$t state=$c before=$before days=$days");
    }
}
