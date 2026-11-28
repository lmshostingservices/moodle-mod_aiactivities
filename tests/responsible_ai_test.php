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
 * Tests for the responsible AI controls (EU AI Act alignment).
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities;

use mod_aiactivities\local\responsible_ai;

/**
 * Tests for the review gate, the pause switch, the learner notice and its marker, the log events and the privacy API.
 *
 * @covers \mod_aiactivities\local\responsible_ai
 * @covers \mod_aiactivities\event\activities_generated
 * @covers \mod_aiactivities\event\activities_released
 * @covers \mod_aiactivities\event\activity_removed
 * @covers \mod_aiactivities\privacy\provider
 */
final class responsible_ai_test extends \advanced_testcase {
    /**
     * Creates a course with a teacher and an instance holding a stored set.
     *
     * @param array $record Extra instance fields.
     * @return array [course, instance, cm, context, teacher]
     */
    private function make_instance(array $record = []): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol(
            $course,
            'editingteacher',
            ['firstname' => 'Maya', 'lastname' => 'Chen']
        );
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id] + $record);
        $activities = [
            ['type' => 'cardselect', 'title' => 'Hazard controls'],
            ['type' => 'truefalseswipe', 'title' => 'PPE myths'],
        ];
        $DB->set_field(
            'aiactivities',
            'activitiesjson',
            manifest_storage::compress(json_encode($activities)),
            ['id' => $instance->id]
        );
        $instance = $DB->get_record('aiactivities', ['id' => $instance->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('aiactivities', $instance->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        return [$course, $instance, $cm, $context, $teacher];
    }

    /**
     * New installs and sets that existed before the upgrade are released; the defaults favour oversight.
     *
     * @return void
     */
    public function test_defaults(): void {
        $this->resetAfterTest();

        [, $instance] = $this->make_instance();
        $this->assertTrue(responsible_ai::is_released($instance), 'A set created outside the AI flow stays live.');
        $this->assertTrue(responsible_ai::is_released((object)['id' => 1]), 'A row read before the upgrade is live.');

        // Settings unset (as on an upgrade before admin/index.php writes defaults) mean the safe defaults.
        unset_config('requirereview', 'mod_aiactivities');
        unset_config('learnernotice', 'mod_aiactivities');
        unset_config('aipaused', 'mod_aiactivities');
        $this->assertTrue(responsible_ai::requires_review());
        $this->assertTrue(responsible_ai::learner_notice_enabled());
        $this->assertFalse(responsible_ai::is_paused());
    }

    /**
     * A newly generated set waits for review when the site asks for it, and goes straight out when it does not.
     *
     * @return void
     */
    public function test_new_set_fields(): void {
        $this->resetAfterTest();

        set_config('requirereview', 1, 'mod_aiactivities');
        $fields = responsible_ai::fields_for_new_set(1700000000);
        $this->assertSame(responsible_ai::STATUS_AWAITING_REVIEW, $fields['reviewstatus']);
        $this->assertSame(0, $fields['reviewedby']);
        $this->assertSame(0, $fields['timereviewed']);
        $this->assertSame(1700000000, $fields['timegenerated']);

        set_config('requirereview', 0, 'mod_aiactivities');
        $update = responsible_ai::apply_new_set((object)['id' => 5]);
        $this->assertSame(responsible_ai::STATUS_RELEASED, $update->reviewstatus);
        $this->assertSame(0, $update->reviewedby, 'An automatic release names nobody as the checker.');
    }

    /**
     * Regenerating a released set takes it away from learners again until it is checked.
     *
     * @return void
     */
    public function test_regeneration_resets_release(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('requirereview', 1, 'mod_aiactivities');

        [, $instance, $cm, $context, $teacher] = $this->make_instance();
        $instance = responsible_ai::release($instance, $cm, $context, (int)$teacher->id);
        $this->assertTrue(responsible_ai::is_released($instance));

        $update = responsible_ai::apply_new_set((object)['id' => $instance->id]);
        $DB->update_record('aiactivities', $update);
        $instance = $DB->get_record('aiactivities', ['id' => $instance->id]);
        $this->assertFalse(responsible_ai::is_released($instance));
        $this->assertEquals(0, $instance->reviewedby);
    }

    /**
     * Releasing records who checked the set and when, and logs it.
     *
     * @return void
     */
    public function test_release_records_reviewer_and_logs(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('requirereview', 1, 'mod_aiactivities');

        [, $instance, $cm, $context, $teacher] = $this->make_instance();
        $DB->update_record('aiactivities', responsible_ai::apply_new_set((object)['id' => $instance->id]));
        $instance = $DB->get_record('aiactivities', ['id' => $instance->id]);
        $this->assertFalse(responsible_ai::is_released($instance));

        $sink = $this->redirectEvents();
        $before = time();
        $instance = responsible_ai::release($instance, $cm, $context, (int)$teacher->id);
        $events = $sink->get_events();
        $sink->close();

        $stored = $DB->get_record('aiactivities', ['id' => $instance->id]);
        $this->assertEquals(responsible_ai::STATUS_RELEASED, $stored->reviewstatus);
        $this->assertEquals($teacher->id, $stored->reviewedby);
        $this->assertGreaterThanOrEqual($before, (int)$stored->timereviewed);

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(\mod_aiactivities\event\activities_released::class, $event);
        $this->assertSame(2, $event->other['activitycount']);
        $this->assertEquals($context, $event->get_context());
        $this->assertStringContainsString('released 2 AI-generated activities', $event->get_description());
    }

    /**
     * The generation event carries what is needed to reconstruct how a set was made.
     *
     * @return void
     */
    public function test_generated_and_removed_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $instance, , $context] = $this->make_instance();
        $sink = $this->redirectEvents();
        responsible_ai::log_generated($instance, $context, 8, 'ownai', 'fr-FR', true);
        \mod_aiactivities\event\activity_removed::create([
            'objectid' => $instance->id,
            'context' => $context,
            'other' => ['activitycount' => 7, 'position' => 3],
        ])->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(2, $events);
        [$generated, $removed] = array_values($events);
        $this->assertInstanceOf(\mod_aiactivities\event\activities_generated::class, $generated);
        $this->assertSame('ownai', $generated->other['engine']);
        $this->assertSame('fr-FR', $generated->other['language']);
        $this->assertSame(1, $generated->other['narrated']);
        $this->assertSame('c', $generated->crud);
        $this->assertStringContainsString('(engine: ownai)', $generated->get_description());
        $this->assertInstanceOf(\mod_aiactivities\event\activity_removed::class, $removed);
        $this->assertStringContainsString('removed activity 3, leaving 7', $removed->get_description());
        $this->assertNotEmpty(\mod_aiactivities\event\activities_generated::get_name());
    }

    /**
     * The learner notice names the checker, and the machine-readable marker declares AI-created content.
     *
     * @return void
     */
    public function test_notice_and_marker(): void {
        $this->resetAfterTest();

        [, $instance, $cm, $context, $teacher] = $this->make_instance(['name' => 'WHS induction practice']);
        $notice = responsible_ai::notice_context($instance, false);
        $this->assertTrue($notice['show']);
        $this->assertFalse($notice['reviewed']);
        $this->assertSame(get_string('ainotice_generic', 'mod_aiactivities'), $notice['message']);

        $instance = responsible_ai::release($instance, $cm, $context, (int)$teacher->id);
        $notice = responsible_ai::notice_context($instance, true);
        $this->assertTrue($notice['reviewed']);
        $this->assertTrue($notice['narrated']);
        $this->assertStringContainsString('Maya Chen', $notice['message']);

        $marker = json_decode($notice['marker'], true);
        $this->assertSame(responsible_ai::DIGITAL_SOURCE_TYPE, $marker['digitalSourceType']);
        $this->assertSame('LearningResource', $marker['@type']);
        $this->assertSame('Published', $marker['creativeWorkStatus']);
        $this->assertSame('Maya Chen', $marker['contributor']['name']);
        $this->assertStringNotContainsString('</', $notice['marker'], 'The marker cannot close its script element.');

        // The visible notice can be turned off; the marker never is.
        set_config('learnernotice', 0, 'mod_aiactivities');
        $notice = responsible_ai::notice_context($instance, false);
        $this->assertFalse($notice['show']);
        $this->assertNotEmpty(json_decode($notice['marker'], true));
    }

    /**
     * The pause switch reads the admin setting.
     *
     * @return void
     */
    public function test_pause_switch(): void {
        $this->resetAfterTest();
        set_config('aipaused', 1, 'mod_aiactivities');
        $this->assertTrue(responsible_ai::is_paused());
        set_config('aipaused', 0, 'mod_aiactivities');
        $this->assertFalse(responsible_ai::is_paused());
    }

    /**
     * Narration is detected from the stored audio on any activity.
     *
     * @return void
     */
    public function test_has_narration(): void {
        $this->assertFalse(responsible_ai::has_narration(null));
        $this->assertFalse(responsible_ai::has_narration([['type' => 'cardselect'], 'junk']));
        $this->assertTrue(responsible_ai::has_narration([['type' => 'cardselect'], ['audioData' => 'UklGRg==']]));
    }

    /**
     * The teacher who released a set is found, exported and removed by the privacy API, and the set stays live.
     *
     * @return void
     */
    public function test_privacy_reviewer(): void {
        global $DB;
        $this->resetAfterTest();

        [, $instance, $cm, $context, $teacher] = $this->make_instance();
        responsible_ai::release($instance, $cm, $context, (int)$teacher->id);

        $contexts = \mod_aiactivities\privacy\provider::get_contexts_for_userid((int)$teacher->id);
        $this->assertContains((int)$context->id, array_map('intval', $contexts->get_contextids()));

        $userlist = new \core_privacy\local\request\userlist($context, 'mod_aiactivities');
        \mod_aiactivities\privacy\provider::get_users_in_context($userlist);
        $this->assertContains((int)$teacher->id, array_map('intval', $userlist->get_userids()));

        $approved = new \core_privacy\local\request\approved_contextlist($teacher, 'mod_aiactivities', [$context->id]);
        \mod_aiactivities\privacy\provider::export_user_data($approved);
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        \mod_aiactivities\privacy\provider::delete_data_for_user($approved);
        $stored = $DB->get_record('aiactivities', ['id' => $instance->id]);
        $this->assertEquals(0, $stored->reviewedby);
        $this->assertEquals(responsible_ai::STATUS_RELEASED, $stored->reviewstatus, 'The set stays available.');
    }

    /**
     * Every responsible AI setting and string exists.
     *
     * @return void
     */
    public function test_strings_exist(): void {
        $keys = ['responsibleai_heading', 'requirereview', 'learnernotice', 'aipaused', 'ainotice_reviewed',
            'ainotice_generic', 'ainotice_voice', 'awaiting_review_learner', 'error_ai_paused', 'review_ack',
            'literacy_title', 'event_activities_generated', 'event_activities_released', 'event_activity_removed'];
        foreach ($keys as $key) {
            $this->assertTrue(get_string_manager()->string_exists($key, 'mod_aiactivities'), $key);
        }
        foreach (\mod_aiactivities\local\activity_types::all() as $type) {
            $this->assertTrue(get_string_manager()->string_exists('typedesc_' . $type, 'mod_aiactivities'), $type);
        }
    }
}
