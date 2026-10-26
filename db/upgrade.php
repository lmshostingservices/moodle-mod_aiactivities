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
 * Upgrade steps for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the upgrade steps for this plugin.
 *
 * @param int $oldversion The version currently installed on the site.
 * @return bool True on success.
 */
function xmldb_aiactivities_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026072300) {
        upgrade_mod_savepoint(true, 2026072300, 'aiactivities');
    }

    if ($oldversion < 2026082800) {
        // The narration columns were introduced in release 1.6.0 without a
        // matching upgrade step, so sites upgrading from an earlier release
        // may still be missing them.
        $table = new xmldb_table('aiactivities');

        $field = new xmldb_field(
            'voiceoverenabled',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completionallcorrect'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'voicegender',
            XMLDB_TYPE_CHAR,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            'female',
            'voiceoverenabled'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'voiceid',
            XMLDB_TYPE_CHAR,
            '50',
            null,
            XMLDB_NOTNULL,
            null,
            'Zephyr',
            'voicegender'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        } else {
            // Release 1.6.5 changed the default voice, but the column default
            // was left at the previous value.
            $dbman->change_field_default($table, $field);
        }

        upgrade_mod_savepoint(true, 2026082800, 'aiactivities');
    }

    if ($oldversion < 2026102300) {
        // Release 1.10.0 lets a teacher restrict a set to chosen activity types.
        // Every existing set was generated from all of them.
        $table = new xmldb_table('aiactivities');
        $field = new xmldb_field(
            'activitytypes',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            XMLDB_NOTNULL,
            null,
            \mod_aiactivities\local\activity_types::ALL,
            'voiceid'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026102300, 'aiactivities');
    }

    return true;
}
