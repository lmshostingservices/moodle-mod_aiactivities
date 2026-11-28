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
 * Event fired when a set of activities is generated with AI.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities\event;

/**
 * Fired when a set of activities is generated with AI (the Responsible AI record of generation).
 *
 * @property-read array $other {
 *      - int activitycount: activities in the new set.
 *      - string engine: lmslabs (LMS Labs service) or ownai (the site's own AI).
 *      - string language: generation language code.
 *      - int narrated: 1 when narration was generated.
 *      - int requiresreview: 1 when the set waits for a teacher before learners see it.
 * }
 */
class activities_generated extends \core\event\base {
    /**
     * Initialises the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'aiactivities';
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_activities_generated', 'mod_aiactivities');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $count = (int)($this->other['activitycount'] ?? 0);
        $engine = s($this->other['engine'] ?? '');
        return "The user with id '$this->userid' generated $count activities with AI (engine: $engine) in the " .
            "AI Learning Activities instance with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns the URL of the instance.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/aiactivities/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validates the event data.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['activitycount'])) {
            throw new \coding_exception('The \'activitycount\' value must be set in other.');
        }
    }

    /**
     * Returns the mapping used when restoring the event.
     *
     * @return array The object id mapping.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'aiactivities', 'restore' => 'aiactivities'];
    }

    /**
     * Values in other need no mapping on restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
