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
 * Test data generator for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates AI Learning Activities instances for tests.
 */
class mod_aiactivities_generator extends testing_module_generator {
    /**
     * Creates a new instance with sensible defaults.
     *
     * @param array|stdClass|null $record The instance data.
     * @param array|null $options Generator options.
     * @return stdClass The created instance record.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        $defaults = [
            'name' => 'AI Learning Activities',
            'intro' => 'Test instance',
            'introformat' => FORMAT_HTML,
            'language' => 'en-AU',
            'activitycount' => 5,
            'completionallcorrect' => 1,
            'voiceoverenabled' => 0,
            'voicegender' => 'female',
            'voiceid' => 'Zephyr',
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->{$field})) {
                $record->{$field} = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
    }
}
