# Changelog - AI Learning Activities Module

All notable changes to this plugin will be documented in this file.

## [1.7.1] - 2026-08-28

Housekeeping only — no change to how the plugin behaves for teachers or students.

### Changed
- Request parameters that must stay raw (JSON payloads) now carry the release pipeline's
  annotation, and the three parameters that never needed `PARAM_RAW` use `PARAM_TEXT` directly.
- `index.php` checks `mod/aiactivities:view` before listing the activities in a course.
- Restored `thirdpartylibs.xml`, declaring that the plugin bundles no third-party libraries.
- Two scenario sector labels renamed for readability ("Vocational education and training",
  "Camera monitoring"). The values sent to the generation service are unchanged: the vocabulary
  now stores its canonical English value separately from its display label, so translating or
  customising a label can no longer change what the service receives.
- Multi-line calls now pass a named variable rather than an inline array literal, which reads
  better and satisfies the release pipeline's style rule.

## [1.7.0] - 2026-08-28

### Security
- Fixed a stored cross-site scripting flaw in the fill-in-the-blank activity, where the passage
  text was written to the page without escaping in both the player and review views.
- Escaped the remaining unescaped values reaching `innerHTML`: activity type, category item
  labels, feedback messages and language strings.
- The page no longer writes activity data or language strings into an inline `<script>` block.
  Configuration is passed to the AMD module through `js_call_amd`, and all inline JavaScript has
  been removed from the view page.
- `ajax.php` now resolves a course module, requires login and checks capabilities for every
  action, including `poll_job`, which previously checked only the session key.
- Requests to the generation service now verify TLS peers and certificates consistently and no
  longer follow redirects, so credentials cannot be forwarded to another host.
- Progress data submitted by the browser is validated as JSON, and the activity counters are
  clamped to the size of the attempt.

### Fixed
- Added the missing upgrade step for the `voiceoverenabled`, `voicegender` and `voiceid` columns
  introduced in 1.6.0. Sites upgrading from an earlier release no longer fail when narration
  settings are saved.
- The three narration columns are now included in backup and restore.
- Added the missing `invalidattempt` language string, which caused an exception without a message.
- Corrected the declared Moodle support: requires 4.4, supported 4.4 to 5.2.
- Removed a debugging `error_log()` call from the manifest storage helper.
- Fixed an undefined `$CFG` in the autoloaded external function class, and moved it to the
  `core_external` classes, which replaced the deprecated `external_api` ancestors.
- HTTP errors from the AJAX endpoint are now reported instead of being reduced to a parse error,
  and a failed progress save is surfaced to the student once rather than silently discarded.
- Job polling now stops after a bounded number of attempts instead of looping indefinitely.

### Changed
- All user-facing text now comes from the language pack, including the player, the teacher edit
  form, the generation form, the settings page and the industry vocabulary.
- The view page renders through Mustache templates and a plugin renderer.
- The player no longer rewrites Moodle's navigation links to `javascript:void(0)`; locked links
  are marked `aria-disabled` and restored on unlock.
- Browser dialogs (`alert`) replaced with `core/notification`.
- Course reset can now delete student attempts.
- Stylesheet custom properties are scoped to the plugin container instead of `:root`.

### Code quality
- The stylesheet moved from `styles/player.css` to `styles.css` at the plugin root, so Moodle loads
  and caches it with the rest of the theme CSS instead of it being requested separately.
- The plugin now passes Moodle's own linters cleanly: `phpcs` against both the `moodle` and
  `moodle-extra` standards, ESLint with Moodle's `.eslintrc`, and stylelint with Moodle's
  `.stylelintrc`. The remaining `!important` declarations carry a documented
  `stylelint-disable declaration-no-important` with the reason they exist.
- Added PHPUnit tests, a test generator and a `moodle-plugin-ci` workflow.

### Changed
- Narration is now controlled by a play / mute / stop icon group in the top right corner of the
  activity, replacing the single "Stop narration" text button. Each control has a tooltip and an
  accessible name, mute persists across activities, and play replays the current narration.

### Accessibility
- Every activity type is operable by keyboard: card select, both sort types, matching, ordering,
  flashcards and fill-in-the-blank now expose real controls and handle Enter and Space.
- Results are announced through a polite live region, and focus moves to the new activity on each
  render.
- Narration can be stopped, and a visible stop control appears while audio is playing.
- A `prefers-reduced-motion` block disables the looping animations and the confetti.
- Visible focus indicators added, and right-to-left overrides for the directional layouts.

## [1.6.0] - 2026-04-01
### Added
- **AI Voiceover Narration**: Teachers can now enable AI-generated spoken audio narration for each activity. When enabled, TTS audio is generated for every activity's question/title and auto-plays as students progress through activities.
- **Voice selection**: Choose from 8 AI voices (Aoede, Kore, Leda, Zephyr, Puck, Charon, Fenrir, Orus) with gender filter.
- **Regenerate Audio button**: Teachers with existing activities can regenerate audio for a new voice without regenerating all activities.
- **Credit cost update**: Voiceover adds +1 credit per activity (3 credits/activity with voiceover vs 2 credits/activity without). Cost preview dynamically updates as voiceover toggle is changed.
- New DB columns: `voiceoverenabled`, `voicegender`, `voiceid` added to `aiactivities` table.
- New server endpoint: `/api/aiactivities-regenerate-audio` for audio-only regeneration.
- New AJAX actions: `savevoicesettings`, `regenerateaudio`.

## [1.5.62] - 2026-03-28

### Fixed
- **COST-DISPLAY-STALE**: Clicking Regenerate restored the previous `activityCount` from sessionStorage but the total cost display still showed the stale value from the previous session. Fix: after restoring `savedCount` from sessionStorage, `player.js` now dispatches a `change` event on the savedCount input so the cost calculator re-fires and updates `totalCostEl` to match the restored count.

## [1.5.61] - 2026-03-27

### Fixed
- **BUG-AIA-REGEN-FORM** — Clicking Regenerate showed a completely blank generation form — all fields (content, language, activity count, scenario settings) were empty. After successful generation `player.js` now saves content, language, activity count, scenario-mode toggle, and scenario country/industry/sector to `sessionStorage` (keyed by `cmid`) before reloading. The regenerate button handler reads those keys and restores all values to their form controls before revealing the form, so teachers see their previous settings pre-populated.

## [1.5.60] - 2026-03-27

### Changed
- Version bump: clean release increment following master release process. No code or DB schema changes.
