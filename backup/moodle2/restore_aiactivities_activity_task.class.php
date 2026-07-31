<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/aiactivities/backup/moodle2/restore_aiactivities_stepslib.php');

class restore_aiactivities_activity_task extends restore_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new restore_aiactivities_activity_structure_step('aiactivities_structure', 'aiactivities.xml'));
    }

    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('aiactivities', ['intro'], 'aiactivities');
        return $contents;
    }

    public static function define_decode_rules() {
        $rules = [];
        $rules[] = new restore_decode_rule('AIACTIVITIESVIEWBYID', '/mod/aiactivities/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('AIACTIVITIESINDEX', '/mod/aiactivities/index.php?id=$1', 'course');
        return $rules;
    }

    public static function define_restore_log_rules() {
        $rules = [];
        $rules[] = new restore_log_rule('aiactivities', 'add', 'view.php?id={course_module}', '{aiactivities}');
        $rules[] = new restore_log_rule('aiactivities', 'update', 'view.php?id={course_module}', '{aiactivities}');
        $rules[] = new restore_log_rule('aiactivities', 'view', 'view.php?id={course_module}', '{aiactivities}');
        return $rules;
    }

    public static function define_restore_log_rules_for_course() {
        $rules = [];
        $rules[] = new restore_log_rule('aiactivities', 'view all', 'index.php?id={course}', null);
        return $rules;
    }
}
