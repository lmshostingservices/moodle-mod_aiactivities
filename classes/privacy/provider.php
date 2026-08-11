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
 * mod_aiactivities file.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace mod_aiactivities\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('aiactivities_attempts', [
            'userid' => 'privacy:metadata:attempts:userid',
            'progressjson' => 'privacy:metadata:attempts:progressjson',
            'score' => 'privacy:metadata:attempts:score',
            'timecreated' => 'privacy:metadata:attempts:timecreated',
        ], 'privacy:metadata:attempts');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {aiactivities} a ON a.id = cm.instance
                  JOIN {aiactivities_attempts} aa ON aa.aiactivitiesid = a.id
                 WHERE aa.userid = :userid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'aiactivities',
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT aa.userid
                  FROM {aiactivities_attempts} aa
                  JOIN {aiactivities} a ON a.id = aa.aiactivitiesid
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";
        $params = ['modname' => 'aiactivities', 'cmid' => $context->instanceid];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('aiactivities', $context->instanceid);
        if ($cm) {
            $DB->delete_records('aiactivities_attempts', ['aiactivitiesid' => $cm->instance]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('aiactivities', $context->instanceid);
            if ($cm) {
                $DB->delete_records('aiactivities_attempts', [
                    'aiactivitiesid' => $cm->instance,
                    'userid' => $userid,
                ]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('aiactivities', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $inparams['aiactivitiesid'] = $cm->instance;
        $DB->delete_records_select('aiactivities_attempts',
            "aiactivitiesid = :aiactivitiesid AND userid $insql", $inparams);
    }
}
