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

namespace mod_aiactivities\local;

/**
 * Keeps saved student progress aligned when the activity list changes.
 *
 * Progress is recorded against activity positions, so removing an activity from
 * the middle of a set would otherwise leave every later answer attached to the
 * wrong activity. These helpers shift the recorded positions to match.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_progress {
    /**
     * Realigns every attempt after an activity has been removed from the set.
     *
     * The answer recorded for the removed activity is dropped, later answers
     * move down one place, and the attempt total is brought into line with the
     * new activity count.
     *
     * @param int $instanceid The aiactivities instance id.
     * @param int $removedindex The zero based position that was removed.
     * @param int $newtotal The number of activities now in the set.
     * @return int The number of attempts that were changed.
     */
    public static function shift_after_removal(int $instanceid, int $removedindex, int $newtotal): int {
        return self::apply($instanceid, $newtotal, function (array $progress) use ($removedindex): array {
            $shifted = [];
            foreach ($progress as $position => $done) {
                $position = (int)$position;
                if ($position === $removedindex) {
                    continue;
                }
                $shifted[$position > $removedindex ? $position - 1 : $position] = $done;
            }

            return $shifted;
        }, function (int $current) use ($removedindex): int {
            // A student sitting on the removed activity stays where they are,
            // which is now the activity that followed it.
            return $current > $removedindex ? $current - 1 : $current;
        });
    }

    /**
     * Realigns every attempt after an activity has been put back into the set.
     *
     * This is the undo of shift_after_removal(). The restored activity itself
     * has no recorded answer: the one it had before the removal was discarded
     * with it, so a student who had already completed it meets it again.
     *
     * @param int $instanceid The aiactivities instance id.
     * @param int $insertedindex The zero based position that was inserted.
     * @param int $newtotal The number of activities now in the set.
     * @return int The number of attempts that were changed.
     */
    public static function shift_after_insertion(int $instanceid, int $insertedindex, int $newtotal): int {
        return self::apply($instanceid, $newtotal, function (array $progress) use ($insertedindex): array {
            $shifted = [];
            foreach ($progress as $position => $done) {
                $position = (int)$position;
                $shifted[$position >= $insertedindex ? $position + 1 : $position] = $done;
            }

            return $shifted;
        }, function (int $current) use ($insertedindex): int {
            // Every student stays on the activity they were on, which is what
            // keeps an undo faithful for everyone who was displaced by the
            // removal. The one exception is a student who was sitting on the
            // activity that was removed: they moved on to the one after it, and
            // nothing in the stored position distinguishes them from a student
            // who was already there, so they stay on that following activity
            // rather than going back to the restored one.
            return $current >= $insertedindex ? $current + 1 : $current;
        });
    }

    /**
     * Rewrites every attempt of one instance with the given mapping.
     *
     * @param int $instanceid The aiactivities instance id.
     * @param int $newtotal The number of activities now in the set.
     * @param callable $mapprogress Takes the decoded progress map, returns the new one.
     * @param callable $mapcurrent Takes the current position, returns the new one.
     * @return int The number of attempts that were changed.
     */
    private static function apply(int $instanceid, int $newtotal, callable $mapprogress, callable $mapcurrent): int {
        global $DB;

        $attempts = $DB->get_records('aiactivities_attempts', ['aiactivitiesid' => $instanceid]);
        if (!$attempts) {
            return 0;
        }

        $now = time();
        $changed = 0;

        foreach ($attempts as $attempt) {
            $progress = json_decode((string)$attempt->progressjson, true);
            if (!is_array($progress)) {
                $progress = [];
            }

            $shifted = $mapprogress($progress);
            ksort($shifted);

            $completed = 0;
            foreach ($shifted as $done) {
                if ($done === true) {
                    $completed++;
                }
            }

            $attempt->progressjson = json_encode((object)$shifted);
            // A finished attempt sits one past the last activity, which is what
            // aiactivities_clamp_index() allows, so the same bound is used here.
            $attempt->currentactivity = max(0, min($mapcurrent((int)$attempt->currentactivity), $newtotal));
            $attempt->completedcount = min($completed, $newtotal);
            $attempt->totalcount = $newtotal;
            $attempt->timemodified = $now;

            $DB->update_record('aiactivities_attempts', $attempt);
            $changed++;
        }

        return $changed;
    }
}
