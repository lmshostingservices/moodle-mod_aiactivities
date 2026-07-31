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
 * Library of functions for AI Learning Activities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

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
            return false;
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

function aiactivities_add_instance($data, ?object $mform = null) {
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

function aiactivities_update_instance($data, ?object $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    if (!isset($data->activitycount)) {
        $data->activitycount = 5;
    }
    if (!isset($data->completionallcorrect)) {
        $data->completionallcorrect = 1;
    }

    $result = $DB->update_record('aiactivities', $data);

    return $result;
}

function aiactivities_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record('aiactivities', ['id' => $id]);
    if (!$instance) {
        return false;
    }

    // NOTE: There is NO aiactivities_items table — activity data is stored as JSON in
    // the activitiesjson column of the aiactivities table itself. The delete_records()
    // call against 'aiactivities_items' was orphaned dead code that caused a fatal
    // "Table does not exist" exception whenever a course containing this activity was
    // deleted (course/delete.php → remove_course_contents → aiactivities_delete_instance).
    $DB->delete_records('aiactivities_attempts', ['aiactivitiesid' => $id]);

    $DB->delete_records('aiactivities', ['id' => $id]);

    return true;
}

function aiactivities_get_extra_capabilities() {
    return ['moodle/site:accessallgroups'];
}

function mod_aiactivities_get_fontawesome_icon_map() {
    return [
        'mod_aiactivities:icon' => 'fa-puzzle-piece',
    ];
}

function aiactivities_get_coursemodule_info($coursemodule) {
    global $DB;

    if (!$instance = $DB->get_record('aiactivities', ['id' => $coursemodule->instance],
            'id, name, intro, introformat, completionallcorrect')) {
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

function aiactivities_view($instance, $course, $cm, $context) {
    $event = \mod_aiactivities\event\course_module_viewed::create([
        'objectid' => $instance->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('aiactivities', $instance);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

function aiactivities_cm_info_view(cm_info $cm) {
}

function aiactivities_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $navref) {
}

function aiactivities_get_file_areas($course, $cm, $context) {
    return [];
}

function aiactivities_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    return false;
}

function mod_aiactivities_get_completion_active_rule_descriptions($cm) {
    global $DB;

    $descriptions = [];
    $instance = $DB->get_record('aiactivities', ['id' => $cm->instance]);

    if ($instance && !empty($instance->completionallcorrect)) {
        $descriptions[] = get_string('completiondetail:completionallcorrect', 'mod_aiactivities');
    }

    return $descriptions;
}
