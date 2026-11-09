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

namespace mod_aiactivities\aicoursebuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * AI Course Builder adapter for AI Learning Activities.
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class generator {
    private const COMPONENT = 'mod_aiactivities';
    private const ACTIVITY_COUNT = 5;

    public static function native_estimate(array $brief): int {
        return self::ACTIVITY_COUNT * 2;
    }

    public static function native_generate(int $cmid, array $brief): string {
        self::load_native_helpers();
        [$siteid, $apikey] = \aiactivities_get_credentials();
        if ($siteid === '' || $apikey === '') {
            throw new \moodle_exception('not_configured', 'mod_aiactivities');
        }
        $lesson = is_array($brief['lesson'] ?? null) ? $brief['lesson'] : [];
        $content = trim((string)($lesson['content'] ?? ''));
        if (strlen($content) < 50) {
            throw new \moodle_exception('error_no_content', 'mod_aiactivities');
        }
        $program = is_array($brief['program'] ?? null) ? $brief['program'] : [];
        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'content' => substr($content, 0, 50000),
            'language' => clean_param((string)($program['language'] ?? 'en-AU'), PARAM_TEXT),
            'activityCount' => self::ACTIVITY_COUNT,
            'route' => ($program['framework'] ?? 'vet') === 'vet' ? 'vet' : 'general',
            'voiceoverEnabled' => false,
        ];
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_FOLLOWLOCATION' => false,
        ]);
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post(
            \aiactivities_get_api_base() . '/api/generate-aiactivities/start',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
        $decoded = json_decode((string)$response, true);
        if ($curl->get_errno() || (int)($curl->get_info()['http_code'] ?? 0) !== 200
                || empty($decoded['ok']) || empty($decoded['jobId'])) {
            throw new \moodle_exception('generationfailed', 'local_aicoursebuilder', '', 'Learning Activities could not start generation.');
        }
        return clean_param((string)$decoded['jobId'], PARAM_ALPHANUMEXT);
    }

    public static function native_status(int $cmid, string $jobid): array {
        self::load_native_helpers();
        global $DB;
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 20,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_FOLLOWLOCATION' => false,
        ]);
        $url = \aiactivities_get_api_base() . '/api/jobs/' . rawurlencode($jobid);
        $response = $curl->get($url);
        $payload = json_decode((string)$response, true);
        if ($curl->get_errno() || (int)($curl->get_info()['http_code'] ?? 0) !== 200 || !is_array($payload)) {
            return ['status' => 'blocked', 'retryable' => false, 'error' => 'Learning Activities job status is unavailable.'];
        }
        if (($payload['status'] ?? '') === 'error') {
            return ['status' => 'failed', 'retryable' => false, 'error' => clean_param((string)($payload['error'] ?? 'Generation failed.'), PARAM_TEXT)];
        }
        if (($payload['status'] ?? '') !== 'done') {
            return ['status' => 'running', 'retryable' => false];
        }
        $activities = $payload['result']['activities'] ?? null;
        if (!is_array($activities) || !$activities) {
            return ['status' => 'failed', 'retryable' => false, 'error' => 'Learning Activities completed without activity data.'];
        }
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        $options = [
            'language' => clean_param((string)($instance->language ?: 'en-AU'), PARAM_TEXT),
            'activitycount' => count($activities),
        ];
        // Stored the same way the teacher flow stores a generated set (ajax.php
        // aiactivities_store_activities); the builder always asks for every type.
        $update = new \stdClass();
        $update->id = (int)$instance->id;
        $update->activitiesjson = \mod_aiactivities\manifest_storage::compress(json_encode($activities));
        $update->language = $options['language'];
        $update->activitycount = $options['activitycount'];
        $update->voiceoverenabled = 0;
        $update->activitytypes = \mod_aiactivities\local\activity_types::ALL;
        $update->timemodified = time();
        $DB->update_record('aiactivities', $update);
        return ['status' => 'complete', 'retryable' => false, 'result' => ['activitycount' => count($activities)]];
    }

    public static function native_save_result(int $cmid, array $result): void {
        global $DB;
        $cm = get_coursemodule_from_id('aiactivities', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);
        $activities = json_decode(\mod_aiactivities\manifest_storage::decompress($instance->activitiesjson) ?? '', true);
        if (!is_array($activities) || count($activities) !== (int)($result['activitycount'] ?? 0)) {
            throw new \moodle_exception('emptygeneratorresult', 'local_aicoursebuilder');
        }
    }

    private static function load_native_helpers(): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/mod/aiactivities/lib.php');
    }

    public static function is_available(): bool {
        return class_exists('\local_aicoursebuilder\local\generator_runtime')
            && \local_aicoursebuilder\local\generator_runtime::component_available(self::COMPONENT);
    }

    public static function estimate(array $brief): int {
        return \local_aicoursebuilder\local\generator_runtime::estimate(self::COMPONENT, $brief);
    }

    public static function generate(int $cmid, array $brief): string {
        return \local_aicoursebuilder\local\generator_runtime::generate(self::COMPONENT, $cmid, $brief);
    }

    public static function status(int $cmid, string $jobid): array {
        return \local_aicoursebuilder\local\generator_runtime::status(self::COMPONENT, $cmid, $jobid);
    }
}
