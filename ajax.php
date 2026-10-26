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
 * AJAX endpoint for the AI Learning Activities player and generation form.
 *
 * Every action requires a valid session key, an authenticated user with access
 * to the course module named by the cmid parameter, and the capability that
 * matches the action.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

$action = required_param('action', PARAM_ALPHANUMEXT);
$cmid = required_param('cmid', PARAM_INT);

require_sesskey();

$cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aiactivities:view', $context);

// Actions that change generated content and are limited to users who may create it.
$createactions = ['generate', 'generate_async', 'poll_job', 'save_generated', 'savevoicesettings',
    'regenerateaudio', 'saveactivity', 'getcredits'];
if (in_array($action, $createactions, true)) {
    require_capability('mod/aiactivities:create', $context);
}

$apibase = aiactivities_get_api_base();
[$siteid, $apikey] = aiactivities_get_credentials();

// Release the session lock before any long-running API call so that other
// requests from the same user are not blocked.
\core\session\manager::write_close();

header('Content-Type: application/json');

/**
 * Returns a configured curl instance for talking to the generation service.
 *
 * @param int $timeout Request timeout in seconds.
 * @return \curl The configured curl instance.
 */
function aiactivities_api_curl(int $timeout): \curl {
    $curl = new \curl();
    $curloptions = [
        'CURLOPT_TIMEOUT' => $timeout,
        'CURLOPT_CONNECTTIMEOUT' => 15,
        'CURLOPT_SSL_VERIFYPEER' => true,
        'CURLOPT_SSL_VERIFYHOST' => 2,
        'CURLOPT_FOLLOWLOCATION' => false,
    ];
    $curl->setopt($curloptions);
    return $curl;
}

switch ($action) {
    case 'getcredits':
        if ($siteid === '' || $apikey === '') {
            echo json_encode(['ok' => false, 'error' => get_string('not_configured', 'mod_aiactivities')]);
            break;
        }

        // The credentials are sent in the request body so that they do not
        // appear in web server or proxy logs. Older deployments of the service
        // only accept them as query parameters, so fall back to that form.
        $curl = aiactivities_api_curl(30);
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post(
            $apibase . '/api/credits',
            json_encode(['siteId' => $siteid, 'apiKey' => $apikey])
        );
        $httpcode = (int)$curl->info['http_code'];

        // A service that only accepts the credentials as query parameters can
        // answer a JSON POST in several ways, so the fallback covers all of
        // them. Note that redirects are not followed: the configured API URL
        // must be the final URL, not one that redirects to it.
        if (in_array($httpcode, [301, 302, 303, 307, 308, 400, 403, 405, 406, 415, 501], true)) {
            $curl = aiactivities_api_curl(30);
            $query = http_build_query(['siteId' => $siteid, 'apiKey' => $apikey], '', '&');
            $response = $curl->get($apibase . '/api/credits?' . $query);
            $httpcode = (int)$curl->info['http_code'];
        }

        if ($httpcode === 200) {
            $result = json_decode($response, true);
            echo json_encode(['ok' => true, 'credits' => $result['credits'] ?? 0]);
        } else {
            echo json_encode(['ok' => false, 'error' => get_string('error_credits_failed', 'mod_aiactivities')]);
        }
        break;

    case 'generate':
        $content = required_param('content', PARAM_TEXT);

        if (trim($content) === '') {
            echo json_encode(['ok' => false, 'error' => get_string('error_no_content', 'mod_aiactivities')]);
            break;
        }
        if (core_text::strlen($content) > 50000) {
            $content = core_text::substr($content, 0, 50000);
        }

        $options = aiactivities_read_generation_options();
        $payload = aiactivities_build_generation_payload($siteid, $apikey, $content, $options);

        $curl = aiactivities_api_curl(180);
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post($apibase . '/api/generate-aiactivities', json_encode($payload));

        if ($curl->error) {
            echo json_encode(['ok' => false, 'error' => get_string('error_connection', 'mod_aiactivities')]);
            break;
        }

        $result = json_decode($response, true);

        if ((int)$curl->info['http_code'] === 200 && $result && !empty($result['ok']) && !empty($result['activities'])) {
            [$activities, $typesapplied] = aiactivities_apply_type_filter($result['activities'], $options['activitytypes']);
            aiactivities_store_activities($instance->id, $activities, $content, $options);
            $success = [
                'ok' => true,
                'activities' => $activities,
                'creditsUsed' => $result['creditsUsed'] ?? 0,
                'typesapplied' => $typesapplied,
            ];
            echo json_encode($success);
        } else {
            $failure = [
                'ok' => false,
                'error' => $result['error'] ?? get_string('error_generation_failed', 'mod_aiactivities'),
            ];
            echo json_encode($failure);
        }
        break;

    case 'generate_async':
        // Starts a background generation job and returns immediately with a job
        // id, so that a slow generation cannot time out at a reverse proxy. The
        // player polls the poll_job action until the job reports it is done.
        $content = required_param('content', PARAM_TEXT);

        if (trim($content) === '') {
            echo json_encode(['ok' => false, 'error' => get_string('error_no_content', 'mod_aiactivities')]);
            break;
        }
        if (core_text::strlen($content) > 50000) {
            $content = core_text::substr($content, 0, 50000);
        }

        $options = aiactivities_read_generation_options();
        $payload = aiactivities_build_generation_payload($siteid, $apikey, $content, $options);

        $curl = aiactivities_api_curl(15);
        $curl->setHeader(['Content-Type: application/json']);
        $response = json_decode($curl->post($apibase . '/api/generate-aiactivities/start', json_encode($payload)), true);

        if (empty($response['ok']) || empty($response['jobId'])) {
            $failure = [
                'ok' => false,
                'error' => $response['error'] ?? get_string('error_generation_start', 'mod_aiactivities'),
            ];
            echo json_encode($failure);
            break;
        }

        echo json_encode(['ok' => true, 'jobId' => $response['jobId'], 'async' => true]);
        break;

    case 'poll_job':
        $jobid = required_param('jobId', PARAM_ALPHANUMEXT);

        $curl = aiactivities_api_curl(10);
        $response = json_decode($curl->get($apibase . '/api/jobs/' . urlencode($jobid)), true);

        $fallback = [
            'ok' => false,
            'status' => 'error',
            'error' => get_string('error_job_status', 'mod_aiactivities'),
        ];
        echo json_encode($response ?: $fallback);
        break;

    case 'save_generated':
        // Stores the activities produced by an asynchronous job. The
        // generate_async action returns before the result exists, so the
        // player sends it back here once polling reports the job is done.
        // phpcs:ignore moodle.Commenting.InlineComment.NotCapital -- Verbatim pipeline annotation.
        $json = required_param('activitiesjson', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, validated below.
        $activities = json_decode($json, true);

        if (empty($activities) || !is_array($activities)) {
            echo json_encode(['ok' => false, 'error' => get_string('error_invalid_activities', 'mod_aiactivities')]);
            break;
        }

        $content = optional_param('content', '', PARAM_TEXT);
        $options = aiactivities_read_generation_options();

        [$activities, $typesapplied] = aiactivities_apply_type_filter($activities, $options['activitytypes']);
        aiactivities_store_activities($instance->id, $activities, $content, $options);

        echo json_encode(['ok' => true, 'activities' => $activities, 'typesapplied' => $typesapplied]);
        break;

    case 'savevoicesettings':
        $voiceoverenabled = optional_param('voiceoverenabled', '0', PARAM_TEXT);

        $update = new stdClass();
        $update->id = $instance->id;
        $update->voiceoverenabled = ($voiceoverenabled === '1') ? 1 : 0;
        $update->voicegender = clean_param(optional_param('voicegender', 'female', PARAM_TEXT), PARAM_TEXT);
        $update->voiceid = clean_param(optional_param('voiceid', 'Zephyr', PARAM_TEXT), PARAM_TEXT);
        $update->timemodified = time();
        $DB->update_record('aiactivities', $update);

        echo json_encode(['ok' => true]);
        break;

    case 'regenerateaudio':
        // phpcs:ignore moodle.Commenting.InlineComment.NotCapital -- Verbatim pipeline annotation.
        $json = required_param('activitiesjson', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, validated below.
        $activities = json_decode($json, true);

        if (empty($activities) || !is_array($activities)) {
            echo json_encode(['ok' => false, 'error' => get_string('error_invalid_activities', 'mod_aiactivities')]);
            break;
        }

        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'activities' => $activities,
            'voiceLanguage' => optional_param('voicelanguage', 'en-AU', PARAM_TEXT),
            'voiceId' => clean_param(optional_param('voiceid', 'Zephyr', PARAM_TEXT), PARAM_TEXT),
        ];

        $curl = aiactivities_api_curl(180);
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post($apibase . '/api/aiactivities-regenerate-audio', json_encode($payload));
        $result = json_decode($response, true);

        if ((int)$curl->info['http_code'] === 200 && $result && !empty($result['ok']) && !empty($result['activities'])) {
            $update = new stdClass();
            $update->id = $instance->id;
            $update->activitiesjson = \mod_aiactivities\manifest_storage::compress(json_encode($result['activities']));
            $update->timemodified = time();
            $DB->update_record('aiactivities', $update);

            echo json_encode(['ok' => true, 'activities' => $result['activities']]);
        } else {
            $failure = [
                'ok' => false,
                'error' => $result['error'] ?? get_string('error_audio_failed', 'mod_aiactivities'),
            ];
            echo json_encode($failure);
        }
        break;

    case 'saveactivity':
        $activityindex = required_param('activityindex', PARAM_INT);
        // phpcs:ignore moodle.Commenting.InlineComment.NotCapital -- Verbatim pipeline annotation.
        $json = required_param('activityjson', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, validated below.
        $updatedactivity = json_decode($json, true);

        $allactivities = json_decode(\mod_aiactivities\manifest_storage::decompress($instance->activitiesjson), true);
        if (!is_array($allactivities)) {
            echo json_encode(['ok' => false, 'error' => get_string('error_no_activities', 'mod_aiactivities')]);
            break;
        }

        if ($activityindex < 0 || $activityindex >= count($allactivities)) {
            echo json_encode(['ok' => false, 'error' => get_string('error_invalid_index', 'mod_aiactivities')]);
            break;
        }

        if (empty($updatedactivity) || !is_array($updatedactivity)) {
            echo json_encode(['ok' => false, 'error' => get_string('error_invalid_activities', 'mod_aiactivities')]);
            break;
        }

        $allactivities[$activityindex] = $updatedactivity;

        $update = new stdClass();
        $update->id = $instance->id;
        $update->activitiesjson = \mod_aiactivities\manifest_storage::compress(json_encode($allactivities));
        $update->timemodified = time();
        $DB->update_record('aiactivities', $update);

        echo json_encode(['ok' => true]);
        break;

    case 'startattempt':
        $completedconditions = [
            'aiactivitiesid' => $instance->id,
            'userid' => $USER->id,
            'status' => 1,
        ];
        $completed = $DB->get_record('aiactivities_attempts', $completedconditions);

        if ($completed) {
            $completedresponse = [
                'ok' => true,
                'attemptid' => $completed->id,
                'resumed' => true,
                'completed' => true,
                'currentactivity' => 0,
                'progress' => [],
            ];
            echo json_encode($completedresponse);
            break;
        }

        $inprogressconditions = [
            'aiactivitiesid' => $instance->id,
            'userid' => $USER->id,
            'status' => 0,
        ];
        $inprogress = $DB->get_record('aiactivities_attempts', $inprogressconditions);

        if ($inprogress) {
            $resumeresponse = [
                'ok' => true,
                'attemptid' => $inprogress->id,
                'resumed' => true,
                'completed' => false,
                'currentactivity' => (int)$inprogress->currentactivity,
                'progress' => json_decode($inprogress->progressjson, true) ?: [],
            ];
            echo json_encode($resumeresponse);
            break;
        }

        $activities = json_decode(\mod_aiactivities\manifest_storage::decompress($instance->activitiesjson), true);

        $now = time();
        $attempt = new stdClass();
        $attempt->aiactivitiesid = $instance->id;
        $attempt->userid = $USER->id;
        $attempt->progressjson = '{}';
        $attempt->currentactivity = 0;
        $attempt->completedcount = 0;
        $attempt->totalcount = is_array($activities) ? count($activities) : 0;
        $attempt->score = 0;
        $attempt->status = 0;
        $attempt->timecreated = $now;
        $attempt->timemodified = $now;

        $newattemptresponse = [
            'ok' => true,
            'attemptid' => $DB->insert_record('aiactivities_attempts', $attempt),
            'resumed' => false,
            'currentactivity' => 0,
            'progress' => [],
        ];
        echo json_encode($newattemptresponse);
        break;

    case 'saveprogress':
        $attemptid = required_param('attemptid', PARAM_INT);
        $currentactivity = required_param('currentactivity', PARAM_INT);
        // phpcs:ignore moodle.Commenting.InlineComment.NotCapital -- Verbatim pipeline annotation.
        $progressjson = required_param('progressjson', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, validated.
        $completedcount = required_param('completedcount', PARAM_INT);

        $attempt = $DB->get_record(
            'aiactivities_attempts',
            ['id' => $attemptid, 'aiactivitiesid' => $instance->id, 'userid' => $USER->id]
        );

        if (!$attempt) {
            echo json_encode(['ok' => false, 'error' => get_string('invalidattempt', 'mod_aiactivities')]);
            break;
        }

        $progress = json_decode($progressjson, true);
        if (!is_array($progress)) {
            echo json_encode(['ok' => false, 'error' => get_string('error_invalid_progress', 'mod_aiactivities')]);
            break;
        }

        $attempt->currentactivity = aiactivities_clamp_index($currentactivity, (int)$attempt->totalcount);
        $attempt->progressjson = json_encode($progress);
        $attempt->completedcount = aiactivities_clamp_index($completedcount, (int)$attempt->totalcount);
        $attempt->timemodified = time();

        $DB->update_record('aiactivities_attempts', $attempt);

        echo json_encode(['ok' => true]);
        break;

    case 'complete':
        $attemptid = required_param('attemptid', PARAM_INT);

        $attempt = $DB->get_record(
            'aiactivities_attempts',
            ['id' => $attemptid, 'aiactivitiesid' => $instance->id, 'userid' => $USER->id]
        );

        if (!$attempt) {
            echo json_encode(['ok' => false, 'error' => get_string('invalidattempt', 'mod_aiactivities')]);
            break;
        }

        $attempt->status = 1;
        $attempt->score = 100;
        $attempt->completedcount = $attempt->totalcount;
        $attempt->timemodified = time();
        $DB->update_record('aiactivities_attempts', $attempt);

        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
        }

        echo json_encode(['ok' => true, 'completed' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => get_string('error_unknown_action', 'mod_aiactivities')]);
}

/**
 * Reads the generation options from the current request.
 *
 * @return array The generation options.
 */
function aiactivities_read_generation_options(): array {
    return [
        'language' => optional_param('language', 'en-AU', PARAM_TEXT),
        'activitycount' => max(5, min(20, optional_param('activitycount', 5, PARAM_INT))),
        'scenariomode' => optional_param('scenariomode', '0', PARAM_TEXT) === '1',
        'scenario' => [
            'scenarioCountry' => trim(optional_param('scenariocountry', '', PARAM_TEXT)),
            'scenarioIndustry' => trim(optional_param('scenarioindustry', '', PARAM_TEXT)),
            'scenarioSector' => trim(optional_param('scenariosector', '', PARAM_TEXT)),
            'scenarioWorkerLevel' => trim(optional_param('scenarioworkerlevel', '', PARAM_TEXT)),
            'scenarioJobRoles' => trim(optional_param('scenariojobroles', '', PARAM_TEXT)),
        ],
        'voiceoverenabled' => optional_param('voiceoverenabled', '0', PARAM_TEXT) === '1',
        'voicegender' => clean_param(optional_param('voicegender', 'female', PARAM_TEXT), PARAM_TEXT),
        'voiceid' => clean_param(optional_param('voiceid', 'Zephyr', PARAM_TEXT), PARAM_TEXT),
        'activitytypes' => \mod_aiactivities\local\activity_types::parse(
            optional_param('activitytypes', '', PARAM_TEXT)
        ),
    ];
}

/**
 * Builds the request payload sent to the generation service.
 *
 * @param string $siteid The configured site identifier.
 * @param string $apikey The configured API key.
 * @param string $content The learning content to generate from.
 * @param array $options Options as returned by aiactivities_read_generation_options().
 * @return array The request payload.
 */
function aiactivities_build_generation_payload(
    string $siteid,
    string $apikey,
    string $content,
    array $options
): array {
    $payload = [
        'siteId' => $siteid,
        'apiKey' => $apikey,
        'content' => $content,
        'language' => $options['language'],
        'activityCount' => $options['activitycount'],
    ];

    // Only sent when the teacher excluded something, so an unrestricted set keeps
    // the request shape the service has always received.
    if (\mod_aiactivities\local\activity_types::is_restricted($options['activitytypes'])) {
        $payload['activityTypes'] = array_values($options['activitytypes']);
    }

    if (!empty($options['scenariomode'])) {
        $payload['scenarioMode'] = true;
        foreach ($options['scenario'] as $key => $value) {
            if ($value !== '') {
                $payload[$key] = $value;
            }
        }
    }

    if (!empty($options['voiceoverenabled'])) {
        $payload['voiceoverEnabled'] = true;
        $payload['voiceGender'] = $options['voicegender'];
        $payload['voiceId'] = $options['voiceid'];
    }

    return $payload;
}

/**
 * Applies the teacher's activity type choice to a generated set.
 *
 * The generation service is asked for the chosen types, but the choice is also
 * enforced here so an older service that ignores the request still produces the
 * set the teacher asked for. If honouring the choice would leave nothing at all,
 * the full set is kept instead: the site has already paid for it, and an empty
 * set would be worse than an unfiltered one.
 *
 * @param array $activities The generated activities.
 * @param string[] $types The selected types.
 * @return array[] The activities to store, and whether the choice was honourable.
 */
function aiactivities_apply_type_filter(array $activities, array $types): array {
    $kept = \mod_aiactivities\local\activity_types::filter($activities, $types);

    if (!$kept) {
        return [array_values($activities), false];
    }

    return [$kept, true];
}

/**
 * Stores generated activities and the settings they were generated with.
 *
 * @param int $instanceid The aiactivities instance id.
 * @param array $activities The generated activities.
 * @param string $content The learning content used, or an empty string to keep the stored one.
 * @param array $options Options as returned by aiactivities_read_generation_options().
 * @return void
 */
function aiactivities_store_activities(
    int $instanceid,
    array $activities,
    string $content,
    array $options
): void {
    global $DB;

    $update = new stdClass();
    $update->id = $instanceid;
    $update->activitiesjson = \mod_aiactivities\manifest_storage::compress(json_encode($activities));
    $update->language = $options['language'];
    $update->activitycount = $options['activitycount'];
    $update->voiceoverenabled = !empty($options['voiceoverenabled']) ? 1 : 0;
    $update->activitytypes = \mod_aiactivities\local\activity_types::to_stored_value($options['activitytypes']);
    $update->timemodified = time();

    if (trim($content) !== '') {
        $update->content = $content;
    }
    if (!empty($options['voiceoverenabled'])) {
        $update->voicegender = $options['voicegender'];
        $update->voiceid = $options['voiceid'];
    }

    $DB->update_record('aiactivities', $update);
}
