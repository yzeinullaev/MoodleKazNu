<?php
namespace local_proctopus\callback;

defined('MOODLE_INTERNAL') || die();

interface replay_guard_interface {
    /** @return true if event is new; false if replay. */
    public function accept(string $externaleventid, string $payloadhash): bool;
}
