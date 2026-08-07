<?php
namespace local_proctopus\callback;

defined('MOODLE_INTERNAL') || die();

interface callback_verifier_interface {
    /**
     * @return array{ok:bool, error?:string, claims?:array}
     */
    public function verify(string $rawbody, string $contenttype, string $correlation): array;
}
