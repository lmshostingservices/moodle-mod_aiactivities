<?php
namespace mod_aiactivities\event;

defined('MOODLE_INTERNAL') || die();

class course_module_viewed extends \core\event\course_module_viewed {
    protected function init() {
        $this->data['objecttable'] = 'aiactivities';
        parent::init();
    }

    public static function get_objectid_mapping() {
        return ['db' => 'aiactivities', 'restore' => 'aiactivities'];
    }
}
