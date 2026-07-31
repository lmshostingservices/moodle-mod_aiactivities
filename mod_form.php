<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_aiactivities_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'mod_aiactivities'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $mform->addElement('checkbox', 'completionallcorrect' . $suffix,
            get_string('completionallcorrect', 'mod_aiactivities'));
        $mform->setDefault('completionallcorrect' . $suffix, 1);
        $mform->addHelpButton('completionallcorrect' . $suffix, 'completionallcorrect', 'mod_aiactivities');

        return ['completionallcorrect' . $suffix];
    }

    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionallcorrect' . $suffix]);
    }

    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $suffix = $this->get_suffix();
        $data->completionallcorrect = !empty($data->{"completionallcorrect$suffix"}) ? 1 : 0;
    }

    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        $suffix = $this->get_suffix();
        if (isset($defaultvalues['completionallcorrect'])) {
            $defaultvalues["completionallcorrect$suffix"] = $defaultvalues['completionallcorrect'];
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
