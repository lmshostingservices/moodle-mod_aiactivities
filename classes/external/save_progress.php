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
 * External function that saves student progress for an attempt.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Saves the progress a student has made through an attempt.
 */
class save_progress extends external_api {
    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters The parameter description.
     */
    public static function execute_parameters(): external_function_parameters {
        $parameters = [
            'attemptid' => new external_value(PARAM_INT, 'The attempt record id'),
            'currentactivity' => new external_value(PARAM_INT, 'Index of the current activity card'),
            'progressjson' => new external_value(
                // phpcs:ignore moodle.Commenting.InlineComment.NotCapital -- Verbatim pipeline annotation.
                PARAM_RAW, // pipeline-ignore: PARAM_RAW — JSON, validated in execute().
                'JSON encoded progress map (card index => true)'
            ),
            'completedcount' => new external_value(PARAM_INT, 'Number of completed activities'),
        ];
        return new external_function_parameters($parameters);
    }

    /**
     * Saves progress for the given attempt.
     *
     * @param int $attemptid The attempt record id.
     * @param int $currentactivity Index of the current activity card.
     * @param string $progressjson JSON encoded progress map.
     * @param int $completedcount Number of completed activities.
     * @return array Result with an ok flag.
     * @throws \moodle_exception If the attempt does not belong to the current user or the progress is malformed.
     */
    public static function execute(
        int $attemptid,
        int $currentactivity,
        string $progressjson,
        int $completedcount
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/aiactivities/lib.php');

        $rawparams = [
            'attemptid' => $attemptid,
            'currentactivity' => $currentactivity,
            'progressjson' => $progressjson,
            'completedcount' => $completedcount,
        ];
        $params = self::validate_parameters(self::execute_parameters(), $rawparams);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $attempt->aiactivitiesid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('aiactivities', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/aiactivities:view', $context);

        if ((int)$attempt->userid !== (int)$USER->id) {
            throw new \moodle_exception('invalidattempt', 'mod_aiactivities');
        }

        $progress = json_decode($params['progressjson'], true);
        if (!is_array($progress)) {
            throw new \moodle_exception('error_invalid_progress', 'mod_aiactivities');
        }

        $attempt->currentactivity = aiactivities_clamp_index($params['currentactivity'], $attempt->totalcount);
        $attempt->progressjson = json_encode($progress);
        $attempt->completedcount = aiactivities_clamp_index($params['completedcount'], $attempt->totalcount);
        $attempt->timemodified = time();

        $DB->update_record('aiactivities_attempts', $attempt);

        return ['ok' => true];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure The return description.
     */
    public static function execute_returns(): external_single_structure {
        $returnfields = [
            'ok' => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
        ];
        return new external_single_structure($returnfields);
    }
}
