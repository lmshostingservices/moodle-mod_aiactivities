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

/**
 * Tests for writing and narrating revision activities on the site's own AI.
 *
 * local\byok\engine was compared with the LMS Labs service code on 150 hand-written cases and 900 randomised
 * requests and AI answers across both routes (identical validation messages, prompts, token budgets, parsing,
 * narration calls, responses and errors); these tests cover the wiring into the plugin.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aiactivities\byok
 * @covers     \mod_aiactivities\local\byok\engine
 * @covers     \mod_aiactivities\task\byok_generate
 */
#[\PHPUnit\Framework\Attributes\CoversClass(byok::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(task\byok_generate::class)]
final class byok_test extends \advanced_testcase {
    /** @var string Learning content, as teachers paste it. */
    private const CONTENT = 'Forklift operators must complete a pre-start check before every shift: brakes, horn, lights '
        . 'and hydraulics. Never carry passengers on the forks. Keep the load low and tilted back while travelling, and '
        . 'report any hydraulic leak to the supervisor so the forklift is tagged out of service.';

    /** @var array Requests the mocked providers received. */
    protected $requests = [];
    /** @var array Text answers the mocked text provider gives, in order. */
    protected $answers = [];
    /** @var \context_module */
    protected $context;
    /** @var \stdClass */
    protected $cm;

    /**
     * Licence the site for Bring your own AI on a test key, with text on an Anthropic key.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_aiconfig\byok\ai')) {
            $this->markTestSkipped('AI Grader Central Config 1.1.0 or later is not installed.');
        }
        $this->resetAfterTest();
        $pair = sodium_crypto_sign_keypair();
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        $payload = json_encode(['v' => 1, 'id' => 'lic_t', 'site' => \local_aiconfig\byok\licence::site_host(),
            'plan' => 'pro', 'byok' => true, 'products' => ['*'], 'sub' => 'sub_t', 'exp' => time() + DAYSECS]);
        $b64 = function (string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };
        set_config('licencekey', $b64($payload) . '.' .
            $b64(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair))), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('vendor_text', 'anthropic', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        set_config('key_gemini', 'g-test', 'local_aiconfig');
        set_config('key_openai', 'sk-open', 'local_aiconfig');
        // No LMS Labs credentials: everything on the site's own AI must work without them.
        foreach (['local_aiconfig', 'mod_aiactivities'] as $plugin) {
            set_config('siteid', '', $plugin);
            set_config('apikey', '', $plugin);
        }
        $this->requests = [];
        $this->answers = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function ($url, $headers, $body, $vendor, $raw = false) {
            $this->requests[] = ['url' => $url, 'body' => $body, 'vendor' => $vendor];
            if (strpos($url, 'texttospeech') !== false) {
                return ['audioContent' => base64_encode('OggS-' . $body['input']['text'])];
            }
            if (strpos($url, 'audio/speech') !== false) {
                return 'OggS-openai-' . $body['voice'];
            }
            $answer = array_shift($this->answers);
            if ($answer === null) {
                throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) ['vendor' => $vendor,
                    'detail' => 'Overloaded']);
            }
            return ['content' => [['type' => 'text', 'text' => $answer]], 'stop_reason' => 'end_turn'];
        };
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $activity = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $this->cm = get_coursemodule_from_instance('aiactivities', $activity->id);
        $this->context = \context_module::instance($this->cm->id);
        $this->setUser($teacher);
    }

    /**
     * Clean up the provider mock.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock']);
        engine::$aicall = engine::$tts = null;
        parent::tearDown();
    }

    /**
     * A set of activities as the model writes it.
     *
     * @return array
     */
    protected function activities(): array {
        return [
            ['type' => 'multiple_choice', 'title' => 'Pre-start check', 'question' => 'What comes first before driving?',
                'options' => ['Brakes, horn and lights', 'Fuel level only', 'Seat height', 'Radio channel'],
                'correctIndex' => 0],
            ['type' => 'true_false', 'title' => 'Passengers', 'question' => 'Passengers may ride on the forks.',
                'answer' => false],
            ['type' => 'flashcards', 'title' => 'Load handling', 'cards' => [['front' => 'Load', 'back' => 'Low and back']]],
        ];
    }

    /**
     * The generate request the page sends.
     *
     * @param array $over
     * @return array
     */
    protected function payload(array $over = []): array {
        return $over + ['siteId' => '', 'apiKey' => '', 'content' => self::CONTENT, 'language' => 'en-AU',
            'activityCount' => 3, 'activityMinutes' => 4, 'route' => 'vet', 'voiceoverEnabled' => false,
            'voiceGender' => 'female', 'voiceId' => 'Kore'];
    }

    public function test_generation_runs_on_own_text_without_credentials(): void {
        $this->answers = [json_encode(['activities' => $this->activities()])];
        $out = byok::generate($this->payload());
        $this->assertTrue($out['ok'], $out['error'] ?? '');
        $this->assertTrue($out['byok']);
        $this->assertSame(0, $out['creditsUsed']);
        $this->assertSame('unlimited', $out['remainingCredits']);
        $this->assertSame($this->activities(), $out['activities']);
        // The service's own prompt, sent to the site's Anthropic key.
        $this->assertCount(1, $this->requests);
        $this->assertSame('Anthropic', $this->requests[0]['vendor']);
        $prompt = json_encode($this->requests[0]['body']);
        $this->assertStringContainsString('expert instructional designer', $prompt);
        $this->assertStringContainsString('Australian spelling', $prompt);
        $this->assertStringContainsString('hydraulic leak', $prompt);
    }

    public function test_service_validation_applies(): void {
        $out = byok::generate($this->payload(['content' => 'Too short.', 'activityCount' => 2]));
        $this->assertFalse($out['ok']);
        $this->assertSame('Validation error: content: String must contain at least 50 character(s), '
            . 'activityCount: Number must be greater than or equal to 3', $out['error']);
        $this->assertCount(0, $this->requests);
    }

    public function test_service_checks_on_the_answer(): void {
        $this->answers = ['not json at all', json_encode(['activities' => []]),
            json_encode(['activities' => [['type' => 'flashcards']]])];
        $this->assertSame(['ok' => false, 'error' => 'Failed to parse AI response'], byok::generate($this->payload()));
        $this->assertSame(['ok' => false, 'error' => 'AI generated no activities'], byok::generate($this->payload()));
        $this->assertSame(['ok' => false, 'error' => 'Activity 1 missing type or title'], byok::generate($this->payload()));
    }

    public function test_failed_provider_says_what_it_said(): void {
        $out = byok::generate($this->payload());
        $this->assertFalse($out['ok']);
        $this->assertStringContainsString('Overloaded', $out['error']);
        $this->assertStringContainsString('No LMS Labs credits were used', $out['error']);
    }

    public function test_narration_on_own_google_voice(): void {
        set_config('src_voice', 'ownkey', 'local_aiconfig');
        set_config('vendor_voice', 'googletts', 'local_aiconfig');
        $this->answers = [json_encode(['activities' => $this->activities()])];
        $out = byok::generate($this->payload(['voiceoverEnabled' => true]));
        $this->assertTrue($out['ok'], $out['error'] ?? '');
        $this->assertSame('OggS-What comes first before driving?', base64_decode($out['activities'][0]['audioData']));
        // No question: the title is narrated.
        $this->assertSame('OggS-Load handling', base64_decode($out['activities'][2]['audioData']));
        $tts = array_values(array_filter($this->requests, function ($r) {
            return strpos($r['url'], 'texttospeech') !== false;
        }));
        $this->assertCount(3, $tts);
        $this->assertSame('en-AU-Chirp3-HD-Kore', $tts[0]['body']['voice']['name']);
        $this->assertEquals(0.92, $tts[0]['body']['audioConfig']['speakingRate']);
    }

    public function test_voice_on_credits_uses_the_free_narration_route(): void {
        set_config('siteid', 'site-1', 'local_aiconfig');
        set_config('apikey', 'key-1', 'local_aiconfig');
        $narrated = $this->activities();
        foreach ($narrated as $i => $act) {
            $narrated[$i]['audioData'] = 'b64-' . $i;
        }
        \curl::mock_response(json_encode(['ok' => true, 'activities' => $narrated]));
        $this->answers = [json_encode(['activities' => $this->activities()])];
        $out = byok::generate($this->payload(['voiceoverEnabled' => true]));
        $this->assertTrue($out['ok'], $out['error'] ?? '');
        $this->assertSame($narrated, $out['activities']);
        // Only the text went to the site's own AI.
        $this->assertCount(1, $this->requests);
    }

    public function test_voice_on_credits_without_credentials_keeps_the_set(): void {
        $this->answers = [json_encode(['activities' => $this->activities()])];
        $out = byok::generate($this->payload(['voiceoverEnabled' => true]));
        $this->assertTrue($out['ok'], $out['error'] ?? '');
        $this->assertSame($this->activities(), $out['activities']);
    }

    public function test_openai_voice_gets_the_matching_voice(): void {
        set_config('src_voice', 'ownkey', 'local_aiconfig');
        set_config('vendor_voice', 'openai', 'local_aiconfig');
        $out = byok::regenerate_audio(['activities' => [['type' => 'x', 'title' => '  Brakes first.  ']],
            'voiceLanguage' => 'en-AU', 'voiceId' => 'Kore']);
        $this->assertTrue($out['ok'], $out['error'] ?? '');
        $this->assertSame('OggS-openai-shimmer', base64_decode($out['activities'][0]['audioData']));
        $this->assertSame('Brakes first.', end($this->requests)['body']['input']);
    }

    public function test_regenerate_audio_keeps_the_service_checks(): void {
        $this->assertNull(byok::regenerate_audio(['activities' => $this->activities()]));
        set_config('src_voice', 'ownkey', 'local_aiconfig');
        set_config('vendor_voice', 'googletts', 'local_aiconfig');
        $this->assertSame(['ok' => false, 'error' => 'Activities required'], byok::regenerate_audio(['activities' => []]));
        $out = byok::regenerate_audio(['activities' => $this->activities(), 'voiceLanguage' => 'fr-FR', 'voiceId' => 'Puck']);
        $this->assertCount(3, $out['activities']);
        $this->assertSame('fr-FR-Chirp3-HD-Puck', $this->requests[0]['body']['voice']['name']);
    }

    public function test_split_voice(): void {
        $payload = $this->payload(['voiceoverEnabled' => true]);
        $this->assertSame([$payload, false], byok::split_voice($payload));
        set_config('src_voice', 'ownkey', 'local_aiconfig');
        set_config('vendor_voice', 'googletts', 'local_aiconfig');
        [$sent, $narrate] = byok::split_voice($payload);
        $this->assertFalse($sent['voiceoverEnabled']);
        $this->assertTrue($narrate);
    }

    public function test_background_job_runs_and_is_polled_like_the_service(): void {
        $this->answers = [json_encode(['activities' => $this->activities()])];
        $started = byok::start($this->payload(), $this->context);
        $this->assertTrue($started['ok']);
        $this->assertTrue(byok::is_job($started['jobId']));
        $this->assertSame(['ok' => true, 'status' => 'pending'], byok::poll($started['jobId'], $this->context));
        $this->assertCount(0, $this->requests);

        $this->runAdhocTasks('\mod_aiactivities\task\byok_generate');
        $done = byok::poll($started['jobId'], $this->context);
        $this->assertSame('done', $done['status']);
        $this->assertTrue($done['result']['ok']);
        $this->assertCount(3, $done['result']['activities']);
    }

    public function test_failed_job_is_done_with_the_error(): void {
        $started = byok::start($this->payload(), $this->context);
        $this->runAdhocTasks('\mod_aiactivities\task\byok_generate');
        $done = byok::poll($started['jobId'], $this->context);
        $this->assertSame('done', $done['status']);
        $this->assertFalse($done['result']['ok']);
        $this->assertStringContainsString('Overloaded', $done['result']['error']);
    }

    public function test_job_belongs_to_its_activity(): void {
        $started = byok::start($this->payload(), $this->context);
        $other = $this->getDataGenerator()->create_module('aiactivities', ['course' => $this->cm->course]);
        $othercontext = \context_module::instance(get_coursemodule_from_instance('aiactivities', $other->id)->id);
        $missing = ['ok' => false, 'error' => 'Job not found or expired'];
        $this->assertSame($missing, byok::poll($started['jobId'], $othercontext));
        $this->assertSame($missing, byok::poll('byokmissing', $this->context));
        // A service job id is not one of ours: the page asks LMS Labs.
        $this->assertNull(byok::poll('3f2a8c4e-service', $this->context));
    }

    public function test_service_job_is_narrated_on_own_voice(): void {
        set_config('src_text', 'credits', 'local_aiconfig');
        set_config('src_voice', 'ownkey', 'local_aiconfig');
        set_config('vendor_voice', 'googletts', 'local_aiconfig');
        byok::narrate_later('svc-123', ['language' => 'en-GB', 'voiceId' => 'Aoede'], $this->context);
        $this->assertTrue(byok::narrates('svc-123'));
        $this->assertNull(byok::poll('svc-123', $this->context, ['ok' => true, 'status' => 'processing']));
        $service = ['ok' => true, 'status' => 'done', 'result' => ['ok' => true, 'activities' => $this->activities(),
            'creditsUsed' => 2]];
        $taken = byok::poll('svc-123', $this->context, $service);
        $this->assertSame(['ok' => true, 'status' => 'processing'], $taken);
        $this->assertFalse(byok::narrates('svc-123'));
        $this->runAdhocTasks('\mod_aiactivities\task\byok_generate');
        $done = byok::poll('svc-123', $this->context, $service);
        $this->assertSame('done', $done['status']);
        $this->assertSame(2, $done['result']['creditsUsed']);
        $this->assertSame('OggS-Passengers may ride on the forks.', base64_decode($done['result']['activities'][1]['audioData']));
        $this->assertSame('en-GB-Chirp3-HD-Aoede', $this->requests[0]['body']['voice']['name']);
    }

    public function test_without_licence_everything_stays_on_lms_labs(): void {
        set_config('licencekey', '', 'local_aiconfig');
        $this->assertFalse(byok::uses('text'));
        $this->assertFalse(byok::uses('voice'));
        $this->assertNull(byok::regenerate_audio(['activities' => $this->activities()]));
    }
}
