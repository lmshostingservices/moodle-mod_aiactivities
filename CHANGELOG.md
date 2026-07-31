# Changelog - AI Learning Activities Module

All notable changes to this plugin will be documented in this file.

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
