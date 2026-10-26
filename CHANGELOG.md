# Changelog - AI Learning Activities Module

All notable changes to this plugin will be documented in this file.

## [1.11.1] - 2026-10-26

### Fixed
- The two fields that carry the state of the list an edit was prepared against are read as
  PARAM_TEXT rather than PARAM_RAW_TRIMMED, which the release pipeline flags. Both sides of the
  comparison are now put through the same cleaning, so an activity title holding a character that
  cleaning alters still matches itself and the edit is not refused for no reason.

## [1.11.0] - 2026-10-26

### Added
- **Teachers can remove a single activity from a generated set.** Each row in the activity list now
  carries a remove button. It asks for confirmation, naming the activity, and once it has gone an
  undo offer stays on screen for eight seconds.
- Saved student progress is realigned when an activity is removed, so recorded answers stay attached
  to the activity they belong to rather than shifting onto the wrong one. The answer for the removed
  activity is dropped and the attempt total is corrected.
- The last remaining activity in a set cannot be removed.

### Changed
- An edit now carries the state of the list it was prepared against, and is refused if the list has
  moved on. Two quick clicks, two browser tabs or two teachers working at once can no longer remove
  the wrong activity.
- Removing or restoring an activity rewrites the set and the attempts in a single transaction, so a
  failure part way through cannot leave the two out of step.
- Saved progress is bounded to the set as it now stands, so a page a student left open before an
  edit can no longer report answers for activities that are no longer there.

### Notes
- A student sitting on the exact activity that was removed moves on to the one that followed it. If
  the removal is then undone they stay on that following activity rather than going back, because
  their stored position no longer distinguishes them from a student who was already there. Everyone
  else comes back exactly where they were.
- Removing a second activity replaces the undo offer for the first, which cannot then be recovered.

## [1.10.0] - 2026-10-23

### Added
- **Teachers can choose which activity types to generate.** The generation form now carries a
  checkbox for each of the eight types, all ticked by default. Untick flashcards, or anything else,
  and the set is built without it. A "Select all" link puts them back.
- The choice is saved on the activity, so regenerating keeps the same types and the teacher sees
  their last selection when they return.
- The chosen types are sent to the generation service as `activityTypes`, and are also applied when
  the result comes back, so the choice is honoured even against a service that does not yet read
  that field.
- Generating with no type ticked is refused before any request is made, so no credits are spent on
  a set that could not be built.

### Changed
- New `activitytypes` column on the activity table, holding `all` or a comma separated list. It is
  included in backups, and a backup taken before this release restores as `all`.

### Notes
- If the service returns only types the teacher excluded, every activity is kept rather than
  discarded, and the teacher is told the choice could not be matched. Work that has been paid for
  is never thrown away.

## [1.9.1] - 2026-09-09

### Fixed
- **The player would not start on a site with developer debugging turned on.** The page data
  (language strings, scenario vocabulary and the activity manifest) was passed as an argument to
  `js_call_amd()`, which Moodle limits to 1024 characters and warns about beyond that. The data is
  now written into the page as a JSON block and read by the module, so the player starts cleanly
  whatever the debugging level.

### Changed
- The bundled JavaScript in `amd/build` is now produced by Moodle's own Grunt build, so the module
  carries its name in the AMD `define()` call as core expects when it serves every module in a
  single request.

## [1.9.0] - 2026-09-09

### Added
- **A Next button between activities.** The player no longer moves on by itself after a correct
  answer. Feedback stays on screen and a "Next activity" button appears (labelled "Finish" on the
  last one), so students set their own pace. The button takes keyboard focus when it appears.
- **Streak rewards.** Activities answered correctly first go are counted as a streak. A chip in the
  player header shows the running count from two upwards and bumps each time it grows, and a
  celebration popup appears at 4, 8, 12 and 16 in a row. The popup carries its own rising chime
  and confetti, dismisses itself after 2.6 seconds, and also closes on click or Escape.
- A wrong answer anywhere in an activity ends the streak and stops that activity counting towards
  it, so the streak only ever reflects first-time-correct answers. Flashcards, which have no answer
  that can be wrong, are neutral: they neither extend nor break a streak.
- A completed activity is now locked until the student moves on, so it cannot be answered a second
  time and cannot lose a streak that was already earned.
- Completion confetti is now withheld whenever the student got anything wrong, in any activity
  type. Previously only the true or false activity recorded that.

### Changed
- Moving to the next activity is now immediate once the student clicks, rather than after the
  800ms pause the automatic advance used.
- Feedback stays on screen until the student moves on, instead of fading after 1.5 seconds.

### Notes
- A streak lives in the browser for the duration of the attempt. Reloading the page mid-attempt
  resets it to zero; the activities already completed are still recorded as normal.

## [1.8.0] - 2026-09-09

### Added
- **Many more industries and sectors for scenario mode.** The vocabulary grew from 29 industries
  and 292 sectors to 52 industries and 704 sectors.
- 23 new industries: Arts & Culture, Beauty & Personal Care, Cleaning & Facility Services,
  Defence, Emergency Services, Energy & Renewables, Environment & Sustainability,
  Events & Entertainment, Fishing & Forestry, Funeral Services, Horticulture & Landscaping,
  Insurance, Legal, Marine & Maritime, Media & Communications, Pharmacy & Life Sciences,
  Property & Real Estate, Rail, Research & Science, Telecommunications,
  "Textiles, Clothing & Footwear", Veterinary & Animal Care, and Wholesale & Distribution.
- 196 further sectors across the 28 existing industries that gained entries. Community Services
  in particular gains Employment Services, Disability Employment Services, Support Coordination
  (NDIS), Child Protection, Foster & Out-of-Home Care, Financial Counselling, Crisis Support &
  Helplines, Social Housing Support, Settlement Services, Community Development, Advocacy
  Services, Volunteer Coordination, Correctional & Justice Support and Community Legal Support.

### Changed
- The vocabulary is now cached for the duration of a request rather than resolving every language
  string each time it is read.
- The TAFE sector is shown as "Public vocational institutes (TAFE)", which no longer reads as a
  duplicate of "Vocational Education (VET)" in the same list. The value sent to the generation
  service is unchanged.
- The API URL setting description named a different product; it now describes this plugin's
  service.

### Removed
- Seven language strings nothing referenced, and the unminified copy of the player in `amd/build/`,
  which Moodle never loads (it serves `amd/src` in developer mode and `amd/build/player.min.js`
  otherwise).

### Notes
- Every existing industry and sector keeps its name, its position in the list and its language
  string key, so activities generated before this release are unaffected and any saved scenario
  selection still resolves.

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
