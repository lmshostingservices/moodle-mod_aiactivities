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
