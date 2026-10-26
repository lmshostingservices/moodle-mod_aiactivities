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
}
