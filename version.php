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
 * v1.5.61: BUG FIX — Regenerate button now pre-populates the generation form from the
 *   last-used settings stored in sessionStorage. After generation, player.js saves
 * v1.5.62: BUG FIX — Regenerate click now dispatches a change event on the savedCount input
 *   after restoring from sessionStorage. Without this the totalCostEl cost display still
 *   showed the stale value from the previous session rather than the restored count.
 *   version.php → 2026032801562.
 *
 * v1.5.61: BUG FIX — Regenerate button pre-populates generation form from sessionStorage.
 *   content, language, activity count, and scenario-mode fields (country, industry, sector)
 *   to sessionStorage before reloading. The regenerate handler reads all of these keys
 *   and restores them to the form controls before revealing the form, so teachers no
 *   longer see a blank form when regenerating. version.php → 2026032701561.
 *
 * v1.5.60: VERSION BUMP: Clean release following master release process. version.php → 2026032701560.
 *   before the page reloads and restored on load, so teachers no longer lose their
 *   custom instructions when regenerating AI Learning Activities.
 *   version.php → 2026032701559.
 *
 * v1.5.58: VERSION-NUMBER FIX — v1.5.57 was stamped with numeric version 2026032501557
 *   (March 25) but v1.5.56 was already stamped 2026032601556 (March 26). Moodle compares
 *   the full integer so 2026032501557 < 2026032601556 — Moodle rejected the install with
 *   "A higher version of this plugin is already installed". No functional changes.
 *   Bumped to 2026032601558 (same day prefix as v1.5.56, higher build suffix).
 *   version.php → 2026032601558.
 *
 * v1.5.57: FIX-ASYNC-DB-SAVE — Generated activities never appeared after async
 *   generation completed. Root cause: the async flow stored the AI result only in
 *   the Express job store — nothing ever wrote to aiactivities.activitiesjson. Fix:
 *   new 'save_generated' AJAX action performs the identical DB write. player.js
 *   doPoll() calls save_generated with the polled activities before reloading.
 *   version.php → 2026032501557.
 *
 * v1.5.56: FIX-UNKNOWN-ACTION — ajax.php used PARAM_ALPHA to validate the 'action'
 *   parameter. PARAM_ALPHA only allows letters a–z, stripping underscores. So
 *   'generate_async' was received as 'generateasync' and 'poll_job' as 'polljob' —
 *   neither matched any switch case, causing Moodle to show "Unknown action" every
 *   time a student or teacher clicked Generate. Changed to PARAM_ALPHANUMEXT which
 *   allows letters, digits, underscores, hyphens, and dots. All existing action
 *   strings in the switch (getcredits, generate, generate_async, poll_job,
 *   startattempt, saveprogress, complete, saveactivity) are unaffected except
 *   generate_async and poll_job which now route correctly. version.php → 2026032601556.
 *
 * v1.5.54: FIX-DOUBLE-CLICK-SKIP — During a second attempt (Practice Again), clicking
 *   Done twice in quick succession on the same activity caused it to skip the following
 *   activity. Root cause: advanceToNext() increments currentIndex immediately but delays
 *   renderActivity() by 800ms. A second Done click during that window saw the already-
 *   incremented currentIndex, marked the unseen next activity as complete (progress[X]=true)
 *   and scheduled a second renderActivity(), jumping past an activity entirely.
 *   Fix: Added 'advancing' boolean flag (var advancing = false). advanceToNext() returns
 *   immediately if advancing is already true. advancing is set true on entry and cleared
 *   in the setTimeout callback just before renderActivity() fires, in the completeAllActivities()
 *   path, and reset in practiceAgain() so every new attempt starts ungated. version.php → 2026032501554.
 *
 * v1.5.53: VERSION BUMP — Maintenance release. version.php → 2026032501553.
 *
 * v1.5.52: CRITICAL FIX — Fatal "Table aiactivities_items does not exist" (ddltablenotexist) when deleting any course that contains an AI Learning Activities instance. Root cause: aiactivities_delete_instance() in lib.php called $DB->delete_records('aiactivities_items', ...) but that table was never defined in install.xml and never created. Activity data has always been stored as JSON in the activitiesjson column of the aiactivities table — there is no separate items table. Removed the orphaned delete_records() call. version.php → 2026032401552.
 *
 * AI Learning Activities v1.5.40 - ETA Recalibrate
 *
 * v1.5.40: ETA RECALIBRATE — Increased estimated completion time formula to realistic
 *           values. True/False: 20s→45s/stmt, Card Select: 10s→30s/card, Flashcards:
 *           15s→40s/card, Matching: 15s→40s/pair, Ordering/Column Sort: 12s→35s/item,
 *           Category Sort: 12s→35s/item, Fill in Blank: 15s→40s/item, default: 45s→90s.
 *
 * v1.5.48: INDUSTRY UNIFICATION — Industry SELECT now uses the same 29-industry list as Content Creator (hardcoded, no server round-trip). New sector SELECT auto-populates. Sector sent as scenariosector param. version.php → 2026032401548.
 *
 * v1.5.39: VERSION BUMP — Maintenance release.
 *
 * v1.5.38: ETA BANNERS — Added "Estimated Time to Complete" banners to both teacher
 *           management view (after activities are generated) and student player view.
 *           Clock icon + gradient banner with dark mode support.
 *
 * v1.5.37: FEEDBACK CENTERING FIX — All 8 activity types: Moodle Boost theme overrides
 *           display/text-align/width on feedback elements, defeating CSS centering. Fix:
 *           added !important to display, align-items, justify-content, width, text-align,
 *           float, and box-sizing on .ala-tfs-mid-result, .ala-tfs-result-icon,
 *           .ala-tfs-result-status, .ala-tfs-explanation, .ala-tfs-explanation-answer,
 *           .ala-tfs-explanation-text, .ala-tfs-explanation p, .ala-tfs-statement,
 *           .ala-cardselect-inline-result, .ala-player-feedback, and .ala-feedback-content.
 *           Covers True/False inline result (Correct!/Incorrect!), Card Select inline result,
 *           and the floating feedback toast used by Column Sort, Category Sort, Matching,
 *           Ordering, and Fill in the Blank.
 *
 * v1.5.36: PREMIUM CSS MAKEOVER — Per-activity accent color system (8 unique colors: blue,
 *           emerald, amber, purple, rose, cyan, indigo, orange). CSS ::before type badges for
 *           all 8 activity types. Player progress bar with shimmer gradient animation + glow.
 *           Content area colored top-border accent stripe per activity type. Premium hover states
 *           with colored glow shadows using CSS custom properties. Floating animation on current
 *           items. Category sort buckets with unique color-coded headers (4 colors). Matching/
 *           ordering items use activity accent for selected/hover states. True/False card gets
 *           accent top border. Fill-in-blank selected blanks pulse with accent glow. Glass morphism
 *           feedback toasts with backdrop blur. Enhanced completion screen with animated gradient
 *           background, radial glow, and spring physics icon entrance. Flashcard progress dots
 *           with accent glow. Full Moodle dark mode support (body[data-bs-theme="dark"] + body.dark)
 *           covering all activities, forms, pills, buckets, and feedback. 499 new CSS lines.
 *
 * v1.5.25: CATEGORYSORT EDIT MODAL FIX — Edit modal showed "Item 1 (belongs to: )" blank
 *           for any item with category index 0, because escapeHtml(0) returns '' (0 is falsy).
 *           Items 2+ showed raw numeric indices instead of category names. Fix: resolve numeric
 *           category index to the category name string before displaying; string categories
 *           displayed directly. Works for both legacy numeric format and new string format.
 *
 * AI Learning Activities v1.5.24 - Category Sort Critical Fix
 *
 * v1.5.24: CATEGORYSORT CRITICAL FIX — AI generates item.category as a numeric index (0-3)
 *           but handleCategoryChoice compared it to a category name string (always false).
 *           Fix: accept both numeric index and string name in play mode. Same dual-check
 *           applied in review mode renderReviewCategorySort. Server-side validator updated
 *           to pass string-category items. Both prompt blocks updated to generate string
 *           category names for all new activities (legacy numeric format still accepted).
 *
 * AI Learning Activities v1.5.23 - True/False Feedback Position Fix
 *
 * v1.5.23: ALA TF FEEDBACK POSITION FIX — ala-tfs-mid-result div was injected between the
 *           two TF buttons inside ala-tfs-buttons-result; moved to render inside the card
 *           above the explanation div. The buttons row is now a clean 2-button layout with
 *           correct/incorrect feedback appearing at card level, not nested inside button group.
 *
 * AI Learning Activities v1.5.27 - Multi-select job levels and job roles
 *
 * v1.5.27: MULTI-SELECT JOB LEVELS + JOB ROLES — Replaced single job-level text input with
 * multi-select pill buttons (4 levels) and added job roles chips input (up to 5).
 * Sends scenarioworkerlevel and scenariojobroles to AI. ajax.php updated with new params.
 *
 * AI Learning Activities v1.5.22 - Release checklist compliance + True/False grid centering
 *
 * v1.5.10: VERSION REBUILD — Full clean rebuild to guarantee Moodle DB recognises latest version. All 5 version locations updated and ZIP rebuilt from source.
 * v1.5.4: VERSION REBUILD — Full clean rebuild to guarantee Moodle DB recognises latest version. All 5 version locations updated and ZIP rebuilt from source.
 * v1.5.3: BUTTON HOVER CONTRAST FIX — Added explicit color declarations with !important to all 8 interactive element hover states (true/false buttons, card select, matching, ordering, fill-in-blank words, column sort, category bucket). Moodle Boost theme global button:hover { color: #fff } was overriding plugin colours, causing white text on light-green/light-red and other light backgrounds. True/False hover now keeps dark red (#dc2626) and dark green (#16a34a) text. All other hover states keep var(--ala-text).
 * v1.5.2: SESSION LOCK FIX — Added \core\session\manager::write_close() after auth checks to prevent blocking concurrent requests during AI generation.
 * v1.5.1: Build sync release — verified CSS safety (no :has/:is/:where/@layer/@container), confirmed all build files match source, clean ZIP rebuild with latest styles.
 * v1.5.0: Premium CSS polish across all 8 activity types — richer shadows, gradient feedback toasts, lock-in animations, celebratory completion screen. Full responsive overhaul with tablet (768px), mobile (600px) and small mobile (380px) breakpoints. Touch device fixes: drag-and-drop disabled on touch, proper tap targets (48px+). Matching shows "Match from/Match to" labels on mobile single-column layout. Flashcard upgrade: 3 new sounds (flip, cardDone, swoosh), progress dots, slide transitions, premium gradients. Educational quality hardened: 0 formulaic T/F explanations, 0 short flashcard backs, 4-5 FIB blanks, full scenario coverage.
 * v1.4.0: Added Flashcards, True/False Swipe, Fill in the Blank activity types. Full prompt rewrite with 8 activity types, enhanced quality rules, mobile-first CSS for all new types, teacher edit/preview/review support for all types. Token limit increased to 6000.
 * v1.3.4: Version sync rebuild — ensures Quick Links block shows correct latest version
 * v1.3.3: Remove video gate feature - AI Video Activity plugin handles video embedding separately
 * v1.3.2: LIVE TEST VERIFIED - HLTAID009 CPR generation confirmed: 5 activity types (cardselect, columnsort, categorysort, matching, ordering), workplace scenarios, plausible distractors
 * v1.3.1: Prompt engine rewrite - ChatGPT-recommended multilingual consistency, banned word enforcement, JSON schema drift elimination, Australian English spelling conventions
 * v1.3.0: Scenario-based questions mode with optional country, industry, and worker level inputs for contextualised workplace scenarios
 * v1.2.0: Fix Moodle completion not triggering - remove non-existent grade function call that crashed before completion, use COMPLETION_UNKNOWN for custom rule re-evaluation
 * v1.1.9: Fix results screen not showing after last activity - show completion screen regardless of AJAX result, save progress before completing
 * v1.1.8: Persist completion screen - returning to activity shows completed state instead of resetting
 * v1.1.7: Add question prompt under each activity title, teacher info banner
 * v1.1.6: Remove view-based completion - only custom rule (complete all activities) remains
 * v1.1.5: Remove grade settings - revision activities use completion only, not grades
 * v1.1.4: Reduce feedback toast display time from 2.5s to 1.5s
 * v1.1.3: Reposition feedback toast to just below the activity area
 * v1.1.2: Fix completion error when multiple attempts exist (Practice Again)
 * v1.1.1: Column sort visual redesign - green tick/red cross headers, colour-coded columns
 * v1.1.0: Review mode shows correct answers highlighted instead of resetting activities
 * v1.0.9: Fix button hover contrast - protect text colours from Moodle theme overrides
 * v1.0.8: Move feedback toast from centre to bottom of activity area
 * v1.0.7: Credit cost summary on generation form (per-activity rate + dynamic total)
 * v1.0.6: Continue/Practice Again buttons, AI prompt quality overhaul, language mapping
 * v1.0.5: Review mode + fix last item not sticking in drop zones
 * v1.0.4: Inline editing - edit icon on each activity for minor text edits
 * v1.0.3: Teacher preview mode - click activities to preview/test them
 *
 * Interactive revision activities generated from learning content using AI.
 * 8 activity types: Ordering, Category Sort, Column Sort, Card Select, Matching,
 *                   Flashcards, True/False Swipe, Fill in the Blank.
 *
 * @package    mod_aiactivities
 * @copyright  2026 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_aiactivities';
$plugin->version   = 2026072300;
$plugin->requires  = 2022041900; // Moodle 4.0
$plugin->supported = [400, 500];  // Moodle 4.0 to 5.x
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.13'; // FIX-AUDIO-BLEED: voiceover from previous activity now stopped before next starts (currentVoiceoverSrc tracking + onended cleanup). FIX-FEEDBACK-GAP: removed premature feedback hide in advanceToNext() — toast now persists through the 800ms delay until renderActivity() clears it, eliminating the blank gap between activities. FIX-TOUCH-DETECT: removed navigator.maxTouchPoints > 0 from isTouchDevice check (false-positive on Windows Chrome/Edge laptops); now uses ontouchstart + pointer:coarse only, restoring drag-and-drop for ordering/sorting/category activities on desktop. Savepoint 2026061600171. (v1.6.6): TrueFalseSwipe handleAnswer() and button result state now use explicit boolean coercion (=== true || === 'true') instead of strict equality on stmt.correct. Fixes AI occasionally returning "false" as a JSON string — previously Boolean("false")=true caused correct answer False to be treated as True, marking student correct=False as incorrect. (v1.6.5): Changed default voice from Aoede to Zephyr in view.php (HTML select order, JS config voiceid fallback) and ajax.php (all voiceid optional_param defaults). PHP-only. No AMD, CSS, or DB schema changes. // FIX-CURL-FILELIB: Added require_once($CFG->libdir.'/filelib.php') to ajax.php so the Moodle \curl class is available. Without it, every \curl instantiation threw "Class curl not found". No DB schema changes. Savepoint 2026052700167.
