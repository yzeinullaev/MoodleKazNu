<?php
namespace local_kaznu\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Outcome of a Univer student login attempt (never contains password/cookies).
 */
final class login_result {
    public const OK = 'ok';
    public const DISABLED = 'disabled';
    public const RATE_LIMITED = 'rate_limited';
    public const BAD_CREDENTIALS = 'bad_credentials';
    public const UNAVAILABLE = 'unavailable';
    public const NOT_STUDENT = 'not_student';
    public const PROFILE_FAILED = 'profile_failed';
    public const ACCOUNT_REVIEW = 'account_review';
    public const SYNC_FAILED = 'sync_failed';
    /** Faculty filter deny (missing or wrong faculty). Not BAD_CREDENTIALS. */
    public const FACULTY_DENIED = 'faculty_denied';

    public function __construct(
        public readonly string $status,
        public readonly ?\stdClass $user = null,
        public readonly bool $created = false,
        public readonly string $correlationid = '',
        public readonly string $errorcode = '',
        /** Internal audit outcome (success_business_school, denied_faculty, …). */
        public readonly string $outcome = ''
    ) {
    }

    public function is_success(): bool {
        return $this->status === self::OK && $this->user !== null;
    }

    /**
     * Neutral lang string identifier for the UI (local_kaznu component unless auth remaps).
     */
    public function user_message_identifier(): string {
        return match ($this->status) {
            self::DISABLED => 'univer_login_disabled',
            self::RATE_LIMITED => 'univer_login_rate_limited',
            self::BAD_CREDENTIALS => 'univer_bad_credentials',
            self::UNAVAILABLE => 'univer_temporarily_unavailable',
            self::NOT_STUDENT => 'univer_not_student',
            self::PROFILE_FAILED => 'univer_profile_failed',
            self::ACCOUNT_REVIEW => 'univer_account_review',
            self::SYNC_FAILED => 'univer_sync_failed',
            self::FACULTY_DENIED => 'univer_faculty_access_denied',
            default => 'univer_auth_failed',
        };
    }
}
