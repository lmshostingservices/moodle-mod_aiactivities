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
 * mod_aiactivities file.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

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
