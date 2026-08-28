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
 * Privacy provider for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Describes and serves the personal data stored by this activity module.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the data this plugin stores and the external service it sends content to.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $attemptfields = [
            'userid' => 'privacy:metadata:attempts:userid',
            'progressjson' => 'privacy:metadata:attempts:progressjson',
            'currentactivity' => 'privacy:metadata:attempts:currentactivity',
            'completedcount' => 'privacy:metadata:attempts:completedcount',
            'score' => 'privacy:metadata:attempts:score',
            'status' => 'privacy:metadata:attempts:status',
            'timecreated' => 'privacy:metadata:attempts:timecreated',
            'timemodified' => 'privacy:metadata:attempts:timemodified',
        ];
        $collection->add_database_table('aiactivities_attempts', $attemptfields, 'privacy:metadata:attempts');

        // Teachers send learning content to the configured generation service so
        // that activities can be produced from it. No student data is sent.
        $servicefields = [
            'siteid' => 'privacy:metadata:service:siteid',
            'content' => 'privacy:metadata:service:content',
            'language' => 'privacy:metadata:service:language',
            'scenario' => 'privacy:metadata:service:scenario',
            'voice' => 'privacy:metadata:service:voice',
        ];
        $collection->add_external_location_link('aiactivities_service', $servicefields, 'privacy:metadata:service');

        return $collection;
    }

    /**
     * Returns the contexts that contain data for the given user.
     *
     * @param int $userid The user to search for.
     * @return contextlist The contexts containing data for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {aiactivities} a ON a.id = cm.instance
                  JOIN {aiactivities_attempts} aa ON aa.aiactivitiesid = a.id
                 WHERE aa.userid = :userid";

        $sqlparams = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'aiactivities',
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $sqlparams);

        return $contextlist;
    }

    /**
     * Returns the users who have data in the given context.
     *
     * @param userlist $userlist The userlist to add users to.
     * @return void
     */
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

        $userlist->add_from_sql('userid', $sql, ['modname' => 'aiactivities', 'cmid' => $context->instanceid]);
    }

    /**
     * Exports the attempt data held for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('aiactivities', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $conditions = [
                'aiactivitiesid' => $cm->instance,
                'userid' => $user->id,
            ];
            $attempts = $DB->get_records('aiactivities_attempts', $conditions, 'timecreated ASC');

            if (empty($attempts)) {
                continue;
            }

            $exported = [];
            foreach ($attempts as $attempt) {
                $exported[] = (object)[
                    'currentactivity' => $attempt->currentactivity,
                    'completedcount' => $attempt->completedcount,
                    'totalcount' => $attempt->totalcount,
                    'score' => $attempt->score,
                    'status' => $attempt->status ? get_string('status_completed', 'mod_aiactivities')
                        : get_string('status_inprogress', 'mod_aiactivities'),
                    'progress' => json_decode($attempt->progressjson, true) ?: [],
                    'timecreated' => $attempt->timecreated ? transform::datetime($attempt->timecreated) : null,
                    'timemodified' => $attempt->timemodified ? transform::datetime($attempt->timemodified) : null,
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:path:attempts', 'mod_aiactivities')],
                (object)['attempts' => $exported]
            );
        }
    }

    /**
     * Deletes all attempt data for every user in the given context.
     *
     * @param \context $context The context to delete data for.
     * @return void
     */
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

    /**
     * Deletes all attempt data for one user in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user to delete data for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('aiactivities', $context->instanceid);
            if ($cm) {
                $conditions = [
                    'aiactivitiesid' => $cm->instance,
                    'userid' => $userid,
                ];
                $DB->delete_records('aiactivities_attempts', $conditions);
            }
        }
    }

    /**
     * Deletes attempt data for several users in one context.
     *
     * @param approved_userlist $userlist The approved context and users to delete data for.
     * @return void
     */
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

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $inparams['aiactivitiesid'] = $cm->instance;

        $DB->delete_records_select(
            'aiactivities_attempts',
            "aiactivitiesid = :aiactivitiesid AND userid $insql",
            $inparams
        );
    }
}
