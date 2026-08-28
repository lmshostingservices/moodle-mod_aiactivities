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
 * Backup steps for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete structure for backing up one aiactivities instance.
 */
class backup_aiactivities_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure.
     *
     * @return backup_nested_element The prepared activity structure.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $instancefields = [
            'name', 'intro', 'introformat', 'grade',
            'content', 'language', 'activitycount', 'activitiesjson',
            'completionallcorrect', 'voiceoverenabled', 'voicegender', 'voiceid',
            'timecreated', 'timemodified',
        ];
        $aiactivities = new backup_nested_element('aiactivities', ['id'], $instancefields);

        $attempts = new backup_nested_element('attempts');
        $attemptfields = [
            'userid', 'progressjson', 'currentactivity',
            'completedcount', 'totalcount', 'score', 'status',
            'timecreated', 'timemodified',
        ];
        $attempt = new backup_nested_element('attempt', ['id'], $attemptfields);

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
