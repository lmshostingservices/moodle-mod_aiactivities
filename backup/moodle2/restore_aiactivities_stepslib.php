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

defined('MOODLE_INTERNAL') || die();

class restore_aiactivities_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('aiactivities', '/activity/aiactivities');

        if ($userinfo) {
            $paths[] = new restore_path_element('aiactivities_attempt', '/activity/aiactivities/attempts/attempt');
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function process_aiactivities($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
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

        $newitemid = $DB->insert_record('aiactivities', $data);
        $this->apply_activity_instance($newitemid);
    }

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

    protected function after_execute() {
        $this->add_related_files('mod_aiactivities', 'intro', null);
    }
}
