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
 * Displays a single AI Learning Activities instance.
 *
 * Users with mod/aiactivities:create see the generation and management
 * interface; everybody else sees the activity player.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('aiactivities', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aiactivities:view', $context);

$cancreate = has_capability('mod/aiactivities:create', $context);

[$siteid, $apikey] = aiactivities_get_credentials();

$PAGE->set_url('/mod/aiactivities/view.php', ['id' => $id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$nextactivityurl = '';
$modinfo = get_fast_modinfo($course);
$cms = $modinfo->get_cms();
$cmids = array_keys($cms);
$currentpos = array_search($cm->id, $cmids);
if ($currentpos !== false) {
    for ($i = $currentpos + 1; $i < count($cmids); $i++) {
        $nextcm = $cms[$cmids[$i]];
        if ($nextcm->uservisible && !$nextcm->is_stealth()) {
            $nextactivityurl = $nextcm->url ? $nextcm->url->out(false) : '';
            break;
        }
    }
}

aiactivities_view($instance, $course, $cm, $context);

echo $OUTPUT->header();

if ($siteid === '' || $apikey === '') {
    echo $OUTPUT->notification(get_string('not_configured', 'mod_aiactivities'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

$activities = null;
if (!empty($instance->activitiesjson)) {
    $decoded = json_decode(\mod_aiactivities\manifest_storage::decompress($instance->activitiesjson), true);
    if (is_array($decoded) && !empty($decoded)) {
        $activities = $decoded;
    }
}
$hasactivities = ($activities !== null);

/** @var \mod_aiactivities\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_aiactivities');

if ($cancreate) {
    echo $renderer->render_teacher_interface($instance, $hasactivities);
} else if ($hasactivities) {
    echo $renderer->render_player();
} else {
    echo $OUTPUT->notification(get_string('no_activities_yet', 'mod_aiactivities'), 'info');
}

$config = [
    'cmid' => (int)$cm->id,
    'instanceid' => (int)$instance->id,
    'sesskey' => sesskey(),
    'cancreate' => (bool)$cancreate,
    'hasactivities' => $hasactivities,
    'activitiesjson' => $activities,
    'ajaxurl' => (new moodle_url('/mod/aiactivities/ajax.php'))->out(false),
    'strings' => aiactivities_get_js_strings($cancreate),
    'taxonomy' => $cancreate ? \mod_aiactivities\local\taxonomy::get_localised() : [],
    'nextactivityurl' => $nextactivityurl,
    'voiceoverenabled' => !empty($instance->voiceoverenabled),
    'voicegender' => $instance->voicegender ?? 'female',
    'voiceid' => $instance->voiceid ?? 'Zephyr',
    'voicelanguage' => $instance->language ?? 'en-AU',
];

// The configuration carries the language strings, the scenario vocabulary and the
// activity manifest, which is far more than js_call_amd() is meant to inline. It is
// written into a non-executable JSON data island instead and read by the module.
echo html_writer::tag(
    'script',
    json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ['type' => 'application/json', 'id' => 'ala-player-config']
);

$PAGE->requires->js_call_amd('mod_aiactivities/player', 'init');

echo $OUTPUT->footer();
