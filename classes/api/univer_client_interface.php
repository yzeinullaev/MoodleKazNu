<?php
namespace local_kaznu\api;

use local_kaznu\api\dto\assessment_item;
use local_kaznu\api\dto\attendance_item;
use local_kaznu\api\dto\exam_item;
use local_kaznu\api\dto\login_result;
use local_kaznu\api\dto\midterm_item;
use local_kaznu\api\dto\schedule_item;
use local_kaznu\api\dto\semester_info;
use local_kaznu\api\dto\student_profile;
use local_kaznu\api\dto\subject;

defined('MOODLE_INTERNAL') || die();

/**
 * Contract for Univer read API (reusable by future auth_univer).
 */
interface univer_client_interface {
    public function login(string $login, string $password): login_result;

    public function logout(): void;

    public function get_cookie_jar(): cookie_jar;

    public function with_cookie_jar(cookie_jar $jar): self;

    public function get_semester_info(): semester_info;

    public function get_profile(string $culture = 'ru-RU'): student_profile;

    public function get_profile_for_user(int $univeruserid, string $culture = 'ru-RU'): student_profile;

    /**
     * @return subject[]
     */
    public function get_group_list(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array;

    /**
     * @return schedule_item[]
     */
    public function get_schedule(int $year = 0, int $semester = 0, int $day = 1, string $culture = 'ru-RU'): array;

    /**
     * @return schedule_item[]
     */
    public function get_schedule_week(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array;

    /**
     * @return exam_item[]
     */
    public function get_exam_schedule(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array;

    /**
     * @return midterm_item[]
     */
    public function get_midterm_schedule(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array;

    /**
     * @return assessment_item[]
     */
    public function get_attestation(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array;

    /**
     * @return attendance_item[]
     */
    public function get_attendance(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array;
}
