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
 * Library of interface functions and constants for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the features this activity module supports.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True if the module supports the feature, null if it does not know, or the feature value.
 */
function aiactivities_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        default:
            return null;
    }
}

/**
 * Adds a new aiactivities instance.
 *
 * @param stdClass $data Data submitted by the module form.
 * @param mod_aiactivities_mod_form|null $mform The form instance.
 * @return int The id of the new instance.
 */
function aiactivities_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();

    if (!isset($data->activitycount)) {
        $data->activitycount = 5;
    }
    if (!isset($data->language)) {
        $data->language = 'en-AU';
    }
    if (!isset($data->completionallcorrect)) {
        $data->completionallcorrect = 1;
    }

    $data->id = $DB->insert_record('aiactivities', $data);

    return $data->id;
}

/**
 * Updates an existing aiactivities instance.
 *
 * @param stdClass $data Data submitted by the module form.
 * @param mod_aiactivities_mod_form|null $mform The form instance.
 * @return bool True on success.
 */
function aiactivities_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    if (!isset($data->activitycount)) {
        $data->activitycount = 5;
    }
    if (!isset($data->completionallcorrect)) {
        $data->completionallcorrect = 1;
    }

    return $DB->update_record('aiactivities', $data);
}

/**
 * Deletes an aiactivities instance and all data belonging to it.
 *
 * @param int $id The instance id.
 * @return bool True on success.
 */
function aiactivities_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record('aiactivities', ['id' => $id]);
    if (!$instance) {
        return false;
    }

    // Activity content is stored as JSON in the activitiesjson column of the
    // aiactivities table itself, so only the attempts table needs clearing.
    $DB->delete_records('aiactivities_attempts', ['aiactivitiesid' => $id]);
    $DB->delete_records('aiactivities', ['id' => $id]);

    return true;
}

/**
 * Returns the Font Awesome icon map for this module.
 *
 * @return array Icon map.
 */
function mod_aiactivities_get_fontawesome_icon_map() {
    return [
        'mod_aiactivities:icon' => 'fa-puzzle-piece',
    ];
}

/**
 * Returns information about the course module for the course page.
 *
 * @param stdClass $coursemodule The course module record.
 * @return cached_cm_info|null Course module information, or null when the instance is missing.
 */
function aiactivities_get_coursemodule_info($coursemodule) {
    global $DB;

    if (
        !$instance = $DB->get_record(
            'aiactivities',
            ['id' => $coursemodule->instance],
            'id, name, intro, introformat, completionallcorrect'
        )
    ) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('aiactivities', $instance, $coursemodule->id, false);
    }

    if ($instance->completionallcorrect) {
        $info->customdata['customcompletionrules']['completionallcorrect'] = $instance->completionallcorrect;
    }

    return $info;
}

/**
 * Marks the activity as viewed and triggers the course module viewed event.
 *
 * @param stdClass $instance The aiactivities instance record.
 * @param stdClass $course The course record.
 * @param stdClass|cm_info $cm The course module.
 * @param context_module $context The module context.
 * @return void
 */
function aiactivities_view($instance, $course, $cm, $context) {
    $eventdata = [
        'objectid' => $instance->id,
        'context' => $context,
    ];
    $event = \mod_aiactivities\event\course_module_viewed::create($eventdata);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('aiactivities', $instance);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Serves files for the activity. This module stores no files of its own.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args Extra arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool False, as no file areas are served.
 */
function aiactivities_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    return false;
}

/**
 * Returns the descriptions of the active custom completion rules.
 *
 * @param cm_info|stdClass $cm The course module.
 * @return array Descriptions of the active rules.
 */
function mod_aiactivities_get_completion_active_rule_descriptions($cm) {
    global $DB;

    $descriptions = [];
    $instance = $DB->get_record('aiactivities', ['id' => $cm->instance]);

    if ($instance && !empty($instance->completionallcorrect)) {
        $descriptions[] = get_string('completiondetail:completionallcorrect', 'mod_aiactivities');
    }

    return $descriptions;
}

/**
 * Adds this module's elements to the course reset form.
 *
 * @param MoodleQuickForm $mform The course reset form.
 * @return void
 */
function aiactivities_reset_course_form_definition($mform) {
    $mform->addElement('header', 'aiactivitiesheader', get_string('modulenameplural', 'mod_aiactivities'));
    $mform->addElement(
        'advcheckbox',
        'reset_aiactivities_attempts',
        get_string('reset_attempts', 'mod_aiactivities')
    );
}

/**
 * Returns the default course reset settings for this module.
 *
 * @param stdClass $course The course record.
 * @return array Default settings.
 */
function aiactivities_reset_course_form_defaults($course) {
    return ['reset_aiactivities_attempts' => 1];
}

/**
 * Removes student attempts from every activity in a course when the course is reset.
 *
 * @param stdClass $data The reset form data.
 * @return array[] Status messages.
 */
function aiactivities_reset_userdata($data) {
    global $DB;

    $status = [];

    if (!empty($data->reset_aiactivities_attempts)) {
        $instanceids = $DB->get_fieldset_select(
            'aiactivities',
            'id',
            'course = :courseid',
            ['courseid' => $data->courseid]
        );

        if (!empty($instanceids)) {
            [$insql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('aiactivities_attempts', "aiactivitiesid $insql", $params);
        }

        $status[] = [
            'component' => get_string('modulenameplural', 'mod_aiactivities'),
            'item' => get_string('reset_attempts', 'mod_aiactivities'),
            'error' => false,
        ];
    }

    return $status;
}

/**
 * Returns the site identifier and API key used to reach the generation service.
 *
 * The optional local_aiconfig plugin, when installed, holds these centrally for
 * all AI Grader plugins and takes priority over the values stored by this plugin.
 *
 * @return array [string $siteid, string $apikey]. Either value is an empty string when not configured.
 */
function aiactivities_get_credentials(): array {
    global $CFG;

    $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
    if (file_exists($aiconfiglib)) {
        require_once($aiconfiglib);
    }

    $siteid = '';
    $apikey = '';

    if (function_exists('local_aiconfig_get_siteid')) {
        $siteid = trim((string)local_aiconfig_get_siteid());
    }
    if (function_exists('local_aiconfig_get_apikey')) {
        $apikey = trim((string)local_aiconfig_get_apikey());
    }
    if ($siteid === '') {
        $siteid = trim((string)get_config('mod_aiactivities', 'siteid'));
    }
    if ($apikey === '') {
        $apikey = trim((string)get_config('mod_aiactivities', 'apikey'));
    }

    return [$siteid, $apikey];
}

/**
 * Returns the base URL of the generation service, without a trailing slash.
 *
 * @return string The API base URL.
 */
function aiactivities_get_api_base(): string {
    $apibase = trim((string)get_config('mod_aiactivities', 'apiurl'));
    if ($apibase === '') {
        $apibase = 'https://lms-labs.com';
    }
    return rtrim($apibase, '/');
}

/**
 * Clamps a client supplied activity index to the size of an attempt.
 *
 * The total is zero on attempts created before any activity existed, and in
 * that case the value is only floored, not capped.
 *
 * @param int $value The value supplied by the browser.
 * @param int $total The number of activities in the attempt.
 * @return int The clamped value.
 */
function aiactivities_clamp_index(int $value, int $total): int {
    $value = max(0, $value);
    return ($total > 0) ? min($total, $value) : $value;
}

/**
 * Returns every language string the player module needs, keyed by the
 * identifier the JavaScript uses.
 *
 * The teacher-only strings are a large part of the pack, so they are sent
 * only to users who can generate activities.
 *
 * @param bool $includeteacher Whether to include the strings only teachers see.
 * @return array Language strings keyed by JavaScript string identifier.
 */
function aiactivities_get_js_strings(bool $includeteacher = true): array {
    $map = aiactivities_get_js_string_map();

    if (!$includeteacher) {
        foreach (aiactivities_get_js_teacher_string_keys() as $key) {
            unset($map[$key]);
        }
    }

    $strings = [];
    foreach ($map as $jskey => $langkey) {
        $strings[$jskey] = get_string($langkey, 'mod_aiactivities');
    }

    return $strings;
}

/**
 * Returns the string identifiers only used by the generation and editing interface.
 *
 * @return string[] JavaScript string identifiers.
 */
function aiactivities_get_js_teacher_string_keys(): array {
    $keys = [];
    foreach (array_keys(aiactivities_get_js_string_map()) as $key) {
        if (strpos($key, 'js_edit_') === 0 || strpos($key, 'js_audio_') === 0) {
            $keys[] = $key;
        }
    }

    $extrakeys = [
        'js_activities_ready', 'js_credits_unit', 'js_err_genfailed', 'js_err_genstart',
        'js_err_gentimeout', 'js_err_noaudioactivities', 'js_err_pollfailed', 'js_err_savefailed',
        'js_err_savegenerated', 'js_generate_btn', 'js_preview_activity', 'js_preview_complete',
        'js_preview_heading', 'js_role_remove', 'js_sector_placeholder', 'error_no_content',
        'error_delete_last',
        'generate_success', 'generating', 'js_types_none', 'js_types_notapplied',
        'js_delete_cancel', 'js_delete_confirm', 'js_delete_message',
        'js_delete_named', 'js_delete_title', 'js_delete_undo', 'js_deleted_notice',
        'js_err_deletefailed', 'js_err_restorefailed',
    ];
    return array_merge($keys, $extrakeys);
}

/**
 * Maps the string identifiers used by the player module to language pack keys.
 *
 * @return array JavaScript string identifier => language string identifier.
 */
function aiactivities_get_js_string_map(): array {
    $shared = [
        'cardselect_instructions' => 'activity_cardselect_instructions',
        'categorysort_instructions' => 'activity_categorysort_instructions',
        'columnsort_instructions' => 'activity_columnsort_instructions',
        'fillinblank_instructions' => 'activity_fillinblank_instructions',
        'flashcards_instructions' => 'activity_flashcards_instructions',
        'matching_instructions' => 'activity_matching_instructions',
        'ordering_instructions' => 'activity_ordering_instructions',
        'truefalseswipe_instructions' => 'activity_truefalseswipe_instructions',
    ];

    $samename = [
        'check_btn', 'correct', 'error_no_content', 'feedback_correct', 'feedback_incorrect',
        'feedback_welldone', 'fillinblank_wordbank', 'flashcard_back', 'flashcard_done',
        'flashcard_front', 'flashcard_tap', 'generate_success', 'generating', 'incorrect',
        'next_btn', 'next_card_btn', 'truefalse_activity_complete', 'truefalse_answer',
        'truefalse_false', 'truefalse_true',
        'error_delete_last',
        'js_a11y_blank', 'js_a11y_placeincategory', 'js_a11y_placeincolumn', 'js_activities_ready',
        'js_audio_generating', 'js_audio_regenerate', 'js_audio_regenerated', 'js_credits_unit',
        'js_delete_cancel', 'js_delete_confirm', 'js_delete_message',
        'js_delete_named', 'js_delete_title', 'js_delete_undo', 'js_deleted_notice',
        'js_dialog_title', 'js_edit_activity', 'js_edit_belongsto', 'js_edit_blank', 'js_edit_cancel',
        'js_edit_card', 'js_edit_category', 'js_edit_correctanswer', 'js_edit_heading', 'js_edit_item',
        'js_edit_lbl_blankanswers', 'js_edit_lbl_cards', 'js_edit_lbl_categories', 'js_edit_lbl_colaname',
        'js_edit_lbl_colbname', 'js_edit_lbl_flashcards', 'js_edit_lbl_items', 'js_edit_lbl_orderitems',
        'js_edit_lbl_pairs', 'js_edit_lbl_question', 'js_edit_lbl_sentence', 'js_edit_lbl_statements',
        'js_edit_lbl_title', 'js_edit_pair', 'js_edit_ph_back', 'js_edit_ph_description',
        'js_edit_ph_explanation', 'js_edit_ph_front', 'js_edit_ph_label', 'js_edit_ph_left',
        'js_edit_ph_right', 'js_edit_ph_statement', 'js_edit_save', 'js_edit_saving', 'js_edit_statement',
        'js_edit_step', 'js_err_audiofailed', 'js_err_deletefailed', 'js_err_genfailed',
        'js_err_genstart', 'js_err_gentimeout', 'js_err_restorefailed',
        'js_err_http', 'js_err_noaudioactivities', 'js_err_parse', 'js_err_pollfailed', 'js_err_savefailed',
        'js_err_savegenerated', 'js_err_saveprogress', 'js_err_unknown', 'js_err_unknowntype',
        'js_eta_activities', 'js_eta_hour', 'js_eta_hours', 'js_eta_hr', 'js_eta_hrs', 'js_eta_label',
        'js_eta_min', 'js_eta_minute', 'js_eta_minutes', 'js_eta_underminute', 'js_generate_btn',
        'js_matching_from', 'js_matching_to', 'js_nav_locked', 'js_next_activity',
        'js_next_finish', 'js_preview_activity', 'js_preview_complete',
        'js_preview_heading', 'js_role_remove', 'js_sector_placeholder', 'js_streak_4_title',
        'js_streak_4_msg', 'js_streak_8_title', 'js_streak_8_msg', 'js_streak_12_title',
        'js_streak_12_msg', 'js_streak_16_title', 'js_streak_16_msg',
        'js_streak_chip_label', 'js_type_cardselect',
        'js_type_categorysort', 'js_type_columnsort', 'js_type_fillinblank', 'js_type_flashcards',
        'js_type_matching', 'js_type_ordering', 'js_type_truefalse', 'js_types_none',
        'js_types_notapplied', 'js_voiceover_controls',
        'js_voiceover_mute', 'js_voiceover_play', 'js_voiceover_stop', 'js_voiceover_stop_label',
        'js_voiceover_unmute',
    ];

    return $shared + array_combine($samename, $samename);
}
