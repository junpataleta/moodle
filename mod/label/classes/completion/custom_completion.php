<?php


namespace mod_label\completion;


use core_completion\activity_custom_completion;

class custom_completion extends activity_custom_completion {

    /**
     * @inheritDoc
     */
    public function get_state(string $rule): int {
        return COMPLETION_UNKNOWN;
    }

    /**
     * @inheritDoc
     */
    public static function get_defined_custom_rules(): array {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function get_custom_rule_descriptions(): array {
        return [];
    }

    public function manual_completion_always_shown(): bool {
        return true;
    }
}
