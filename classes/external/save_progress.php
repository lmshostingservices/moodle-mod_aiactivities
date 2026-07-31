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

namespace mod_aiactivities\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * External function to save student progress for an AI Learning Activities attempt.
 *
 * This mirrors the 'saveprogress' action in ajax.php and is registered as a
 * Moodle web service so that the service registry does not throw a "missing
 * externallib.php" error on plugin install/upgrade.
 */
class save_progress extends external_api {

    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid'       => new external_value(PARAM_INT,  'The attempt record ID'),
            'currentactivity' => new external_value(PARAM_INT,  'Index of the current activity card'),
            'progressjson'    => new external_value(PARAM_RAW,  'JSON-encoded progress map (card index => true)'),
            'completedcount'  => new external_value(PARAM_INT,  'Number of completed activities'),
        ]);
    }

    /**
     * Save progress for the given attempt.
     *
     * @param int    $attemptid       The attempt record ID.
     * @param int    $currentactivity Index of the current activity card.
     * @param string $progressjson    JSON-encoded progress map.
     * @param int    $completedcount  Number of completed activities.
     * @return array ['ok' => bool]
     */
    public static function execute(int $attemptid, int $currentactivity, string $progressjson, int $completedcount): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid'       => $attemptid,
            'currentactivity' => $currentactivity,
            'progressjson'    => $progressjson,
            'completedcount'  => $completedcount,
        ]);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);

        $instance = $DB->get_record('aiactivities', ['id' => $attempt->aiactivitiesid], '*', MUST_EXIST);
        $cm       = get_coursemodule_from_instance('aiactivities', $instance->id, 0, false, MUST_EXIST);
        $course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        self::validate_context(\context_module::instance($cm->id));
        require_login($course, false, $cm);

        if ((int)$attempt->userid !== (int)$USER->id) {
            throw new \moodle_exception('invalidattempt', 'mod_aiactivities');
        }

        $attempt->currentactivity = $params['currentactivity'];
        $attempt->progressjson    = $params['progressjson'];
        $attempt->completedcount  = $params['completedcount'];
        $attempt->timemodified    = time();

        $DB->update_record('aiactivities_attempts', $attempt);

        return ['ok' => true];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
        ]);
    }
}
