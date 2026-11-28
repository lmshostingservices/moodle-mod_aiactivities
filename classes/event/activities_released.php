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
 * Event fired when a teacher releases AI-generated activities to learners.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities\event;

/**
 * Fired when a teacher checks an AI-generated set and releases it to learners.
 *
 * @property-read array $other {
 *      - int activitycount: activities in the released set.
 * }
 */
class activities_released extends \core\event\base {
    /**
     * Initialises the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'aiactivities';
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_activities_released', 'mod_aiactivities');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $count = (int)($this->other['activitycount'] ?? 0);
        return "The user with id '$this->userid' checked and released $count AI-generated activities to learners in the " .
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
