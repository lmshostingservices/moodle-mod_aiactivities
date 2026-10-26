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
 * Restore steps for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the structure restored for one aiactivities instance.
 */
class restore_aiactivities_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the paths that are restored.
     *
     * @return restore_path_element[] The prepared activity structure.
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('aiactivities', '/activity/aiactivities');

        if ($userinfo) {
            $paths[] = new restore_path_element('aiactivities_attempt', '/activity/aiactivities/attempts/attempt');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores one aiactivities instance.
     *
     * @param array $data The instance data from the backup file.
     * @return void
     */
    protected function process_aiactivities($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if (!isset($data->activitycount)) {
            $data->activitycount = 5;
        }
        if (!isset($data->language)) {
            $data->language = 'en-AU';
        }
        if (!isset($data->completionallcorrect)) {
            $data->completionallcorrect = 1;
        }
        if (!isset($data->voiceoverenabled)) {
            $data->voiceoverenabled = 0;
        }
        if (!isset($data->voicegender)) {
            $data->voicegender = 'female';
        }
        if (!isset($data->voiceid)) {
            $data->voiceid = 'Zephyr';
        }
        if (!isset($data->activitytypes)) {
            // Backups taken before release 1.10.0 carry no type restriction.
            $data->activitytypes = \mod_aiactivities\local\activity_types::ALL;
        }

        $newitemid = $DB->insert_record('aiactivities', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores one attempt.
     *
     * @param array $data The attempt data from the backup file.
     * @return void
     */
    protected function process_aiactivities_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->aiactivitiesid = $this->get_new_parentid('aiactivities');
        $data->userid = $this->get_mappingid('user', $data->userid);

        if (!empty($data->timecreated)) {
            $data->timecreated = $this->apply_date_offset($data->timecreated);
        }
        if (!empty($data->timemodified)) {
            $data->timemodified = $this->apply_date_offset($data->timemodified);
        }

        $newitemid = $DB->insert_record('aiactivities_attempts', $data);
        $this->set_mapping('aiactivities_attempt', $oldid, $newitemid);
    }

    /**
     * Adds the files belonging to the restored instance.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_aiactivities', 'intro', null);
    }
}
