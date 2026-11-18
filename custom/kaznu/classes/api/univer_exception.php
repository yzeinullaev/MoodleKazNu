<?php
namespace local_kaznu\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Univer API / transport errors. Never put passwords or cookies in the message.
 */
class univer_exception extends \moodle_exception {
    /** @var int|null UniResponse code when available */
    public $uniresponsecode;

    public function __construct(
        string $errorcode,
        string $debuginfo = '',
        ?int $uniresponsecode = null,
        mixed $a = null
    ) {
        $this->uniresponsecode = $uniresponsecode;
        parent::__construct($errorcode, 'local_kaznu', '', $a, $debuginfo);
    }
}
