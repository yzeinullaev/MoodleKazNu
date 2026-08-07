<?php
/**
 * @package local_kaznu
 * @covers ::local_kaznu_is_quiz_focus_page
 * @covers ::local_kaznu_render_achievement_panel
 */
class local_kaznu_achievements_ui_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
    }

    public function test_quiz_focus_pages(): void {
        global $PAGE;
        $cases = [
            'mod-quiz-attempt' => true,
            'mod-quiz-attempt-1' => true,
            'mod-quiz-summary' => true,
            'mod-quiz-processattempt' => true,
            'mod-quiz-review' => false,
            'mod-quiz-view' => false,
            'course-view-topics' => false,
            'my-index' => false,
        ];
        foreach ($cases as $pagetype => $expect) {
            $PAGE->set_pagetype($pagetype);
            $this->assertSame($expect, local_kaznu_is_quiz_focus_page(), $pagetype);
        }
    }

    public function test_panel_has_a11y_button_and_collapsed_default(): void {
        $html = local_kaznu_render_achievement_panel([
            'id' => 'kzn-test-ach',
            'title' => 'Achievements',
            'level' => 2,
            'xp' => 40,
            'bodyhtml' => '<p class="body-x">reward</p>',
            'variant' => 'hud',
        ]);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="kzn-test-ach-body"', $html);
        $this->assertStringContainsString('data-kzn-achievements="1"', $html);
        $this->assertStringContainsString('is-collapsed', $html);
        $this->assertStringContainsString('farabi_achievements_open', $html);
        $this->assertStringContainsString('kzn-achievement-icon', $html);
        $this->assertStringContainsString('kzn-achievement-close', $html);
        $this->assertStringContainsString('body-x', $html);
        // Collapsed control must not put Lv/XP in the toggle button surface.
        $this->assertDoesNotMatchRegularExpression(
            '/kzn-achievement-toggle[^>]*>[^<]*Lv/s',
            $html
        );
        $this->assertStringNotContainsString('insert_record', $html);
    }
}
