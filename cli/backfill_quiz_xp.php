<?php
/**
 * Backfill quiz XP for finished attempts that already passed (>= 60%).
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$attempts = $DB->get_records_sql("
    SELECT a.id, a.userid, a.quiz, q.course, gg.finalgrade, gi.grademax
      FROM {quiz_attempts} a
      JOIN {quiz} q ON q.id = a.quiz
      JOIN {grade_items} gi ON gi.itemmodule = 'quiz' AND gi.iteminstance = a.quiz AND gi.courseid = q.course
      JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = a.userid
     WHERE a.state = 'finished' AND gg.finalgrade IS NOT NULL
");

$n = 0;
foreach ($attempts as $a) {
    $ok = local_kaznu_award_quiz_xp(
        (int) $a->userid,
        (int) $a->quiz,
        (float) $a->finalgrade,
        (float) ($a->grademax ?: 100)
    );
    if ($ok) {
        $n++;
        mtrace("awarded userid={$a->userid} quiz={$a->quiz} grade={$a->finalgrade}");
    }
}
mtrace("done, new awards: {$n}");
