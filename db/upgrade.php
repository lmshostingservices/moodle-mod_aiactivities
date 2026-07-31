<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_aiactivities_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022412) {
        $table = new xmldb_table('aiactivities');

        $field = new xmldb_field('videourl', XMLDB_TYPE_CHAR, '512', null, null, null, null, 'completionallcorrect');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('videorequirement', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none', 'videourl');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('videominseconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'videorequirement');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026022412, 'aiactivities');
    }

    if ($oldversion < 2026022750) {
        $table = new xmldb_table('aiactivities');

        $field = new xmldb_field('videominseconds');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('videorequirement');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('videourl');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026022750, 'aiactivities');
    }

    if ($oldversion < 2026030600157) {
        upgrade_mod_savepoint(true, 2026030600157, 'aiactivities');
    }

    if ($oldversion < 2026030600158) {
        upgrade_mod_savepoint(true, 2026030600158, 'aiactivities');
    }

    if ($oldversion < 2026030600159) {
        upgrade_mod_savepoint(true, 2026030600159, 'aiactivities');
    }

    if ($oldversion < 2026030600160) {
        // v1.5.10: Version rebuild — no DB schema changes.
        upgrade_mod_savepoint(true, 2026030600160, 'aiactivities');
    }

    if ($oldversion < 2026030600161) {
        // v1.5.11: Flashcard UI — fixed dark mode back card which had a solid
        // greenish background (#1a2b24). Back card now uses the same neutral dark
        // background as the front card (#1e1e2e) with only a color accent via the
        // top border. No DB schema changes.
        upgrade_mod_savepoint(true, 2026030600161, 'aiactivities');
    }

    if ($oldversion < 2026031200005) {
        // v1.5.12 through v1.5.16: Various UI and logic fixes — no DB schema changes.
        upgrade_mod_savepoint(true, 2026031200005, 'aiactivities');
    }

    if ($oldversion < 2026031600517) {
        // v1.5.17: AMD build sync — build/player.js and build/player.min.js updated
        // to match src/player.js (v1.5.14 True/False correct/incorrect result status
        // display was missing from build files). No DB schema changes.
        upgrade_mod_savepoint(true, 2026031600517, 'aiactivities');
    }

    if ($oldversion < 2026031700518) {
        // v1.5.18: True/False tester feedback fixes:
        // (A) Inline result status now full-width (width:100%) so correct/incorrect
        //     text is always centred regardless of message length.
        // (B) Added $string['incorrect']='Incorrect!' so the inline TFS card shows
        //     "Incorrect!" instead of "Not quite right. Try again!" (which implied retry).
        // (C) TFS activity completion with wrong answers now shows "Activity done." via
        //     new $string['truefalse_activity_complete'] — avoids misleading "Try again!"
        //     toast when the TFS activity has no per-question retry mechanism.
        // No DB schema changes.
        upgrade_mod_savepoint(true, 2026031700518, 'aiactivities');
    }

    if ($oldversion < 2026032200538) {
        // v1.5.38: ETA BANNERS — Estimated Time to Complete banners for teacher + student views.
        // No DB schema changes.
        upgrade_mod_savepoint(true, 2026032200538, 'aiactivities');
    }

    // v1.5.39: VERSION BUMP — Maintenance release. No DB changes.
    if ($oldversion < 2026032200539) {
        upgrade_mod_savepoint(true, 2026032200539, 'aiactivities');
    }

    // v1.5.40: ETA recalibrate.
    if ($oldversion < 2026032200540) {
        upgrade_mod_savepoint(true, 2026032200540, 'aiactivities');
    }

    // v1.5.41: Course info time estimation update — 2 min per scenario.
    if ($oldversion < 2026032300541) {
        upgrade_mod_savepoint(true, 2026032300541, 'aiactivities');
    }

    // v1.5.42: FIX-FEEDBACK-CENTRE — replaced position:absolute/left:50%/transform with
    // normal-flow width:100% flex container. Feedback badge now centres via justify-content:center
    // on the element itself, immune to Moodle Bootstrap positioned ancestors intercepting left:50%.
    if ($oldversion < 2026032300542) {
        upgrade_mod_savepoint(true, 2026032300542, 'aiactivities');
    }

    // v1.5.43: FIX-WELLDONE-OVERLAP — feedbackTimer variable added; renderActivity() now
    // cancels any pending "Well done!" toast timer before rendering the next activity,
    // preventing the green toast from bleeding ~700ms into the next question view.
    if ($oldversion < 2026032300543) {
        upgrade_mod_savepoint(true, 2026032300543, 'aiactivities');
    }

    // v1.5.44: FIX-FEEDBACK-PERSIST — feedback toasts (Well done, Activity Done, Not quite
    // right) were never actually hiding. Root cause: .ala-player-feedback has display:flex
    // !important in CSS, which overrides the inline style.display='none' set by JS (inline
    // without !important loses to stylesheet !important). Fix: added
    // .ala-player-feedback.ala-feedback-hidden { display:none!important } (specificity 0,2,0
    // beats 0,1,0); all JS hide calls changed from style.display='none' to
    // classList.add('ala-feedback-hidden'); view.php elements start with ala-feedback-hidden
    // class instead of broken inline display:none. JS-only + CSS + view.php change.
    if ($oldversion < 2026032300544) {
        upgrade_mod_savepoint(true, 2026032300544, 'aiactivities');
    }

    // v1.5.45: BUG FIX — Feedback toasts ("Well done", "Activity Done", "Not quite right")
    //          persisted and did not auto-dismiss when moving between activities or retrying.
    //          Root cause 1: showFeedback() set feedbackEl.style.display='flex' as an inline
    //          style that persisted in the DOM even after classList.add('ala-feedback-hidden')
    //          was called; the inline style could interfere with the class-based display in
    //          Moodle's Bootstrap environment despite the CSS !important rule. Fix: remove the
    //          style.display='flex' line entirely; visibility is now controlled exclusively by
    //          the ala-feedback-hidden CSS class (showFeedback clears any lingering inline
    //          style with style.display='' before reassigning className). Root cause 2: when
    //          the student clicked "Practice Again", the old feedback was still visible during
    //          the startattempt AJAX gap before renderActivity(0) ran. Fix: practiceAgain()
    //          immediately clears the timer and adds ala-feedback-hidden on click. Also
    //          reinforced renderActivity() to clear the inline style alongside the class. JS-only.
    if ($oldversion < 2026032400545) {
        upgrade_mod_savepoint(true, 2026032400545, 'aiactivities');
    }

    // v1.5.47: FIX-FEEDBACK-PERSIST — advanceToNext() now immediately clears the
    //          feedback auto-hide timer and hides the toast before scheduling
    //          renderActivity(). Previously the 1500ms timer outlived the 800ms
    //          render delay, so "Well done" / "Activity Done" floated over the next
    //          activity's UI. JS-only fix; no DB schema changes.
    if ($oldversion < 2026032401547) {
        upgrade_mod_savepoint(true, 2026032401547, 'aiactivities');
    }

    // v1.5.48: Industry & Sector dropdown unification. Industry SELECT uses same
    //          29-industry list as Content Creator (hardcoded in JS). New
    //          #ala-scenario-sector SELECT auto-populates sub-sectors on industry change.
    //          Sector value sent as 'scenariosector' to AI server. JS-only. No DB changes.
    if ($oldversion < 2026032401548) {
        upgrade_mod_savepoint(true, 2026032401548, 'aiactivities');
    }

    // v1.5.49: ASYNC GENERATION — Eliminated Replit proxy 120s timeout failures.
    //          JS calls action=generate_async → PHP hits Express /api/moodle/aiactivities/start
    //          → returns {jobId} in ~500ms. JS polls action=poll_job every 3s → Express
    //          GET /api/jobs/:jobId. When status=done, result processed identically to the
    //          former sync response. Internal loopback bypasses proxy hard limit. No DB changes.
    if ($oldversion < 2026032401549) {
        upgrade_mod_savepoint(true, 2026032401549, 'aiactivities');
    }

    // v1.5.50: VERSION BUMP — Clean release following master release process.
    //          No code changes beyond v1.5.49. No DB schema changes.
    if ($oldversion < 2026032401550) {
        upgrade_mod_savepoint(true, 2026032401550, 'aiactivities');
    }

    // v1.5.51: DBWRITE AUDIT FIX — All write operations in ajax.php (generate,
    //   generate_async, poll_job) now enforce require_sesskey() and capability checks
    //   before modifying the DB. No DB schema changes.
    if ($oldversion < 2026032401551) {
        upgrade_mod_savepoint(true, 2026032401551, 'aiactivities');
    }

    // v1.5.52: VERSION BUMP — Maintenance release. No DB schema changes.
    if ($oldversion < 2026032401552) {
        upgrade_mod_savepoint(true, 2026032401552, 'aiactivities');
    }

    // v1.5.53: VERSION BUMP — Maintenance release. No DB schema changes.
    if ($oldversion < 2026032401553) {
        upgrade_mod_savepoint(true, 2026032401553, 'aiactivities');
    }

    // v1.5.54: FIX-DOUBLE-CLICK-SKIP — Added advancing boolean flag guard in
    //   advanceToNext() to prevent race condition where a second Done click
    //   within the 800ms delay window skipped the next activity. No DB schema changes.
    if ($oldversion < 2026032501554) {
        upgrade_mod_savepoint(true, 2026032501554, 'aiactivities');
    }

    // v1.5.55: VERSION BUMP — Sync release. CC_VERSION stale fix, all AMD trios
    //   hard-synced src = build = min (CRC verified). No DB schema changes.
    if ($oldversion < 2026032501555) {
        upgrade_mod_savepoint(true, 2026032501555, 'aiactivities');
    }

    // v1.5.56: FIX-UNKNOWN-ACTION — ajax.php PARAM_ALPHA stripped underscores from
    //   action names: 'generate_async' → 'generateasync', 'poll_job' → 'polljob'.
    //   Changed to PARAM_ALPHANUMEXT. No DB schema changes.
    if ($oldversion < 2026032601556) {
        upgrade_mod_savepoint(true, 2026032601556, 'aiactivities');
    }

    // v1.5.57 + v1.5.58: FIX-ASYNC-DB-SAVE — async generation never persisted activities to the DB.
    //   New 'save_generated' AJAX action added to ajax.php. doPoll() in player.js now calls
    //   save_generated with the polled activities before reloading. No DB schema changes.
    //   v1.5.57 was stamped 2026032501557 (March 25) but v1.5.56 was already 2026032601556
    //   (March 26) — Moodle rejected the install with "cannot downgrade". v1.5.58 corrects
    //   this by using 2026032601558 (same day prefix as v1.5.56, higher build suffix).
    //   Both v1.5.57 and v1.5.58 use this single savepoint so sites on v1.5.56 upgrade cleanly.
    if ($oldversion < 2026032601558) {
        upgrade_mod_savepoint(true, 2026032601558, 'aiactivities');
    }

    // v1.5.59: BUG FIX — sessionStorage textarea save/restore on activity type tab switch. No DB schema changes.
    if ($oldversion < 2026032701559) {
        upgrade_mod_savepoint(true, 2026032701559, 'aiactivities');
    }

    // v1.5.60: VERSION BUMP — Clean release increment. No code or DB schema changes.
    if ($oldversion < 2026032701560) {
        upgrade_mod_savepoint(true, 2026032701560, 'aiactivities');
    }

    // v1.5.61: BUG FIX — Regenerate button pre-populates generation form from sessionStorage.
    //   Content, language, activity count, and scenario-mode fields saved on generation and
    //   restored when regenerate is clicked. No DB schema changes.
    if ($oldversion < 2026032701561) {
        upgrade_mod_savepoint(true, 2026032701561, 'aiactivities');
    }

    // v1.5.62: BUG FIX — COST-DISPLAY-STALE: after restoring activityCount from sessionStorage
    //   on Regenerate, change event now dispatched on savedCount input so cost calculator
    //   re-fires and updates totalCostEl. No DB schema changes.
    if ($oldversion < 2026032801562) {
        upgrade_mod_savepoint(true, 2026032801562, 'aiactivities');
    }

    // v1.6.0: NEW FEATURE — Voiceover narration support. Adds voiceoverenabled, voicegender,
    //   and voiceid columns to aiactivities table. Teachers can enable AI voiceover that reads
    //   each activity aloud for students. Credit cost: +1 per activity when voiceover enabled.
    if ($oldversion < 2026040100163) {
        $table = new xmldb_table('aiactivities');

        $field = new xmldb_field('voiceoverenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'completionallcorrect');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('voicegender', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'female', 'voiceoverenabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('voiceid', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'Aoede', 'voicegender');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026040100163, 'aiactivities');
    }

    // v1.6.1 - FIX: Activity count cap raised from 15 to 20.
    //   Root cause: ajax.php clamped activitycount to min(15, ...) in 3 places and
    //   server/routes.ts validated activityCount with z.number().max(15). The frontend
    //   dropdown showed options up to 30, so selecting any value above 15 was silently
    //   truncated to 15 with no error message. Fix: all four locations now use max of 20:
    //   ajax.php (3 occurrences), server/routes.ts Zod schema, and view.php dropdown
    //   capped to 20 options so the UI stays in sync.
    //   No DB schema changes. version.php → 2026041600164.
    if ($oldversion < 2026041600164) {
        upgrade_mod_savepoint(true, 2026041600164, 'aiactivities');
    }
    // v1.6.2: AMD ENCODING FIX: All non-ASCII characters (em dashes, arrows, box-drawing chars, ellipsis, bullets, emoji, accented Latin) scrubbed from all AMD JS files (amd/src, amd/build, amd/build/*.min.js). Root cause of Moodle primary/secondary navigation menus disappearing site-wide: non-ASCII bytes in any installed plugin's AMD file cause a SyntaxError inside RequireJS's first.js bundle, throwing "No define call for core/first" and aborting the entire AMD module chain. No PHP, DB schema, or functional changes in this release.
    if ($oldversion < 2026042200165) {
        upgrade_mod_savepoint(true, 2026042200165, 'aiactivities');
    }

    // v1.6.3: FIX-CURL-BATCH — ajax.php switched all raw curl_init() calls to Moodle \curl
    //   wrapper. No DB schema changes.
    if ($oldversion < 2026051200166) {
        upgrade_mod_savepoint(true, 2026051200166, 'aiactivities');
    }

    // v1.6.4: FIX-CURL-FILELIB — Added require_once($CFG->libdir.'/filelib.php') to ajax.php.
    //   The Moodle \curl class is defined in filelib.php; without the require_once every
    //   new \curl() call threw "Class curl not found". No DB schema changes.
    if ($oldversion < 2026052700167) {
        upgrade_mod_savepoint(true, 2026052700167, 'aiactivities');
    }

    if ($oldversion < 2026072300221) {
        // FIX-API-DOMAIN: Updated all API endpoint URLs from lms-labs.com to lms-labs.com.
        // lms-labs.com has no DNS resolution from Moodle server side; lms-labs.com is the
        // correct working domain. All ajax.php, api_client, unlock_verifier, lib.php calls updated.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_mod_savepoint(true, 2026072300221, 'aiactivities');
    }

    if ($oldversion < 2026072300222) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // essaygraderai.app was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_mod_savepoint(true, 2026072300222, 'aiactivities');
    }

    if ($oldversion < 2026072300223) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_mod_savepoint(true, 2026072300223, 'aiactivities');
    }

    return true;
}