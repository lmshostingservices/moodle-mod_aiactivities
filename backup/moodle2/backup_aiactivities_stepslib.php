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

class backup_aiactivities_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $aiactivities = new backup_nested_element('aiactivities', ['id'], [
            'name', 'intro', 'introformat', 'grade',
            'content', 'language', 'activitycount', 'activitiesjson',
            'completionallcorrect', 'timecreated', 'timemodified',
        ]);

        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'progressjson', 'currentactivity',
            'completedcount', 'totalcount', 'score', 'status',
            'timecreated', 'timemodified',
        ]);

        $aiactivities->add_child($attempts);
        $attempts->add_child($attempt);

        $aiactivities->set_source_table('aiactivities', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $attempt->set_source_table('aiactivities_attempts', ['aiactivitiesid' => backup::VAR_PARENTID], 'id ASC');
        }

        $attempt->annotate_ids('user', 'userid');

        return $this->prepare_activity_structure($aiactivities);
    }
}
