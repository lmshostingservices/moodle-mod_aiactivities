<?php
namespace mod_aiactivities\completion;

use core_completion\activity_custom_completion;

defined('MOODLE_INTERNAL') || die();

class custom_completion extends activity_custom_completion {

    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $instance = $DB->get_record('aiactivities', ['id' => $this->cm->instance], '*', MUST_EXIST);

        if ($rule === 'completionallcorrect') {
            $exists = $DB->record_exists('aiactivities_attempts', [
                'aiactivitiesid' => $instance->id,
                'userid' => $this->userid,
                'status' => 1,
            ]);

            return $exists ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    public static function get_defined_custom_rules(): array {
        return ['completionallcorrect'];
    }

    public function get_custom_rule_descriptions(): array {
        return [
            'completionallcorrect' => get_string('completiondetail:completionallcorrect', 'mod_aiactivities'),
        ];
    }

    public function get_sort_order(): array {
        return ['completionallcorrect'];
    }
}
