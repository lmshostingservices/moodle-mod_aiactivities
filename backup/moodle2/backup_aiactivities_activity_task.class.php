<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/aiactivities/backup/moodle2/backup_aiactivities_stepslib.php');

class backup_aiactivities_activity_task extends backup_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new backup_aiactivities_activity_structure_step('aiactivities_structure', 'aiactivities.xml'));
    }

    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/aiactivities\/index.php\?id=)([0-9]+)/';
        $content = preg_replace($search, '$@AIACTIVITIESINDEX*$2@$', $content);

        $search = '/(' . $base . '\/mod\/aiactivities\/view.php\?id=)([0-9]+)/';
        $content = preg_replace($search, '$@AIACTIVITIESVIEWBYID*$2@$', $content);

        return $content;
    }
}
