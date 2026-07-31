<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php'); // required for \curl class
require_once(__DIR__ . '/lib.php');

$action = required_param('action', PARAM_ALPHANUMEXT);
$sesskey = required_param('sesskey', PARAM_RAW);

if (!confirm_sesskey($sesskey)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid session']);
    exit;
}

$apibase = get_config('mod_aiactivities', 'apiurl');
if (empty($apibase)) {
    $apibase = 'https://lms-labs.com';
}
$apibase = rtrim($apibase, '/');

$aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
if (file_exists($aiconfiglib)) {
    require_once($aiconfiglib);
}

$siteid = '';
$apikey = '';
if (function_exists('local_aiconfig_get_siteid')) {
    $siteid = trim(local_aiconfig_get_siteid() ?? '');
}
if (function_exists('local_aiconfig_get_apikey')) {
    $apikey = trim(local_aiconfig_get_apikey() ?? '');
}
if (empty($siteid)) {
    $siteid = trim(get_config('mod_aiactivities', 'siteid') ?? '');
}
if (empty($apikey)) {
    $apikey = trim(get_config('mod_aiactivities', 'apikey') ?? '');
}

// Release session lock before long-running API calls to prevent blocking other requests.
\core\session\manager::write_close();

header('Content-Type: application/json');

switch ($action) {
    case 'getcredits':
        $cmid = required_param('cmid', PARAM_INT);
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/aiactivities:view', $context);

        if (strlen($siteid) === 0 || strlen($apikey) === 0) {
            echo json_encode(['ok' => false, 'error' => 'Plugin not configured']);
            break;
        }

        $url = $apibase . '/api/credits?' . http_build_query(['siteId' => $siteid, 'apiKey' => $apikey], '', '&');

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 30, 'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_FOLLOWLOCATION' => true]);
        $response = $curl->get($url);
        $httpcode = $curl->info['http_code'];

        if ($httpcode === 200) {
            $result = json_decode($response, true);
            echo json_encode(['ok' => true, 'credits' => $result['credits'] ?? 0]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Failed to fetch credits']);
        }
        break;

    case 'generate':
        $cmid = required_param('cmid', PARAM_INT);
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/aiactivities:create', $context);

        $contentraw = required_param('content', PARAM_RAW);
        $content = clean_param($contentraw, PARAM_TEXT);

        if (empty(trim($content))) {
            echo json_encode(['ok' => false, 'error' => get_string('error_no_content', 'mod_aiactivities')]);
            break;
        }
        if (strlen($content) > 50000) {
            $content = substr($content, 0, 50000);
        }

        $language = optional_param('language', 'en-AU', PARAM_TEXT);
        $activitycount = optional_param('activitycount', 5, PARAM_INT);
        $activitycount = max(5, min(20, $activitycount));

        $scenariomode = optional_param('scenariomode', '0', PARAM_TEXT);
        $scenariocountry = optional_param('scenariocountry', '', PARAM_TEXT);
        $scenarioindustry = optional_param('scenarioindustry', '', PARAM_TEXT);
        $scenarioworkerlevel = optional_param('scenarioworkerlevel', '', PARAM_TEXT);
        $scenariojobroles = optional_param('scenariojobroles', '', PARAM_TEXT);
        $voiceoverenabled = optional_param('voiceoverenabled', '0', PARAM_TEXT);
        $voicegender = optional_param('voicegender', 'female', PARAM_TEXT);
        $voiceid = optional_param('voiceid', 'Zephyr', PARAM_TEXT);

        $url = $apibase . '/api/generate-aiactivities';
        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'content' => $content,
            'language' => $language,
            'activityCount' => $activitycount,
        ];

        if ($scenariomode === '1') {
            $payload['scenarioMode'] = true;
            if (!empty(trim($scenariocountry))) {
                $payload['scenarioCountry'] = trim($scenariocountry);
            }
            if (!empty(trim($scenarioindustry))) {
                $payload['scenarioIndustry'] = trim($scenarioindustry);
            }
            if (!empty(trim($scenarioworkerlevel))) {
                $payload['scenarioWorkerLevel'] = trim($scenarioworkerlevel);
            }
            if (!empty(trim($scenariojobroles))) {
                $payload['scenarioJobRoles'] = trim($scenariojobroles);
            }
        }

        if ($voiceoverenabled === '1') {
            $payload['voiceoverEnabled'] = true;
            $payload['voiceGender'] = clean_param($voicegender, PARAM_TEXT);
            $payload['voiceId'] = clean_param($voiceid, PARAM_TEXT);
        }

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 180]);
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post($url, json_encode($payload));
        $curlerror = $curl->error;
        $httpcode = $curl->info['http_code'];

        if ($curlerror) {
            echo json_encode(['ok' => false, 'error' => 'Connection error: ' . $curlerror]);
            break;
        }

        if ($httpcode === 200) {
            $result = json_decode($response, true);
            if ($result && !empty($result['ok']) && !empty($result['activities'])) {
                $DB->set_field('aiactivities', 'activitiesjson', \mod_aiactivities\manifest_storage::compress(json_encode($result['activities'])), ['id' => $instance->id]);
                $DB->set_field('aiactivities', 'content', $content, ['id' => $instance->id]);
                $DB->set_field('aiactivities', 'language', $language, ['id' => $instance->id]);
                $DB->set_field('aiactivities', 'activitycount', $activitycount, ['id' => $instance->id]);
                $DB->set_field('aiactivities', 'voiceoverenabled', ($voiceoverenabled === '1') ? 1 : 0, ['id' => $instance->id]);
                if ($voiceoverenabled === '1') {
                    $DB->set_field('aiactivities', 'voicegender', clean_param($voicegender, PARAM_TEXT), ['id' => $instance->id]);
                    $DB->set_field('aiactivities', 'voiceid', clean_param($voiceid, PARAM_TEXT), ['id' => $instance->id]);
                }
                $DB->set_field('aiactivities', 'timemodified', time(), ['id' => $instance->id]);
                echo json_encode(['ok' => true, 'activities' => $result['activities'], 'creditsUsed' => $result['creditsUsed'] ?? 0]);
            } else {
                $error = $result['error'] ?? 'Invalid API response';
                echo json_encode(['ok' => false, 'error' => $error]);
            }
        } else {
            $result = json_decode($response, true);
            $error = $result['error'] ?? 'API request failed (HTTP ' . $httpcode . ')';
            echo json_encode(['ok' => false, 'error' => $error]);
        }
        break;

    // ASYNC: Start generation job — returns jobId in ~500ms so proxy can't timeout.
    // JS polls case 'poll_job' every 3-4s until status=done.
    case 'generate_async':
        $cmid = required_param('cmid', PARAM_INT);
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/aiactivities:create', $context);

        $contentraw = required_param('content', PARAM_RAW);
        $content = clean_param($contentraw, PARAM_TEXT);
        if (empty(trim($content))) {
            echo json_encode(['ok' => false, 'error' => get_string('error_no_content', 'mod_aiactivities')]);
            break;
        }
        if (strlen($content) > 50000) { $content = substr($content, 0, 50000); }

        $language = optional_param('language', 'en-AU', PARAM_TEXT);
        $activitycount = max(5, min(20, optional_param('activitycount', 5, PARAM_INT)));
        $scenariomode = optional_param('scenariomode', '0', PARAM_TEXT);
        $scenariocountry = optional_param('scenariocountry', '', PARAM_TEXT);
        $scenarioindustry = optional_param('scenarioindustry', '', PARAM_TEXT);
        $scenarioworkerlevel = optional_param('scenarioworkerlevel', '', PARAM_TEXT);
        $scenariojobroles = optional_param('scenariojobroles', '', PARAM_TEXT);
        $voiceoverenabled = optional_param('voiceoverenabled', '0', PARAM_TEXT);
        $voicegender = optional_param('voicegender', 'female', PARAM_TEXT);
        $voiceid = optional_param('voiceid', 'Zephyr', PARAM_TEXT);

        $payload = ['siteId' => $siteid, 'apiKey' => $apikey, 'content' => $content,
                    'language' => $language, 'activityCount' => $activitycount];
        if ($scenariomode === '1') {
            $payload['scenarioMode'] = true;
            if (!empty(trim($scenariocountry)))    { $payload['scenarioCountry']    = trim($scenariocountry); }
            if (!empty(trim($scenarioindustry)))   { $payload['scenarioIndustry']   = trim($scenarioindustry); }
            if (!empty(trim($scenarioworkerlevel))){ $payload['scenarioWorkerLevel'] = trim($scenarioworkerlevel); }
            if (!empty(trim($scenariojobroles)))   { $payload['scenarioJobRoles']   = trim($scenariojobroles); }
        }
        if ($voiceoverenabled === '1') {
            $payload['voiceoverEnabled'] = true;
            $payload['voiceGender'] = clean_param($voicegender, PARAM_TEXT);
            $payload['voiceId'] = clean_param($voiceid, PARAM_TEXT);
        }

        \core\session\manager::write_close();

        $startUrl = $apibase . '/api/generate-aiactivities/start';
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 15]);
        $curl->setHeader(['Content-Type: application/json']);
        $resp = json_decode($curl->post($startUrl, json_encode($payload)), true);

        if (empty($resp['ok']) || empty($resp['jobId'])) {
            echo json_encode(['ok' => false, 'error' => $resp['error'] ?? 'Failed to start generation']);
            break;
        }
        echo json_encode(['ok' => true, 'jobId' => $resp['jobId'], 'async' => true]);
        break;

    // ASYNC POLL: Check background job status.
    case 'poll_job':
        $jobId = required_param('jobId', PARAM_ALPHANUMEXT);
        \core\session\manager::write_close();
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 10]);
        $resp = json_decode($curl->get($apibase . '/api/jobs/' . urlencode($jobId)), true);
        echo json_encode($resp ?: ['ok' => false, 'status' => 'error', 'error' => 'Could not reach job status endpoint']);
        break;

    // ASYNC SAVE: After poll_job returns status=done, JS sends the generated activities
    // back here so they can be saved to the Moodle DB. The generate_async case cannot
    // save to DB itself (it returns immediately without the AI result). This case is the
    // async equivalent of the DB-write section inside the sync 'generate' case.
    case 'save_generated':
        $cmid = required_param('cmid', PARAM_INT);
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/aiactivities:create', $context);

        $activitiesjson  = required_param('activitiesjson', PARAM_RAW);
        $content         = optional_param('content', '', PARAM_RAW);
        $language        = optional_param('language', 'en-AU', PARAM_TEXT);
        $activitycount   = max(5, min(20, optional_param('activitycount', 5, PARAM_INT)));
        $voiceoverenabled = optional_param('voiceoverenabled', '0', PARAM_TEXT);
        $voicegender     = optional_param('voicegender', 'female', PARAM_TEXT);
        $voiceid         = optional_param('voiceid', 'Aoede', PARAM_TEXT);

        $activities = json_decode($activitiesjson, true);
        if (empty($activities) || !is_array($activities)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid activities data']);
            break;
        }

        $DB->set_field('aiactivities', 'activitiesjson',
            \mod_aiactivities\manifest_storage::compress(json_encode($activities)),
            ['id' => $instance->id]);
        $cleanContent = clean_param($content, PARAM_TEXT);
        if (!empty(trim($cleanContent))) {
            $DB->set_field('aiactivities', 'content', $cleanContent, ['id' => $instance->id]);
        }
        $DB->set_field('aiactivities', 'language', $language, ['id' => $instance->id]);
        $DB->set_field('aiactivities', 'activitycount', $activitycount, ['id' => $instance->id]);
        $DB->set_field('aiactivities', 'voiceoverenabled', ($voiceoverenabled === '1') ? 1 : 0, ['id' => $instance->id]);
        if ($voiceoverenabled === '1') {
            $DB->set_field('aiactivities', 'voicegender', clean_param($voicegender, PARAM_TEXT), ['id' => $instance->id]);
            $DB->set_field('aiactivities', 'voiceid', clean_param($voiceid, PARAM_TEXT), ['id' => $instance->id]);
        }
        $DB->set_field('aiactivities', 'timemodified', time(), ['id' => $instance->id]);

        echo json_encode(['ok' => true]);
        break;

    // Save voiceover settings without regenerating activities.
    case 'savevoicesettings':
        $cmid = required_param('cmid', PARAM_INT);
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/aiactivities:create', $context);

        $voiceoverenabled = optional_param('voiceoverenabled', '0', PARAM_TEXT);
        $voicegender = optional_param('voicegender', 'female', PARAM_TEXT);
        $voiceid = optional_param('voiceid', 'Zephyr', PARAM_TEXT);

        $DB->set_field('aiactivities', 'voiceoverenabled', ($voiceoverenabled === '1') ? 1 : 0, ['id' => $instance->id]);
        $DB->set_field('aiactivities', 'voicegender', clean_param($voicegender, PARAM_TEXT), ['id' => $instance->id]);
        $DB->set_field('aiactivities', 'voiceid', clean_param($voiceid, PARAM_TEXT), ['id' => $instance->id]);
        $DB->set_field('aiactivities', 'timemodified', time(), ['id' => $instance->id]);

        echo json_encode(['ok' => true]);
        break;

    // Regenerate voiceover audio for existing activities by calling server TTS endpoint.
    case 'regenerateaudio':
        $cmid = required_param('cmid', PARAM_INT);
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/aiactivities:create', $context);

        $activitiesjsonraw = required_param('activitiesjson', PARAM_RAW);
        $voicelanguage = optional_param('voicelanguage', 'en-AU', PARAM_TEXT);
        $voiceid = optional_param('voiceid', 'Zephyr', PARAM_TEXT);

        $activities = json_decode($activitiesjsonraw, true);
        if (empty($activities) || !is_array($activities)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid activities data']);
            break;
        }

        $url = $apibase . '/api/aiactivities-regenerate-audio';
        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'activities' => $activities,
            'voiceLanguage' => $voicelanguage,
            'voiceId' => $voiceid,
        ];

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 180]);
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post($url, json_encode($payload));
        $httpcode = $curl->info['http_code'];

        if ($httpcode === 200) {
            $result = json_decode($response, true);
            if ($result && !empty($result['ok']) && !empty($result['activities'])) {
                $DB->set_field('aiactivities', 'activitiesjson',
                    \mod_aiactivities\manifest_storage::compress(json_encode($result['activities'])),
                    ['id' => $instance->id]);
                $DB->set_field('aiactivities', 'timemodified', time(), ['id' => $instance->id]);
                echo json_encode(['ok' => true, 'activities' => $result['activities']]);
            } else {
                echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Audio generation failed']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Server error ' . $httpcode]);
        }
        break;

    case 'startattempt':
        $cmid = required_param('cmid', PARAM_INT);
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/aiactivities:view', $context);

        $userid = $USER->id;

        $completed = $DB->get_record('aiactivities_attempts', [
            'aiactivitiesid' => $instance->id,
            'userid' => $userid,
            'status' => 1,
        ]);

        if ($completed) {
            echo json_encode([
                'ok' => true,
                'attemptid' => $completed->id,
                'resumed' => true,
                'completed' => true,
                'currentactivity' => 0,
                'progress' => [],
            ]);
            break;
        }

        $inprogress = $DB->get_record('aiactivities_attempts', [
            'aiactivitiesid' => $instance->id,
            'userid' => $userid,
            'status' => 0,
        ]);

        if ($inprogress) {
            echo json_encode([
                'ok' => true,
                'attemptid' => $inprogress->id,
                'resumed' => true,
                'completed' => false,
                'currentactivity' => (int)$inprogress->currentactivity,
                'progress' => json_decode($inprogress->progressjson, true) ?: [],
            ]);
            break;
        }

        $activities = json_decode(\mod_aiactivities\manifest_storage::decompress($instance->activitiesjson), true);
        $totalcount = is_array($activities) ? count($activities) : 0;

        $now = time();
        $attempt = new stdClass();
        $attempt->aiactivitiesid = $instance->id;
        $attempt->userid = $userid;
        $attempt->progressjson = '{}';
        $attempt->currentactivity = 0;
        $attempt->completedcount = 0;
        $attempt->totalcount = $totalcount;
        $attempt->score = 0;
        $attempt->status = 0;
        $attempt->timecreated = $now;
        $attempt->timemodified = $now;

        $attemptid = $DB->insert_record('aiactivities_attempts', $attempt);

        echo json_encode([
            'ok' => true,
            'attemptid' => $attemptid,
            'resumed' => false,
            'currentactivity' => 0,
            'progress' => [],
        ]);
        break;

    case 'saveprogress':
        $attemptid = required_param('attemptid', PARAM_INT);
        $currentactivity = required_param('currentactivity', PARAM_INT);
        $progressjson = required_param('progressjson', PARAM_RAW);
        $completedcount = required_param('completedcount', PARAM_INT);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        $instance = $DB->get_record('aiactivities', ['id' => $attempt->aiactivitiesid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('aiactivities', $instance->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        require_login($course, false, $cm);

        if ($attempt->userid != $USER->id) {
            echo json_encode(['ok' => false, 'error' => 'Invalid attempt']);
            break;
        }

        $attempt->currentactivity = $currentactivity;
        $attempt->progressjson = $progressjson;
        $attempt->completedcount = $completedcount;
        $attempt->timemodified = time();

        $DB->update_record('aiactivities_attempts', $attempt);

        echo json_encode(['ok' => true]);
        break;

    case 'complete':
        $attemptid = required_param('attemptid', PARAM_INT);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        $instance = $DB->get_record('aiactivities', ['id' => $attempt->aiactivitiesid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('aiactivities', $instance->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        require_login($course, false, $cm);

        if ($attempt->userid != $USER->id) {
            echo json_encode(['ok' => false, 'error' => 'Invalid attempt']);
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

    case 'saveactivity':
        $cmid = required_param('cmid', PARAM_INT);
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/aiactivities:create', $context);

        $activityindex = required_param('activityindex', PARAM_INT);
        $activityjson = required_param('activityjson', PARAM_RAW);

        $allactivities = json_decode(\mod_aiactivities\manifest_storage::decompress($instance->activitiesjson), true);
        if (!is_array($allactivities)) {
            echo json_encode(['ok' => false, 'error' => 'No activities found']);
            break;
        }

        if ($activityindex < 0 || $activityindex >= count($allactivities)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid activity index']);
            break;
        }

        $updatedactivity = json_decode($activityjson, true);
        if (!$updatedactivity || !is_array($updatedactivity)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid activity data']);
            break;
        }

        $allactivities[$activityindex] = $updatedactivity;

        $DB->set_field('aiactivities', 'activitiesjson', \mod_aiactivities\manifest_storage::compress(json_encode($allactivities)), ['id' => $instance->id]);
        $DB->set_field('aiactivities', 'timemodified', time(), ['id' => $instance->id]);

        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
