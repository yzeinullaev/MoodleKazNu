<?php
namespace local_proctopus\callback;

defined('MOODLE_INTERNAL') || die();

/**
 * Default until provider documents signature scheme.
 */
class disabled_verifier implements callback_verifier_interface {
    public function verify(string $rawbody, string $contenttype, string $correlation): array {
        return ['ok' => false, 'error' => 'not_configured'];
    }
}
