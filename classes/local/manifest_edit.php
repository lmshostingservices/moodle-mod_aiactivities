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
 * Checks that an edit still applies to the list the teacher was looking at.
 *
 * Activities are addressed by position, so a request that was prepared against
 * one version of the list must not be applied to another: two quick clicks, two
 * browser tabs or two teachers would otherwise remove the wrong activity.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manifest_edit {
    /**
     * The most activities one set may hold.
     *
     * Generation is capped well below this; the limit exists so that no editing
     * path can grow a set without bound.
     */
    public const MAX_ACTIVITIES = 50;

    /**
     * Whether the stored list still matches the one the request was prepared against.
     *
     * The type and title arrive cleaned as PARAM_TEXT, so the stored values are
     * put through the same cleaning before they are compared: otherwise a title
     * holding a character that cleaning alters would never match itself and the
     * edit would be refused for no reason.
     *
     * @param array $activities The stored activities.
     * @param int $expectedcount The number of activities the teacher could see.
     * @param int $index The position being acted on.
     * @param string $type The type the teacher saw at that position.
     * @param string $title The title the teacher saw at that position.
     * @return bool True when the request still applies.
     */
    public static function still_matches(
        array $activities,
        int $expectedcount,
        int $index,
        string $type,
        string $title
    ): bool {
        if (count($activities) !== $expectedcount) {
            return false;
        }

        if (!isset($activities[$index]) || !is_array($activities[$index])) {
            return false;
        }

        $activity = $activities[$index];

        return self::comparable($activity['type'] ?? '') === self::comparable($type)
            && self::comparable($activity['title'] ?? '') === self::comparable($title);
    }

    /**
     * Normalises a value so both sides of a comparison have had the same treatment.
     *
     * @param mixed $value The value to normalise.
     * @return string The comparable form.
     */
    private static function comparable($value): string {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(clean_param((string)$value, PARAM_TEXT));
    }

    /**
     * Whether the stored list is still the length the request was prepared against.
     *
     * @param array $activities The stored activities.
     * @param int $expectedcount The number of activities the teacher could see.
     * @return bool True when the request still applies.
     */
    public static function still_has_count(array $activities, int $expectedcount): bool {
        return count($activities) === $expectedcount;
    }

    /**
     * Whether a decoded activity is shaped like something the player can render.
     *
     * The type is not checked against the known list: the generation service may
     * introduce a type this release does not know about yet, and refusing to put
     * such an activity back would lose it.
     *
     * @param mixed $activity The decoded activity.
     * @return bool True when it is safe to store.
     */
    public static function is_storable($activity): bool {
        if (!is_array($activity) || !$activity) {
            return false;
        }

        return isset($activity['type']) && is_string($activity['type']) && trim($activity['type']) !== '';
    }
}
