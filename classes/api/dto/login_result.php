<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Result of GET /user/login.
 */
class login_result {
    public function __construct(
        public readonly int $univeruserid,
        public readonly string $login,
        public readonly string $usertype,
        public readonly int $access,
        public readonly ?string $sessionid,
        public readonly string $message
    ) {
    }

    public function is_student(): bool {
        return strtoupper($this->usertype) === 'STUDENT';
    }

    /**
     * Technical unique email when Univer does not provide one.
     */
    public function technical_email(): string {
        return 'univer_' . $this->univeruserid . '@users.invalid';
    }
}
