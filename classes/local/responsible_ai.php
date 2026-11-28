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
 * Responsible AI controls (EU AI Act alignment).
 *
 * Three site settings, all under "Responsible AI" on the plugin's settings page:
 * - requirereview: a newly generated set waits for a teacher to check it before learners see it (supports human
 *   oversight and the deployer's use of the content as intended).
 * - learnernotice: learners are told the activities were made with AI and who checked them (Article 50), and the
 *   page carries a machine-readable AI marker.
 * - paused: an administrator can stop all AI generation on the site at once. Existing activities keep working.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class responsible_ai {
    /** @var int The set waits for a teacher to check it. */
    public const STATUS_AWAITING_REVIEW = 0;

    /** @var int The set is released to learners. */
    public const STATUS_RELEASED = 1;

    /** IPTC digital source type for content created by a trained AI model. */
    public const DIGITAL_SOURCE_TYPE = 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';

    /**
     * Whether a newly generated set must be checked by a teacher before learners see it.
     *
     * @return bool
     */
    public static function requires_review(): bool {
        $value = get_config('mod_aiactivities', 'requirereview');
        return $value === false || $value === null || (string)$value !== '0';
    }

    /**
     * Whether learners are told that the activities were made with AI.
     *
     * @return bool
     */
    public static function learner_notice_enabled(): bool {
        $value = get_config('mod_aiactivities', 'learnernotice');
        return $value === false || $value === null || (string)$value !== '0';
    }

    /**
     * Whether an administrator has paused AI generation on this site.
     *
     * @return bool
     */
    public static function is_paused(): bool {
        return (string)get_config('mod_aiactivities', 'aipaused') === '1';
    }

    /**
     * Whether learners may see the instance's current set.
     *
     * @param \stdClass $instance The aiactivities record.
     * @return bool
     */
    public static function is_released(\stdClass $instance): bool {
        // Records without the field (rows read before the upgrade ran) were live already.
        return !isset($instance->reviewstatus) || (int)$instance->reviewstatus === self::STATUS_RELEASED;
    }

    /**
     * The review fields to write alongside a newly generated set.
     *
     * @param int|null $now Time of generation, for tests.
     * @return array Field => value.
     */
    public static function fields_for_new_set(?int $now = null): array {
        return [
            'reviewstatus' => self::requires_review() ? self::STATUS_AWAITING_REVIEW : self::STATUS_RELEASED,
            'reviewedby' => 0,
            'timereviewed' => 0,
            'timegenerated' => $now ?? time(),
        ];
    }

    /**
     * Applies the review fields for a newly generated set to an update record.
     *
     * @param \stdClass $update The record about to be written to the aiactivities table.
     * @return \stdClass The same record.
     */
    public static function apply_new_set(\stdClass $update): \stdClass {
        foreach (self::fields_for_new_set() as $field => $value) {
            $update->$field = $value;
        }
        return $update;
    }

    /**
     * Releases the instance's current set to learners, recording who checked it.
     *
     * @param \stdClass $instance The aiactivities record.
     * @param \stdClass $cm The course module.
     * @param \context_module $context The module context.
     * @param int $userid The teacher releasing it.
     * @return \stdClass The updated record.
     */
    public static function release(\stdClass $instance, \stdClass $cm, \context_module $context, int $userid): \stdClass {
        global $DB;

        $update = (object)[
            'id' => $instance->id,
            'reviewstatus' => self::STATUS_RELEASED,
            'reviewedby' => $userid,
            'timereviewed' => time(),
        ];
        $DB->update_record('aiactivities', $update);

        $event = \mod_aiactivities\event\activities_released::create([
            'objectid' => $instance->id,
            'context' => $context,
            'other' => ['activitycount' => self::count_activities($instance)],
        ]);
        $event->add_record_snapshot('course_modules', $cm);
        $event->trigger();

        foreach ((array)$update as $field => $value) {
            $instance->$field = $value;
        }
        return $instance;
    }

    /**
     * Records that a set was generated with AI, in the Moodle log.
     *
     * @param \stdClass $instance The aiactivities record.
     * @param \context_module $context The module context.
     * @param int $activitycount Activities in the new set.
     * @param string $engine 'lmslabs' or 'ownai' (Bring your own AI).
     * @param string $language Generation language code.
     * @param bool $narrated Whether narration was generated.
     * @return void
     */
    public static function log_generated(
        \stdClass $instance,
        \context_module $context,
        int $activitycount,
        string $engine,
        string $language,
        bool $narrated
    ): void {
        $event = \mod_aiactivities\event\activities_generated::create([
            'objectid' => $instance->id,
            'context' => $context,
            'other' => [
                'activitycount' => $activitycount,
                'engine' => $engine,
                'language' => $language,
                'narrated' => $narrated ? 1 : 0,
                'requiresreview' => self::requires_review() ? 1 : 0,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Counts the activities in the instance's stored set.
     *
     * @param \stdClass $instance The aiactivities record.
     * @return int
     */
    public static function count_activities(\stdClass $instance): int {
        if (empty($instance->activitiesjson)) {
            return 0;
        }
        $decoded = json_decode((string)\mod_aiactivities\manifest_storage::decompress($instance->activitiesjson), true);
        return is_array($decoded) ? count($decoded) : 0;
    }

    /**
     * Whether any activity in a set carries narration.
     *
     * @param array|null $activities The decoded set.
     * @return bool
     */
    public static function has_narration(?array $activities): bool {
        foreach ((array)$activities as $activity) {
            // Narration is stored as base64 audio on each activity (see player.js).
            if (is_array($activity) && !empty($activity['audioData'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * The line shown to teachers once a set is released.
     *
     * @param \stdClass $instance The aiactivities record.
     * @return string
     */
    public static function teacher_status_message(\stdClass $instance): string {
        if (!empty($instance->reviewedby)) {
            $user = \core_user::get_user((int)$instance->reviewedby);
            if ($user && empty($user->deleted)) {
                return get_string('review_released_by', 'mod_aiactivities', (object)[
                    'name' => fullname($user),
                    'date' => userdate((int)$instance->timereviewed, get_string('strftimedatetimeshort', 'langconfig')),
                ]);
            }
        }
        return get_string('review_released', 'mod_aiactivities');
    }

    /**
     * Template context for the AI notice and marker shown with the activities.
     *
     * @param \stdClass $instance The aiactivities record.
     * @param bool $narrated Whether the set carries AI narration.
     * @return array
     */
    public static function notice_context(\stdClass $instance, bool $narrated): array {
        $reviewer = '';
        if (!empty($instance->reviewedby)) {
            $user = \core_user::get_user((int)$instance->reviewedby);
            if ($user && empty($user->deleted)) {
                $reviewer = fullname($user);
            }
        }

        if ($reviewer !== '') {
            $message = get_string('ainotice_reviewed', 'mod_aiactivities', (object)[
                'name' => $reviewer,
                'date' => userdate((int)$instance->timereviewed, get_string('strftimedate', 'langconfig')),
            ]);
        } else {
            $message = get_string('ainotice_generic', 'mod_aiactivities');
        }

        $marker = [
            '@context' => 'https://schema.org',
            '@type' => 'LearningResource',
            'name' => format_string($instance->name),
            'creativeWorkStatus' => self::is_released($instance) ? 'Published' : 'Draft',
            'digitalSourceType' => self::DIGITAL_SOURCE_TYPE,
            'creator' => ['@type' => 'SoftwareApplication', 'name' => 'AI Learning Activities (LMS Labs)'],
        ];
        if (!empty($instance->timegenerated)) {
            $marker['dateCreated'] = date('c', (int)$instance->timegenerated);
        }
        if ($reviewer !== '') {
            $marker['contributor'] = ['@type' => 'Role', 'roleName' => 'Reviewer', 'name' => $reviewer];
        }

        return [
            'show' => self::learner_notice_enabled(),
            'message' => $message,
            'narrated' => $narrated,
            'reviewed' => $reviewer !== '',
            'marker' => json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ];
    }
}
