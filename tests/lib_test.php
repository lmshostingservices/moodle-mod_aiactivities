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
 * Tests for the mod_aiactivities library functions.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities;

/**
 * Tests for the library functions and helpers.
 *
 * @covers ::aiactivities_clamp_index
 * @covers ::aiactivities_get_js_strings
 * @covers ::aiactivities_delete_instance
 * @covers ::aiactivities_reset_userdata
 * @covers \mod_aiactivities\manifest_storage
 * @covers \mod_aiactivities\local\taxonomy
 * @covers \mod_aiactivities\local\activity_types
 * @covers \mod_aiactivities\local\attempt_progress
 * @covers \mod_aiactivities\local\manifest_edit
 */
final class lib_test extends \advanced_testcase {
    /**
     * Loads the library under test.
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/aiactivities/lib.php');
    }

    /**
     * The clamp keeps a value inside an attempt, and leaves it alone when the total is unknown.
     *
     * @return void
     */
    public function test_clamp_index(): void {
        $this->assertSame(3, aiactivities_clamp_index(3, 8));
        $this->assertSame(8, aiactivities_clamp_index(99, 8));
        $this->assertSame(0, aiactivities_clamp_index(-4, 8));
        // A total of zero means the attempt was created before the manifest existed.
        $this->assertSame(5, aiactivities_clamp_index(5, 0));
        $this->assertSame(0, aiactivities_clamp_index(-1, 0));
    }

    /**
     * Every string the player asks for exists in the language pack.
     *
     * @return void
     */
    public function test_js_strings_all_resolve(): void {
        $this->resetAfterTest();

        $strings = aiactivities_get_js_strings();
        $this->assertNotEmpty($strings);

        foreach ($strings as $key => $value) {
            $this->assertIsString($value, "String $key did not resolve");
            $this->assertStringNotContainsString('[[', $value, "String $key is missing from the language pack");
        }
    }

    /**
     * Students are not sent the strings only the generation interface uses.
     *
     * @return void
     */
    public function test_js_strings_exclude_teacher_strings(): void {
        $this->resetAfterTest();

        $student = aiactivities_get_js_strings(false);
        $teacher = aiactivities_get_js_strings(true);

        $this->assertArrayNotHasKey('js_edit_save', $student);
        $this->assertArrayHasKey('js_edit_save', $teacher);
        $this->assertArrayHasKey('feedback_correct', $student);
        $this->assertLessThan(count($teacher), count($student));
    }

    /**
     * The scenario vocabulary keeps canonical English values alongside translated labels.
     *
     * @return void
     */
    public function test_taxonomy_shape(): void {
        $this->resetAfterTest();

        $taxonomy = \mod_aiactivities\local\taxonomy::get_localised();

        $this->assertCount(52, $taxonomy);

        $values = [];
        foreach ($taxonomy as $industry) {
            $this->assertArrayHasKey('value', $industry);
            $this->assertNotSame('', $industry['value']);
            $this->assertNotEmpty($industry['sectors']);
            $values[] = $industry['value'];

            $sectorvalues = [];
            foreach ($industry['sectors'] as $sector) {
                $this->assertArrayHasKey('value', $sector);
                $this->assertNotSame('', $sector['value']);
                $sectorvalues[] = $sector['value'];
            }

            // Sector values must be unique inside an industry, or the sector
            // select would offer the same option twice.
            $this->assertSame(array_unique($sectorvalues), $sectorvalues);
        }

        $this->assertSame(array_unique($values), $values);
    }

    /**
     * A label is only sent when it differs from the canonical value.
     *
     * @return void
     */
    public function test_taxonomy_labels_only_when_translated(): void {
        $this->resetAfterTest();

        $taxonomy = \mod_aiactivities\local\taxonomy::get_localised();

        $sectors = [];
        foreach ($taxonomy as $industry) {
            $this->assertArrayNotHasKey('label', $industry);
            foreach ($industry['sectors'] as $sector) {
                $sectors[$sector['value']] = $sector;
            }
        }

        // These two are displayed under a friendlier name, but the generation
        // service must still receive the original value.
        $this->assertSame('Public vocational institutes (TAFE)', $sectors['TAFE']['label']);
        $this->assertSame('Camera monitoring', $sectors['CCTV & Monitoring']['label']);
    }

    /**
     * Employment services are offered under community services.
     *
     * @return void
     */
    public function test_taxonomy_includes_employment_services(): void {
        $this->resetAfterTest();

        $sectors = [];
        foreach (\mod_aiactivities\local\taxonomy::get_localised() as $industry) {
            if ($industry['value'] === 'Community Services') {
                $sectors = array_column($industry['sectors'], 'value');
            }
        }

        $this->assertContains('Employment Services', $sectors);
        $this->assertContains('Disability Employment Services', $sectors);
    }

    /**
     * Deleting an instance removes its attempts.
     *
     * @return void
     */
    public function test_delete_instance_removes_attempts(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $attempt = [
            'aiactivitiesid' => $instance->id,
            'userid' => $user->id,
            'progressjson' => '{}',
            'currentactivity' => 0,
            'completedcount' => 0,
            'totalcount' => 5,
            'score' => 0,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('aiactivities_attempts', (object)$attempt);

        $this->assertEquals(1, $DB->count_records('aiactivities_attempts', ['aiactivitiesid' => $instance->id]));

        aiactivities_delete_instance($instance->id);

        $this->assertEquals(0, $DB->count_records('aiactivities_attempts', ['aiactivitiesid' => $instance->id]));
        $this->assertEquals(0, $DB->count_records('aiactivities', ['id' => $instance->id]));
    }

    /**
     * Course reset deletes the attempts in that course only.
     *
     * @return void
     */
    public function test_reset_userdata_deletes_attempts(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $otherinstance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $othercourse->id]);
        $user = $this->getDataGenerator()->create_user();

        foreach ([$instance->id, $otherinstance->id] as $instanceid) {
            $attempt = [
                'aiactivitiesid' => $instanceid,
                'userid' => $user->id,
                'progressjson' => '{}',
                'currentactivity' => 0,
                'completedcount' => 0,
                'totalcount' => 5,
                'score' => 0,
                'status' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $DB->insert_record('aiactivities_attempts', (object)$attempt);
        }

        $resetdata = [
            'courseid' => $course->id,
            'reset_aiactivities_attempts' => 1,
        ];
        aiactivities_reset_userdata((object)$resetdata);

        $this->assertEquals(0, $DB->count_records('aiactivities_attempts', ['aiactivitiesid' => $instance->id]));
        $this->assertEquals(1, $DB->count_records('aiactivities_attempts', ['aiactivitiesid' => $otherinstance->id]));
    }

    /**
     * Large manifests round-trip through the compression helper.
     *
     * @return void
     */
    public function test_manifest_storage_roundtrip(): void {
        $small = json_encode(['activities' => 'small']);
        $this->assertSame($small, \mod_aiactivities\manifest_storage::compress($small));
        $this->assertSame($small, \mod_aiactivities\manifest_storage::decompress($small));

        $large = json_encode(['audio' => str_repeat('a', 600000)]);
        $stored = \mod_aiactivities\manifest_storage::compress($large);

        $this->assertStringStartsWith('gz:', $stored);
        $this->assertLessThan(strlen($large), strlen($stored));
        $this->assertSame($large, \mod_aiactivities\manifest_storage::decompress($stored));

        $this->assertNull(\mod_aiactivities\manifest_storage::compress(null));
        $this->assertNull(\mod_aiactivities\manifest_storage::decompress(null));
    }

    /**
     * An empty, malformed or complete type list all mean "every type".
     *
     * @return void
     */
    public function test_activity_types_parse_falls_back_to_all(): void {
        $all = \mod_aiactivities\local\activity_types::all();

        $this->assertCount(8, $all);
        $this->assertSame($all, \mod_aiactivities\local\activity_types::parse(''));
        $this->assertSame($all, \mod_aiactivities\local\activity_types::parse('   '));
        $this->assertSame($all, \mod_aiactivities\local\activity_types::parse('nonsense,alsonotatype'));
        $this->assertSame($all, \mod_aiactivities\local\activity_types::parse(\mod_aiactivities\local\activity_types::ALL));
        $this->assertSame($all, \mod_aiactivities\local\activity_types::parse(implode(',', $all)));
    }

    /**
     * A partial list keeps only known types, in the canonical order.
     *
     * @return void
     */
    public function test_activity_types_parse_cleans_the_list(): void {
        $parsed = \mod_aiactivities\local\activity_types::parse('flashcards, cardselect ,flashcards,bogus');

        // Canonical order, deduplicated, with the unknown entry dropped.
        $this->assertSame(['cardselect', 'flashcards'], $parsed);
        $this->assertTrue(\mod_aiactivities\local\activity_types::is_restricted($parsed));
    }

    /**
     * Only a genuine restriction is written to the instance.
     *
     * @return void
     */
    public function test_activity_types_stored_value(): void {
        $all = \mod_aiactivities\local\activity_types::all();

        $this->assertSame(
            \mod_aiactivities\local\activity_types::ALL,
            \mod_aiactivities\local\activity_types::to_stored_value($all)
        );
        $this->assertSame(
            \mod_aiactivities\local\activity_types::ALL,
            \mod_aiactivities\local\activity_types::to_stored_value([])
        );
        $this->assertSame(
            'cardselect,flashcards',
            \mod_aiactivities\local\activity_types::to_stored_value(['flashcards', 'cardselect'])
        );
    }

    /**
     * Filtering drops excluded types and keeps everything when nothing is excluded.
     *
     * @return void
     */
    public function test_activity_types_filter(): void {
        $activities = [
            ['type' => 'cardselect', 'title' => 'A'],
            ['type' => 'flashcards', 'title' => 'B'],
            ['type' => 'ordering', 'title' => 'C'],
        ];

        $all = \mod_aiactivities\local\activity_types::all();
        $this->assertSame($activities, \mod_aiactivities\local\activity_types::filter($activities, $all));

        $kept = \mod_aiactivities\local\activity_types::filter($activities, ['cardselect', 'ordering']);
        $this->assertSame(['A', 'C'], array_column($kept, 'title'));

        // Keys are re-indexed so the stored manifest stays a JSON array.
        $this->assertSame([0, 1], array_keys($kept));
    }

    /**
     * An activity of a type the plugin does not know is never discarded.
     *
     * @return void
     */
    public function test_activity_types_filter_keeps_unknown_types(): void {
        $activities = [
            ['type' => 'flashcards', 'title' => 'A'],
            ['type' => 'somethingnew', 'title' => 'B'],
            ['title' => 'C'],
        ];

        $kept = \mod_aiactivities\local\activity_types::filter($activities, ['cardselect']);

        $this->assertSame(['B', 'C'], array_column($kept, 'title'));
    }

    /**
     * The type labels all resolve in the language pack.
     *
     * @return void
     */
    public function test_activity_types_labels_resolve(): void {
        $this->resetAfterTest();

        foreach (\mod_aiactivities\local\activity_types::all() as $type) {
            $key = \mod_aiactivities\local\activity_types::label_key($type);
            $this->assertNotSame('', $key, "No label key for $type");
            $this->assertStringNotContainsString(
                '[[',
                get_string($key, 'mod_aiactivities'),
                "Label for $type is missing from the language pack"
            );
        }

        $this->assertSame('', \mod_aiactivities\local\activity_types::label_key('notatype'));
    }

    /**
     * A new instance starts with every type allowed.
     *
     * @return void
     */
    public function test_new_instance_allows_every_type(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $record = $DB->get_record('aiactivities', ['id' => $instance->id], '*', MUST_EXIST);

        $this->assertSame(\mod_aiactivities\local\activity_types::ALL, $record->activitytypes);
        $this->assertSame(
            \mod_aiactivities\local\activity_types::all(),
            \mod_aiactivities\local\activity_types::parse($record->activitytypes)
        );
    }

    /**
     * Creates an attempt with the given progress, for the remapping tests.
     *
     * @param int $instanceid The instance the attempt belongs to.
     * @param int $userid The student.
     * @param array $progress Position => true for each completed activity.
     * @param int $current The position the student is sitting on.
     * @param int $total The activity count at the time.
     * @param int $status 0 for in progress, 1 for completed.
     * @return int The new attempt id.
     */
    private function make_attempt(
        int $instanceid,
        int $userid,
        array $progress,
        int $current,
        int $total,
        int $status = 0
    ): int {
        global $DB;

        return $DB->insert_record('aiactivities_attempts', (object)[
            'aiactivitiesid' => $instanceid,
            'userid' => $userid,
            'progressjson' => json_encode((object)$progress),
            'currentactivity' => $current,
            'completedcount' => count(array_filter($progress)),
            'totalcount' => $total,
            'score' => 0,
            'status' => $status,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Removing an activity moves the later answers down with it.
     *
     * @return void
     */
    public function test_progress_shifts_after_removal(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        // Six activities; the student answered 0, 1, 2 and 4, and is sitting on 5.
        $attemptid = $this->make_attempt($instance->id, $user->id, [0 => true, 1 => true, 2 => true, 4 => true], 5, 6);

        $changed = \mod_aiactivities\local\attempt_progress::shift_after_removal($instance->id, 2, 5);
        $this->assertSame(1, $changed);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $progress = json_decode($attempt->progressjson, true);

        // The answer for the removed activity is gone, and what was 4 is now 3.
        $this->assertSame([0 => true, 1 => true, 3 => true], $progress);
        $this->assertEquals(4, $attempt->currentactivity);
        $this->assertEquals(3, $attempt->completedcount);
        $this->assertEquals(5, $attempt->totalcount);
    }

    /**
     * A student sitting on the removed activity stays in place, which is now
     * the activity that followed it.
     *
     * @return void
     */
    public function test_current_position_when_sitting_on_the_removed_activity(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $attemptid = $this->make_attempt($instance->id, $user->id, [0 => true, 1 => true], 2, 4);

        \mod_aiactivities\local\attempt_progress::shift_after_removal($instance->id, 2, 3);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $this->assertEquals(2, $attempt->currentactivity);
        $this->assertEquals(3, $attempt->totalcount);
    }

    /**
     * A student on the last activity of a set that is then removed is treated as
     * having reached the end, which is one past the last position, exactly as
     * aiactivities_clamp_index() allows elsewhere in the plugin.
     *
     * @return void
     */
    public function test_current_position_is_clamped_to_the_new_end(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $attemptid = $this->make_attempt($instance->id, $user->id, [0 => true, 1 => true], 2, 3);

        \mod_aiactivities\local\attempt_progress::shift_after_removal($instance->id, 2, 2);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $this->assertEquals(2, $attempt->currentactivity);
        $this->assertEquals(2, $attempt->completedcount);
        $this->assertEquals(2, $attempt->totalcount);
        // Never past one beyond the end, whatever the student was sitting on.
        $this->assertLessThanOrEqual((int)$attempt->totalcount, (int)$attempt->currentactivity);
    }

    /**
     * A finished attempt stays finished: it loses one answer and one activity.
     *
     * @return void
     */
    public function test_completed_attempt_stays_consistent(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $attemptid = $this->make_attempt(
            $instance->id,
            $user->id,
            [0 => true, 1 => true, 2 => true],
            2,
            3,
            1
        );

        // A finished attempt sits one past the last activity.
        $DB->set_field('aiactivities_attempts', 'currentactivity', 3, ['id' => $attemptid]);

        \mod_aiactivities\local\attempt_progress::shift_after_removal($instance->id, 1, 2);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        // The middle activity's answer is gone and the last one moved down.
        $this->assertSame([0 => true, 1 => true], json_decode($attempt->progressjson, true));
        // Still one past the end, so the attempt still reads as finished.
        $this->assertEquals(2, $attempt->currentactivity);
        $this->assertEquals(2, $attempt->completedcount);
        $this->assertEquals(2, $attempt->totalcount);
    }

    /**
     * Undoing a removal puts the later answers back where they were.
     *
     * @return void
     */
    public function test_progress_shifts_back_after_insertion(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $attemptid = $this->make_attempt($instance->id, $user->id, [0 => true, 1 => true, 3 => true], 4, 5);

        \mod_aiactivities\local\attempt_progress::shift_after_insertion($instance->id, 2, 6);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $progress = json_decode($attempt->progressjson, true);

        // Positions from 2 upwards moved up one; the restored activity itself
        // has no recorded answer.
        $this->assertSame([0 => true, 1 => true, 4 => true], $progress);
        $this->assertEquals(5, $attempt->currentactivity);
        $this->assertEquals(3, $attempt->completedcount);
        $this->assertEquals(6, $attempt->totalcount);
    }

    /**
     * Removing and putting back leaves the untouched answers exactly as they were.
     *
     * @return void
     */
    public function test_removal_then_insertion_round_trips(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        // Position 2 is the one being removed and put back. A student sitting
        // anywhere else comes back exactly where they were; a student who was
        // sitting on the removed activity has moved on to the next one and
        // stays there, because nothing in the stored position tells them apart
        // from a student who was already on it.
        $expected = [0 => 0, 1 => 1, 2 => 3, 3 => 3, 4 => 4];

        foreach ($expected as $current => $after) {
            $attemptid = $this->make_attempt($instance->id, $user->id, [0 => true, 1 => true, 3 => true], $current, 5);

            \mod_aiactivities\local\attempt_progress::shift_after_removal($instance->id, 2, 4);
            \mod_aiactivities\local\attempt_progress::shift_after_insertion($instance->id, 2, 5);

            $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);

            // The answers always come back exactly as they were.
            $this->assertSame(
                [0 => true, 1 => true, 3 => true],
                json_decode($attempt->progressjson, true),
                "Answers moved for a student sitting on $current"
            );
            $this->assertEquals($after, $attempt->currentactivity, "Wrong position for a student on $current");
            $this->assertEquals(5, $attempt->totalcount);

            $DB->delete_records('aiactivities_attempts', ['id' => $attemptid]);
        }
    }

    /**
     * Only the attempts of the instance being changed are touched.
     *
     * @return void
     */
    public function test_other_instances_are_left_alone(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $other = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $this->make_attempt($instance->id, $user->id, [0 => true, 1 => true], 1, 3);
        $otherid = $this->make_attempt($other->id, $user->id, [0 => true, 1 => true], 1, 3);

        $changed = \mod_aiactivities\local\attempt_progress::shift_after_removal($instance->id, 0, 2);
        $this->assertSame(1, $changed);

        $untouched = $DB->get_record('aiactivities_attempts', ['id' => $otherid], '*', MUST_EXIST);
        $this->assertSame([0 => true, 1 => true], json_decode($untouched->progressjson, true));
        $this->assertEquals(3, $untouched->totalcount);
    }

    /**
     * An attempt with no recorded progress survives the remapping.
     *
     * @return void
     */
    public function test_empty_progress_is_handled(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $attemptid = $this->make_attempt($instance->id, $user->id, [], 0, 3);

        \mod_aiactivities\local\attempt_progress::shift_after_removal($instance->id, 1, 2);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $this->assertSame([], json_decode($attempt->progressjson, true));
        $this->assertEquals(0, $attempt->currentactivity);
        $this->assertEquals(0, $attempt->completedcount);
        $this->assertEquals(2, $attempt->totalcount);
    }

    /**
     * Progress that cannot be read is replaced with an empty record rather than
     * left to misalign. This is deliberate: there is nothing to realign, and a
     * value that will not decode cannot be preserved meaningfully.
     *
     * @return void
     */
    public function test_unreadable_progress_is_reset(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aiactivities', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $attemptid = $this->make_attempt($instance->id, $user->id, [0 => true], 1, 3);
        $DB->set_field('aiactivities_attempts', 'progressjson', 'not json at all', ['id' => $attemptid]);

        \mod_aiactivities\local\attempt_progress::shift_after_removal($instance->id, 1, 2);

        $attempt = $DB->get_record('aiactivities_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $this->assertSame([], json_decode($attempt->progressjson, true));
        $this->assertEquals(0, $attempt->completedcount);
        $this->assertEquals(2, $attempt->totalcount);
    }

    /**
     * Every language string the player module asks for is actually delivered to it.
     *
     * The string map is maintained by hand, so a key used in JavaScript but left
     * out of the map would silently fall back to untranslated English. This reads
     * the module source and checks the two against each other.
     *
     * @return void
     */
    public function test_every_js_string_key_is_delivered(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents($CFG->dirroot . '/mod/aiactivities/amd/src/player.js');
        $this->assertNotEmpty($source);

        $matches = [];
        // Only keys written as a plain literal; the streak messages build their
        // key from a counter and are checked by test_js_strings_all_resolve.
        preg_match_all("/\\bstr(?:WithParam)?\\(\\s*'([a-z0-9_]+)'\\s*[,)]/i", $source, $matches);
        $used = array_unique($matches[1]);
        $this->assertNotEmpty($used);

        $delivered = aiactivities_get_js_string_map();

        $missing = [];
        foreach ($used as $key) {
            if (!array_key_exists($key, $delivered)) {
                $missing[] = $key;
            }
        }

        $this->assertSame([], $missing, 'Keys used by player.js but missing from the string map');
    }

    /**
     * An edit prepared against a different version of the list is refused.
     *
     * @return void
     */
    public function test_manifest_edit_detects_a_changed_list(): void {
        $activities = [
            ['type' => 'cardselect', 'title' => 'One'],
            ['type' => 'flashcards', 'title' => 'Two'],
            ['type' => 'ordering', 'title' => 'Three'],
        ];

        $matches = \mod_aiactivities\local\manifest_edit::still_matches($activities, 3, 1, 'flashcards', 'Two');
        $this->assertTrue($matches);

        // Someone else removed one first, so the positions have moved.
        $this->assertFalse(
            \mod_aiactivities\local\manifest_edit::still_matches($activities, 4, 1, 'flashcards', 'Two')
        );
        // The list is the right length but holds something else at that position.
        $this->assertFalse(
            \mod_aiactivities\local\manifest_edit::still_matches($activities, 3, 1, 'ordering', 'Three')
        );
        // A position that is not there at all.
        $this->assertFalse(
            \mod_aiactivities\local\manifest_edit::still_matches($activities, 3, 9, 'flashcards', 'Two')
        );

        $this->assertTrue(\mod_aiactivities\local\manifest_edit::still_has_count($activities, 3));
        $this->assertFalse(\mod_aiactivities\local\manifest_edit::still_has_count($activities, 2));
    }

    /**
     * A title holding characters that cleaning alters still matches itself.
     *
     * The values arrive cleaned as PARAM_TEXT, so a title the generation service
     * wrote with, say, an angle bracket must not make the edit unperformable.
     *
     * @return void
     */
    public function test_manifest_edit_compares_cleaned_values(): void {
        $this->resetAfterTest();

        $activities = [
            ['type' => 'cardselect', 'title' => 'Safety <signs> & symbols'],
        ];

        // What the browser sent and what the server cleaned it to.
        $sent = clean_param('Safety <signs> & symbols', PARAM_TEXT);

        $this->assertTrue(
            \mod_aiactivities\local\manifest_edit::still_matches($activities, 1, 0, 'cardselect', $sent)
        );
        $this->assertFalse(
            \mod_aiactivities\local\manifest_edit::still_matches($activities, 1, 0, 'cardselect', 'Something else')
        );
    }

    /**
     * Only something shaped like an activity may be written back into a set.
     *
     * @return void
     */
    public function test_manifest_edit_rejects_unusable_payloads(): void {
        $this->assertTrue(
            \mod_aiactivities\local\manifest_edit::is_storable(['type' => 'cardselect', 'title' => 'One'])
        );
        // A type this release does not know is still kept: it can only have come
        // from the generation service.
        $this->assertTrue(\mod_aiactivities\local\manifest_edit::is_storable(['type' => 'somethingnew']));

        $this->assertFalse(\mod_aiactivities\local\manifest_edit::is_storable([]));
        $this->assertFalse(\mod_aiactivities\local\manifest_edit::is_storable(['title' => 'No type']));
        $this->assertFalse(\mod_aiactivities\local\manifest_edit::is_storable(['type' => '   ']));
        $this->assertFalse(\mod_aiactivities\local\manifest_edit::is_storable(['type' => 42]));
        $this->assertFalse(\mod_aiactivities\local\manifest_edit::is_storable('not an array'));
        $this->assertFalse(\mod_aiactivities\local\manifest_edit::is_storable(null));
    }
}
