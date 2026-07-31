<?php
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
