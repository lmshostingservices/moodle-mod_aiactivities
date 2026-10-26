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
 * The activity types a set can be generated from.
 *
 * A teacher may restrict a set to a subset of these. The choice is stored on the
 * instance as a comma separated list, or as self::ALL when nothing is excluded;
 * storing "all" rather than the full list keeps existing instances correct when
 * a new type is added later.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_types {
    /**
     * The stored value meaning "every type", so the column never holds an empty
     * string and a set stays correct when a new type is added later.
     */
    public const ALL = 'all';

    /**
     * Every activity type the player can render, in the order shown to teachers.
     */
    private const TYPES = [
        'cardselect' => 'js_type_cardselect',
        'truefalseswipe' => 'js_type_truefalse',
        'fillinblank' => 'js_type_fillinblank',
        'matching' => 'js_type_matching',
        'ordering' => 'js_type_ordering',
        'columnsort' => 'js_type_columnsort',
        'categorysort' => 'js_type_categorysort',
        'flashcards' => 'js_type_flashcards',
    ];

    /**
     * All known activity type identifiers.
     *
     * @return string[] The identifiers, in display order.
     */
    public static function all(): array {
        return array_keys(self::TYPES);
    }

    /**
     * The language string key holding the label for one type.
     *
     * @param string $type An activity type identifier.
     * @return string The language string key, or an empty string for an unknown type.
     */
    public static function label_key(string $type): string {
        return self::TYPES[$type] ?? '';
    }

    /**
     * Turns a stored or submitted list into a clean list of known types.
     *
     * Unknown entries are dropped, duplicates are removed and the result is
     * returned in the canonical display order. An empty or fully inclusive list
     * means "every type", and comes back as every type.
     *
     * @param string $list A comma separated list of type identifiers.
     * @return string[] The selected types, never empty.
     */
    public static function parse(string $list): array {
        $wanted = array_filter(array_map('trim', explode(',', $list)));
        if (!$wanted) {
            return self::all();
        }

        $selected = [];
        foreach (self::all() as $type) {
            if (in_array($type, $wanted, true)) {
                $selected[] = $type;
            }
        }

        // A list naming nothing we recognise is treated the same as no list at
        // all, so a stale or malformed value can never leave a set ungeneratable.
        return $selected ?: self::all();
    }

    /**
     * Turns a list of types into the value stored on the instance.
     *
     * @param string[] $types The selected types.
     * @return string A comma separated list, or self::ALL when every type is selected.
     */
    public static function to_stored_value(array $types): string {
        $selected = self::parse(implode(',', $types));

        return count($selected) === count(self::all()) ? self::ALL : implode(',', $selected);
    }

    /**
     * Whether a restriction is in force.
     *
     * @param string[] $types The selected types.
     * @return bool True when at least one type is excluded.
     */
    public static function is_restricted(array $types): bool {
        return count($types) < count(self::all());
    }

    /**
     * Drops the activities whose type the teacher excluded.
     *
     * Activities carrying a type we do not know are kept: they can only have come
     * from the generation service, and discarding them would silently throw away
     * work the site has already paid for.
     *
     * @param array $activities The generated activities.
     * @param string[] $types The selected types.
     * @return array The activities to keep, re-indexed from zero.
     */
    public static function filter(array $activities, array $types): array {
        if (!self::is_restricted($types)) {
            return array_values($activities);
        }

        $kept = [];
        foreach ($activities as $activity) {
            $type = is_array($activity) ? ($activity['type'] ?? '') : '';
            if ($type === '' || !isset(self::TYPES[$type]) || in_array($type, $types, true)) {
                $kept[] = $activity;
            }
        }

        return $kept;
    }
}
