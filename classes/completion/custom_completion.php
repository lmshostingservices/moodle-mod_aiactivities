<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Custom completion rules for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities\completion;

use core_completion\activity_custom_completion;

/**
 * Reports the state of this module's custom completion rules.
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns the completion state for a rule.
     *
     * @param string $rule The rule to check.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $instance = $DB->get_record('aiactivities', ['id' => $this->cm->instance], '*', MUST_EXIST);

        if ($rule === 'completionallcorrect') {
            $conditions = [
                'aiactivitiesid' => $instance->id,
                'userid' => $this->userid,
                'status' => 1,
            ];
            $exists = $DB->record_exists('aiactivities_attempts', $conditions);

            return $exists ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Returns the rules this module defines.
     *
     * @return string[] The rule names.
     */
    public static function get_defined_custom_rules(): array {
        return ['completionallcorrect'];
    }

    /**
     * Returns the human readable description of each rule.
     *
     * @return array Rule name => description.
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionallcorrect' => get_string('completiondetail:completionallcorrect', 'mod_aiactivities'),
        ];
    }

    /**
     * Returns the order the rules are shown in.
     *
     * @return string[] The rule names in display order.
     */
    public function get_sort_order(): array {
        return ['completionallcorrect'];
    }
}
