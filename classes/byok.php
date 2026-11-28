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

namespace mod_aiactivities;

use mod_aiactivities\local\byok\engine;
use mod_aiactivities\local\byok\http_error;

/**
 * Bring your own AI: runs activity writing and narration on the site's own AI instead of LMS Labs credits.
 *
 * With LMS Labs credits the work runs on the LMS Labs service, as before. When the site administrator turns on
 * Bring your own AI (AI Connect), the service's own route logic runs here, in Moodle, on the site's AI provider
 * (engine.php is a port of it). Writing (text) and narration (voice) are set separately, so either can stay on
 * credits: the part that stays goes to LMS Labs as before.
 *
 * Every method that stands in for a service call answers exactly as that call would, or returns null when the
 * call should still go to LMS Labs.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok {
    /** Plugin component, as AI Connect knows it. */
    public const COMPONENT = 'mod_aiactivities';
    /** Seconds the site's AI may take to write a set of activities. */
    public const TEXT_TIMEOUT = 300;
    /** Speaking rate the service narrates at. */
    public const SPEAKING_RATE = 0.92;
    /** Job ids of generations running on the site's own AI start with this. */
    public const JOB_PREFIX = 'byok';
    /** The closest OpenAI voice to each Google Chirp 3 HD voice the activity offers. */
    public const OPENAI_VOICES = ['Aoede' => 'nova', 'Kore' => 'shimmer', 'Leda' => 'coral', 'Zephyr' => 'sage',
        'Puck' => 'onyx', 'Charon' => 'echo', 'Fenrir' => 'ash', 'Orus' => 'ballad'];
    /** What the routes need in siteId and apiKey when nothing goes to LMS Labs. */
    public const NO_CREDENTIALS = 'byok';

    /**
     * Whether a kind of work runs on the site's own AI now.
     *
     * @param string $kind text or voice.
     * @return bool
     */
    public static function uses(string $kind): bool {
        return class_exists('\local_aiconfig\byok\ai') && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, $kind);
    }

    /**
     * Which kinds of work run on the site's own AI, for the page.
     *
     * @return array text, voice => bool.
     */
    public static function state(): array {
        return ['text' => self::uses('text'), 'voice' => self::uses('voice')];
    }

    /**
     * Point the engine at the site's own AI.
     */
    protected static function hooks(): void {
        engine::$aicall = function (string $system, string $user, array $options): string {
            return \local_aiconfig\byok\ai::text(self::COMPONENT, $system, $user, [
                'json' => !empty($options['json']),
                'maxtokens' => (int) $options['maxtokens'],
                'temperature' => (float) $options['temperature'],
                'timeout' => self::TEXT_TIMEOUT,
            ]);
        };
        engine::$tts = function (string $text, array $voice): string {
            return self::speech($text, $voice);
        };
    }

    /**
     * One narration clip in Ogg Opus, the format the service narrates in.
     *
     * The service narrates with Google Chirp 3 HD voices. A Google key uses the same voice; an OpenAI key gets the
     * closest OpenAI voice of the same kind; other providers use the voice the administrator chose.
     *
     * @param string $text
     * @param array $voice languageCode, name, id (from engine::voice_config()).
     * @return string Audio bytes.
     */
    protected static function speech(string $text, array $voice): string {
        $vendor = \local_aiconfig\byok\router::vendor(\local_aiconfig\byok\router::VOICE);
        if ($vendor === 'googletts') {
            $name = $voice['name'];
        } else if ($vendor === 'openai') {
            $name = self::OPENAI_VOICES[$voice['id']] ?? '';
        } else {
            $name = '';
        }
        return \local_aiconfig\byok\ai::speech(
            self::COMPONENT,
            $text,
            ['language' => $voice['languageCode'], 'voice' => $name, 'format' => 'ogg', 'rate' => self::SPEAKING_RATE]
        );
    }

    /**
     * Fill in the credentials the routes check for when the work does not go to LMS Labs.
     *
     * @param array $payload
     * @return array
     */
    protected static function with_credentials(array $payload): array {
        foreach (['siteId', 'apiKey'] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                $payload[$field] = self::NO_CREDENTIALS;
            }
        }
        return $payload;
    }

    /**
     * What to tell the teacher about an own-AI error.
     *
     * @param \Throwable $e
     * @return string
     */
    public static function message(\Throwable $e): string {
        if ($e instanceof http_error) {
            return $e->getMessage();
        }
        if ($e instanceof \moodle_exception && $e->errorcode === 'byok_notavailable') {
            return get_string('byok_unavailable', 'mod_aiactivities');
        }
        return get_string(
            'byok_failed',
            'mod_aiactivities',
            \core_text::substr(clean_param($e->getMessage(), PARAM_TEXT), 0, 400)
        );
    }

    // Writing a set of activities.

    /**
     * Write a set of activities now (the generate route), on the site's own AI.
     *
     * Narration follows the voice setting: the site's own voice, or LMS Labs (its free narration route) when voice
     * stays on credits.
     *
     * @param array $payload The generate request the plugin would send to LMS Labs.
     * @return array The route's answer.
     */
    public static function generate(array $payload): array {
        self::hooks();
        $voice = !empty($payload['voiceoverEnabled']);
        $ownvoice = self::uses('voice');
        if ($voice && !$ownvoice) {
            $payload['voiceoverEnabled'] = false;
        }
        try {
            $result = engine::generate(self::with_credentials($payload));
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => self::message($e)];
        }
        if ($voice && !$ownvoice) {
            $result['activities'] = self::narrate_on_credits($result['activities'], $payload);
        }
        $result['byok'] = true;
        return $result;
    }

    /**
     * Narrate activities the site's own AI wrote on LMS Labs, for a site whose voice stays on credits.
     *
     * Uses the service's narration route with the plugin's credentials. When that is not possible the activities
     * are kept without narration: the teacher can narrate them later.
     *
     * @param array $activities
     * @param array $payload The generate request (language and voice).
     * @return array
     */
    protected static function narrate_on_credits(array $activities, array $payload): array {
        [$siteid, $apikey] = \aiactivities_get_credentials();
        if ($siteid === '' || $apikey === '') {
            return $activities;
        }
        $result = self::service('/api/aiactivities-regenerate-audio', [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'activities' => $activities,
            'voiceLanguage' => $payload['language'] ?? 'en-AU',
            'voiceId' => $payload['voiceId'] ?? 'Zephyr',
        ], 180);
        if (
            !empty($result['ok']) && !empty($result['activities']) && is_array($result['activities'])
                && count($result['activities']) === count($activities)
        ) {
            return $result['activities'];
        }
        return $activities;
    }

    /**
     * Narrate activities on the site's own voice (the regenerate-audio route), or null to use LMS Labs.
     *
     * @param array $payload The regenerate-audio request the plugin would send to LMS Labs.
     * @return array|null The route's answer.
     */
    public static function regenerate_audio(array $payload): ?array {
        if (!self::uses('voice')) {
            return null;
        }
        self::hooks();
        try {
            return engine::regenerate_audio(self::with_credentials($payload));
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => self::message($e)];
        }
    }

    /**
     * Prepare a request that goes to LMS Labs while narration runs on the site's own voice.
     *
     * The service then writes without narration (and charges no narration credits); the activities are narrated
     * here once they come back.
     *
     * @param array $payload The generate request.
     * @return array [payload to send, whether to narrate here afterwards]
     */
    public static function split_voice(array $payload): array {
        if (empty($payload['voiceoverEnabled']) || !self::uses('voice')) {
            return [$payload, false];
        }
        $payload['voiceoverEnabled'] = false;
        return [$payload, true];
    }

    /**
     * Narrate a set LMS Labs wrote, on the site's own voice.
     *
     * @param array $activities
     * @param array $payload The generate request (language and voice).
     * @return array
     */
    public static function narrate(array $activities, array $payload): array {
        self::hooks();
        $voiceid = $payload['voiceId'] ?? 'Zephyr';
        return engine::narrate($activities, $payload['language'] ?? 'en-AU', $voiceid === '' ? 'Zephyr' : $voiceid);
    }

    // Background generation: a job, run by a task, polled like the service's /api/jobs.

    /**
     * The cache that holds own-AI generation jobs.
     *
     * @return \cache
     */
    protected static function jobs(): \cache {
        return \cache::make('mod_aiactivities', 'byokjobs');
    }

    /**
     * Cache key for a job id.
     *
     * @param string $jobid
     * @return string
     */
    protected static function key(string $jobid): string {
        return 'j' . sha1($jobid);
    }

    /**
     * Whether a job id is one of this plugin's own-AI jobs.
     *
     * @param string $jobid
     * @return bool
     */
    public static function is_job(string $jobid): bool {
        return strpos($jobid, self::JOB_PREFIX) === 0;
    }

    /**
     * Start writing a set on the site's own AI in the background (the generate start route).
     *
     * @param array $payload The generate request the plugin would send to LMS Labs.
     * @param \context $context The activity.
     * @return array ok and jobId, as the service answers.
     */
    public static function start(array $payload, \context $context): array {
        global $USER;
        $jobid = self::JOB_PREFIX . bin2hex(random_bytes(16));
        self::jobs()->set(self::key($jobid), ['contextid' => $context->id, 'status' => 'pending']);
        $task = new task\byok_generate();
        $task->set_custom_data(['jobid' => $jobid, 'contextid' => $context->id, 'mode' => 'generate',
            'payload' => $payload]);
        $task->set_userid($USER->id);
        \core\task\manager::queue_adhoc_task($task);
        return ['ok' => true, 'jobId' => $jobid];
    }

    /**
     * Remember that a set LMS Labs is writing (without narration) is to be narrated on the site's own voice.
     *
     * @param string $jobid The service's job id.
     * @param array $payload The generate request (language and voice).
     * @param \context $context The activity.
     */
    public static function narrate_later(string $jobid, array $payload, \context $context): void {
        self::jobs()->set(self::key('narrate:' . $jobid), ['contextid' => $context->id,
            'language' => $payload['language'] ?? 'en-AU', 'voiceId' => $payload['voiceId'] ?? 'Zephyr']);
    }

    /**
     * Whether a service job's set is to be narrated here.
     *
     * @param string $jobid
     * @return bool
     */
    public static function narrates(string $jobid): bool {
        return self::jobs()->get(self::key('narrate:' . $jobid)) !== false;
    }

    /**
     * Answer a job poll as /api/jobs does, for own-AI jobs and for service jobs narrated here.
     *
     * @param string $jobid
     * @param \context $context The activity asking.
     * @param array|null $servicestatus The service's answer for its own job, when it has one.
     * @return array|null The poll answer, or null to pass the service's answer on unchanged.
     */
    public static function poll(string $jobid, \context $context, ?array $servicestatus = null): ?array {
        global $USER;
        $jobs = self::jobs();
        $job = $jobs->get(self::key($jobid));
        if ($job === false && $servicestatus !== null) {
            $narrate = $jobs->get(self::key('narrate:' . $jobid));
            if ($narrate === false || (int) $narrate['contextid'] !== (int) $context->id) {
                return null;
            }
            $result = $servicestatus['result'] ?? null;
            if (
                ($servicestatus['status'] ?? '') !== 'done' || empty($result['ok'])
                    || empty($result['activities']) || !is_array($result['activities'])
            ) {
                return null;
            }
            $job = ['contextid' => $context->id, 'status' => 'processing'];
            $jobs->set(self::key($jobid), $job);
            $jobs->delete(self::key('narrate:' . $jobid));
            $task = new task\byok_generate();
            $task->set_custom_data(['jobid' => $jobid, 'contextid' => $context->id, 'mode' => 'narrate',
                'result' => $result, 'payload' => ['language' => $narrate['language'], 'voiceId' => $narrate['voiceId']]]);
            $task->set_userid($USER->id);
            \core\task\manager::queue_adhoc_task($task);
        }
        if ($job === false) {
            return self::is_job($jobid) ? ['ok' => false, 'error' => 'Job not found or expired'] : null;
        }
        if ((int) $job['contextid'] !== (int) $context->id) {
            return ['ok' => false, 'error' => 'Job not found or expired'];
        }
        $answer = ['ok' => true, 'status' => $job['status']];
        if ($job['status'] === 'done') {
            $answer['result'] = $job['result'];
        } else if ($job['status'] === 'error') {
            $answer['error'] = $job['error'];
        }
        return $answer;
    }

    /**
     * Run a queued job (from the task): write the set, or narrate one LMS Labs wrote.
     *
     * @param array $data jobid, contextid, mode (generate|narrate), payload, and result for narrate.
     */
    public static function run_job(array $data): void {
        $jobs = self::jobs();
        $key = self::key($data['jobid']);
        $job = $jobs->get($key);
        if ($job === false) {
            return;
        }
        $jobs->set($key, ['status' => 'processing'] + $job);
        \core_php_time_limit::raise();
        if ($data['mode'] === 'narrate') {
            $result = $data['result'];
            try {
                $result['activities'] = self::narrate($result['activities'], $data['payload']);
            } catch (\Throwable $e) {
                // The set is kept without narration rather than lost: the teacher can narrate it later.
                debugging('Own-voice narration failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        } else {
            $result = self::generate($data['payload']);
        }
        // As on the service, a failed generation still completes the job: done, with ok false in the result.
        $jobs->set($key, ['status' => 'done', 'result' => $result] + $job);
    }

    /**
     * POST to the LMS Labs service.
     *
     * @param string $path
     * @param array $payload
     * @param int $timeout Seconds.
     * @return array|null The decoded answer, or null when there is none.
     */
    protected static function service(string $path, array $payload, int $timeout): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_FOLLOWLOCATION' => false,
        ]);
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post(\aiactivities_get_api_base() . $path, json_encode($payload));
        $decoded = json_decode((string) $response, true);
        return is_array($decoded) ? $decoded : null;
    }
}
