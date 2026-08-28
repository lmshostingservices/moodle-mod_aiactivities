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
 * Settings form for an AI Learning Activities instance.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Defines the module settings form.
 */
class mod_aiactivities_mod_form extends moodleform_mod {
    /**
     * Defines the form elements.
     *
     * @return void
     */
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

    /**
     * Adds the custom completion rules to the form.
     *
     * @return string[] The names of the added elements.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $mform->addElement(
            'checkbox',
            'completionallcorrect' . $suffix,
            get_string('completionallcorrect', 'mod_aiactivities')
        );
        $mform->setDefault('completionallcorrect' . $suffix, 1);
        $mform->addHelpButton('completionallcorrect' . $suffix, 'completionallcorrect', 'mod_aiactivities');

        return ['completionallcorrect' . $suffix];
    }

    /**
     * Reports whether a custom completion rule is enabled.
     *
     * @param array $data The submitted data.
     * @return bool True when the rule is enabled.
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionallcorrect' . $suffix]);
    }

    /**
     * Normalises the submitted completion data.
     *
     * @param stdClass $data The submitted data.
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $suffix = $this->get_suffix();
        $data->completionallcorrect = !empty($data->{"completionallcorrect$suffix"}) ? 1 : 0;
    }

    /**
     * Prepares the stored data for display in the form.
     *
     * @param array $defaultvalues The current values, modified in place.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        $suffix = $this->get_suffix();
        if (isset($defaultvalues['completionallcorrect'])) {
            $defaultvalues["completionallcorrect$suffix"] = $defaultvalues['completionallcorrect'];
        }
    }

    /**
     * Validates the submitted data.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
