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
 * Renderer for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities\output;

use plugin_renderer_base;

/**
 * Renders the teacher generation interface and the student player.
 */
class renderer extends plugin_renderer_base {
    /** @var int The fewest activities the generation service produces. */
    private const MIN_ACTIVITIES = 5;

    /** @var int The most activities the generation service produces. */
    private const MAX_ACTIVITIES = 20;

    /**
     * Voice identifiers offered for narration, keyed by voice gender.
     *
     * @var array[]
     */
    private const VOICES = [
        'female' => ['Zephyr', 'Aoede', 'Kore', 'Leda'],
        'male' => ['Puck', 'Charon', 'Fenrir', 'Orus'],
    ];

    /**
     * Language codes offered for generation, grouped for the select element.
     *
     * @var array[]
     */
    private const LANGUAGES = [
        'langgroup_english' => ['en-AU', 'en-GB', 'en-US', 'en-IN'],
        'langgroup_european' => ['es-ES', 'fr-FR', 'de-DE', 'it-IT', 'pt-BR', 'nl-NL', 'pl-PL', 'tr-TR'],
        'langgroup_nordic' => ['sv-SE', 'da-DK', 'nb-NO', 'fi-FI'],
        'langgroup_asian' => ['zh-CN', 'ja-JP', 'ko-KR', 'hi-IN', 'th-TH', 'vi-VN', 'id-ID', 'ms-MY', 'tl-PH'],
        'langgroup_other' => ['ar-SA'],
    ];

    /**
     * Worker levels offered in scenario mode. The value is sent to the
     * generation service and is deliberately not translated; the label is.
     *
     * @var string[]
     */
    private const WORKER_LEVELS = ['Worker', 'Supervisor', 'Manager', 'Executive'];

    /**
     * Renders the generation and management interface shown to teachers.
     *
     * @param \stdClass $instance The aiactivities instance record.
     * @param bool $hasactivities Whether activities have already been generated.
     * @return string HTML.
     */
    public function render_teacher_interface(\stdClass $instance, bool $hasactivities): string {
        $context = [
            'hasactivities' => $hasactivities,
            'voiceoverenabled' => !empty($instance->voiceoverenabled),
            'languages' => $this->language_groups($instance->language ?? 'en-AU'),
            'activitycounts' => $this->activity_count_options((int)($instance->activitycount ?? 8)),
            'voices' => $this->voice_options($instance->voiceid ?? 'Zephyr'),
            'voicegenders' => $this->voice_gender_options($instance->voicegender ?? 'female'),
            'workerlevels' => $this->worker_level_options(),
            'activitytypes' => $this->activity_type_options(
                $instance->activitytypes ?? \mod_aiactivities\local\activity_types::ALL
            ),
        ];

        return $this->render_from_template('mod_aiactivities/teacher_interface', $context);
    }

    /**
     * Builds the activity type checkboxes for the generation form.
     *
     * @param string $stored The stored comma separated list, or the all sentinel.
     * @return array[] Template context for the checkbox list.
     */
    private function activity_type_options(string $stored): array {
        $selected = \mod_aiactivities\local\activity_types::parse($stored);

        $options = [];
        foreach (\mod_aiactivities\local\activity_types::all() as $type) {
            $options[] = [
                'value' => $type,
                'label' => get_string(\mod_aiactivities\local\activity_types::label_key($type), 'mod_aiactivities'),
                'checked' => in_array($type, $selected, true),
            ];
        }

        return $options;
    }

    /**
     * Renders the activity player shown to students.
     *
     * @return string HTML.
     */
    public function render_player(): string {
        return $this->render_from_template('mod_aiactivities/player', []);
    }

    /**
     * Builds the grouped language options for the generation form.
     *
     * @param string $selected The language code to preselect.
     * @return array[] Template context for the language select.
     */
    private function language_groups(string $selected): array {
        $groups = [];
        foreach (self::LANGUAGES as $groupkey => $codes) {
            $options = [];
            foreach ($codes as $code) {
                $stringkey = 'lang_' . str_replace('-', '_', strtolower($code));
                $options[] = [
                    'value' => $code,
                    'label' => get_string($stringkey, 'mod_aiactivities'),
                    'selected' => ($code === $selected),
                ];
            }
            $groups[] = [
                'label' => get_string($groupkey, 'mod_aiactivities'),
                'options' => $options,
            ];
        }
        return $groups;
    }

    /**
     * Builds the "number of activities" options.
     *
     * The service generates a minimum of five, so smaller counts are not offered.
     *
     * @param int $selected The count to preselect.
     * @return array[] Template context for the activity count select.
     */
    private function activity_count_options(int $selected): array {
        $selected = ($selected >= self::MIN_ACTIVITIES && $selected <= self::MAX_ACTIVITIES) ? $selected : 8;

        $options = [];
        for ($i = self::MIN_ACTIVITIES; $i <= self::MAX_ACTIVITIES; $i++) {
            $options[] = [
                'value' => $i,
                'label' => get_string('activitycount_many', 'mod_aiactivities', $i),
                'selected' => ($i === $selected),
            ];
        }
        return $options;
    }

    /**
     * Builds the narration voice options.
     *
     * @param string $selected The voice identifier to preselect.
     * @return array[] Template context for the voice select.
     */
    private function voice_options(string $selected): array {
        $options = [];
        foreach (self::VOICES as $gender => $voices) {
            $stringkey = ($gender === 'male') ? 'voice_option_male' : 'voice_option_female';
            foreach ($voices as $voice) {
                $options[] = [
                    'value' => $voice,
                    'label' => get_string($stringkey, 'mod_aiactivities', $voice),
                    'selected' => ($voice === $selected),
                ];
            }
        }
        return $options;
    }

    /**
     * Builds the voice gender options.
     *
     * @param string $selected The gender to preselect.
     * @return array[] Template context for the voice gender select.
     */
    private function voice_gender_options(string $selected): array {
        $options = [];
        foreach (['female', 'male'] as $gender) {
            $options[] = [
                'value' => $gender,
                'label' => get_string('voice_gender_' . $gender, 'mod_aiactivities'),
                'selected' => ($gender === $selected),
            ];
        }
        return $options;
    }

    /**
     * Builds the worker level pill options used in scenario mode.
     *
     * @return array[] Template context for the worker level pills.
     */
    private function worker_level_options(): array {
        $levels = [];
        foreach (self::WORKER_LEVELS as $level) {
            $levels[] = [
                'value' => $level,
                'label' => get_string('level_' . strtolower($level), 'mod_aiactivities'),
            ];
        }
        return $levels;
    }
}
