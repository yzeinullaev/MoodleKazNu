<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Sanitised student profile (no IIN).
 *
 * Univer may return null for optional string fields (e.g. nameEn). Constructor
 * always coerces string properties so callers never TypeError on null.
 */
class student_profile {
    public readonly int $univeruserid;
    public readonly string $login;
    public readonly string $firstname;
    public readonly string $lastname;
    public readonly string $firstnamephonetic;
    public readonly ?string $email;
    public readonly bool $emailistechnical;
    public readonly string $faculty;
    public readonly string $speciality;
    public readonly int $coursenumber;
    public readonly string $groupname;
    public readonly string $firstnameen;
    public readonly string $lastnameen;

    public function __construct(
        int $univeruserid,
        ?string $login = '',
        ?string $firstname = '',
        ?string $lastname = '',
        ?string $firstnamephonetic = '',
        ?string $email = null,
        bool $emailistechnical = true,
        ?string $faculty = '',
        ?string $speciality = '',
        int $coursenumber = 0,
        ?string $groupname = '',
        ?string $firstnameen = '',
        ?string $lastnameen = ''
    ) {
        $this->univeruserid = $univeruserid;
        $this->login = (string) ($login ?? '');
        $this->firstname = (string) ($firstname ?? '');
        $this->lastname = (string) ($lastname ?? '');
        $this->firstnamephonetic = (string) ($firstnamephonetic ?? '');
        $this->email = $email;
        $this->emailistechnical = $emailistechnical;
        $this->faculty = (string) ($faculty ?? '');
        $this->speciality = (string) ($speciality ?? '');
        $this->coursenumber = $coursenumber;
        $this->groupname = (string) ($groupname ?? '');
        $this->firstnameen = (string) ($firstnameen ?? '');
        $this->lastnameen = (string) ($lastnameen ?? '');
    }
}
