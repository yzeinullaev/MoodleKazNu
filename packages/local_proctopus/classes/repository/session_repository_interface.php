<?php
namespace local_proctopus\repository;

defined('MOODLE_INTERNAL') || die();

interface session_repository_interface {
    public function create(array $fields): int;
    public function get_by_id(int $id): ?\stdClass;
    public function get_by_external_session_id(string $external): ?\stdClass;
    public function get_active_for_user_cm(int $userid, int $cmid): ?\stdClass;
    public function update_status(int $id, string $status, array $extra = []): void;
}
