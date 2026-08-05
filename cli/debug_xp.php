<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

echo "=== XP balances ===\n";
$rows = $DB->get_records_sql("
    SELECT u.id, u.username, x.xp, x.level, x.badges
      FROM {user} u
      JOIN {local_kaznu_xp} x ON x.userid = u.id
  ORDER BY x.xp DESC, u.id ASC
");
foreach ($rows as $r) {
    echo "{$r->username}: xp={$r->xp} lv={$r->level} badges={$r->badges}\n";
}

echo "\n=== Recent finished attempts ===\n";
$attempts = $DB->get_records_sql("
    SELECT a.id, u.username, q.name, q.id AS quizid, c.shortname,
           a.state, a.sumgrades, a.timefinish, gg.finalgrade, gi.grademax, cm.completion
      FROM {quiz_attempts} a
      JOIN {user} u ON u.id = a.userid
      JOIN {quiz} q ON q.id = a.quiz
      JOIN {course} c ON c.id = q.course
 LEFT JOIN {grade_items} gi ON gi.itemmodule = 'quiz' AND gi.iteminstance = a.quiz AND gi.courseid = q.course
 LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = a.userid
 LEFT JOIN {modules} m ON m.name = 'quiz'
 LEFT JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = q.id AND cm.course = q.course
     WHERE a.timefinish > 0
  ORDER BY a.timefinish DESC
", [], 0, 20);

foreach ($attempts as $a) {
    $pct = ($a->grademax > 0 && $a->finalgrade !== null)
        ? round(100 * (float)$a->finalgrade / (float)$a->grademax, 1)
        : 'n/a';
    echo date('Y-m-d H:i', (int)$a->timefinish)
        . " | {$a->username} | {$a->shortname} | {$a->name}"
        . " | grade={$a->finalgrade}/{$a->grademax} (~{$pct}%)"
        . " | cm.completion={$a->completion}\n";
}
