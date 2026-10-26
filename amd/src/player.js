/**
 * AI Learning Activities - Interactive Player.
 *
 * 8 activity types: Card Select, Column Sort, Category Sort, Matching, Ordering,
 *                   Flashcards, True/False Swipe, Fill in the Blank
 * Mobile-first with tap mode + desktop drag/drop
 * Unlimited attempts, forced progression, confetti on completion
 *
 * @module     mod_aiactivities/player
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {
    'use strict';

    var CONFIG = null;
    var activities = [];
    var currentIndex = 0;
    var attemptId = null;
    var progress = {};
    var hadWrongAnswers = false;
    var streak = 0; // Activities answered correctly first go, in a row.
    var activityclean = true; // No wrong answer yet in the current activity.
    var activitydone = false; // The current activity has already been completed.
    var activityscored = true; // The current activity has answers that can be wrong.
    var streakTimer = null;
    var undoTimer = null; // Dismisses the undo bar shown after a removal.
    var deleteInFlight = false; // Guards against a second removal before the first lands.
    var UNDO_WINDOW_MS = 8000; // How long the undo offer stays on screen.
    var advancing = false; // V1.5.54 FIX-DOUBLE-CLICK-SKIP: guard against advanceToNext() firing twice
    // v1.6.8 FIX-AUDIO-BLEED: reference to the playing voiceover BufferSourceNode
    // so it can be stopped before the next activity starts.
    var currentVoiceoverSrc = null;
    var currentVoiceoverBuffer = null;
    var voiceoverGain = null;
    var voiceoverMuted = false;
    var isTouchDevice = false;
    var isPreviewMode = false;
    var isReviewMode = false;
    var feedbackTimer = null; // V1.5.43 FIX-WELLDONE-OVERLAP: tracks auto-hide timer so it can be cancelled early
    var reviewIndex = 0;
    var selectedAlaJobLevels = []; // Multi-select job levels (pill buttons)
    var selectedAlaJobRoles = []; // Multi-select job roles (chips input)
    var progressErrorShown = false; // Only surface a save-progress failure once per page load.

    // ========== LANGUAGE STRINGS ==========

    /**
     * Look up a user-facing string supplied by PHP in CONFIG.strings.
     *
     * @param {String} key The language string key.
     * @param {String} fallback English text used when the key was not supplied.
     * @return {String} The localised string, or the English fallback.
     */
    function str(key, fallback) {
        var s = (CONFIG && CONFIG.strings) ? CONFIG.strings[key] : null;
        return (s === undefined || s === null || s === '') ? fallback : s;
    }

    /**
     * Look up a language string that carries a single {$a} placeholder.
     *
     * PHP hands the strings over unresolved, so the substitution happens here.
     *
     * @param {String} key The string identifier.
     * @param {String} value The value to put in place of the placeholder.
     * @return {String} The resolved string.
     */
    function strWithParam(key, value) {
        var template = str(key, '');
        if (!template) {
            return String(value === undefined || value === null ? '' : value);
        }

        return template.replace('{$a}', String(value === undefined || value === null ? '' : value));
    }

    /**
     * Human readable label for one of the eight activity types.
     *
     * @param {String} type The internal activity type identifier.
     * @return {String} The localised label, or the raw type when unrecognised.
     */
    function activityTypeLabel(type) {
        switch (type) {
            case 'cardselect': return str('js_type_cardselect', 'Card Select');
            case 'columnsort': return str('js_type_columnsort', 'Column Sort');
            case 'categorysort': return str('js_type_categorysort', 'Category Sort');
            case 'matching': return str('js_type_matching', 'Matching Pairs');
            case 'ordering': return str('js_type_ordering', 'Put in Order');
            case 'flashcards': return str('js_type_flashcards', 'Flashcards');
            case 'truefalseswipe': return str('js_type_truefalse', 'True or False');
            case 'fillinblank': return str('js_type_fillinblank', 'Fill in the Blank');
            default: return type ? String(type) : '';
        }
    }

    /**
     * The activity types currently ticked in the generation form.
     *
     * @return {String[]} The checked type identifiers, in the order they appear.
     */
    function selectedActivityTypes() {
        var boxes = document.querySelectorAll('#ala-activity-types .ala-type-checkbox');
        var selected = [];
        Array.prototype.forEach.call(boxes, function(box) {
            if (box.checked) {
                selected.push(box.value);
            }
        });

        return selected;
    }

    // ========== NOTIFICATIONS ==========

    /**
     * Show a non-blocking error notification.
     *
     * @param {String} message The message to display.
     * @return {void}
     */
    function notifyError(message) {
        try {
            Notification.addNotification({message: message, type: 'error'});
        } catch (e) {
            // Notification subsystem unavailable - nothing further we can do.
        }
    }

    /**
     * Show a non-blocking success notification.
     *
     * @param {String} message The message to display.
     * @return {void}
     */
    function notifySuccess(message) {
        try {
            Notification.addNotification({message: message, type: 'success'});
        } catch (e) {
            // Notification subsystem unavailable - nothing further we can do.
        }
    }

    // ========== SHARED DOM HELPERS ==========

    /**
     * Bind an activation handler that fires on both click and keyboard (Enter / Space),
     * so elements rendered as clickable divs remain keyboard operable.
     *
     * @param {Element} el The element to bind.
     * @param {Function} fn Handler, called with the element as `this`.
     * @return {void}
     */
    function onActivate(el, fn) {
        if (!el) {
            return;
        }
        el.addEventListener('click', fn);
        el.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                fn.call(el, e);
            }
        });
    }

    /**
     * Mark an element as a keyboard-operable button without changing its tag.
     *
     * @param {Element} el The element to mark up.
     * @param {String} label Optional accessible label.
     * @return {void}
     */
    function makeButtonLike(el, label) {
        if (!el) {
            return;
        }
        el.setAttribute('role', 'button');
        el.setAttribute('tabindex', '0');
        if (label) {
            el.setAttribute('aria-label', label);
        }
    }

    /**
     * Move focus somewhere sensible after a full re-render of the activity area.
     *
     * @param {Element} container The activity container that was just rewritten.
     * @return {void}
     */
    function focusFirst(container) {
        if (!container) {
            return;
        }
        var target = container.querySelector('.ala-activity-title');
        if (target) {
            target.setAttribute('tabindex', '-1');
        } else {
            target = container.querySelector(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
        }
        if (!target) {
            return;
        }
        try {
            target.focus({preventScroll: true});
        } catch (e) {
            target.focus();
        }
    }

    /**
     * Replace the first literal occurrence of a needle, without `$` pattern expansion.
     *
     * @param {String} haystack The string to search.
     * @param {String} needle The literal substring to find.
     * @param {String} replacement The literal replacement text.
     * @return {String} The resulting string.
     */
    function replaceLiteral(haystack, needle, replacement) {
        var at = haystack.indexOf(needle);
        if (at === -1) {
            return haystack;
        }
        return haystack.slice(0, at) + replacement + haystack.slice(at + needle.length);
    }

    /**
     * Whether the viewer has asked for reduced motion.
     *
     * @return {Boolean} True when animations should be skipped.
     */
    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    // -- Industry & Sector Data (supplied, already localised, by PHP in CONFIG.taxonomy) --

    /**
     * Whether a value is a real array.
     *
     * @param {*} value The value to test.
     * @return {Boolean} True when the value is an array.
     */
    function isArray(value) {
        return Object.prototype.toString.call(value) === '[object Array]';
    }

    /**
     * Normalise one sector list into {value, label} pairs.
     *
     * `value` is the canonical English string sent to the generation API; `label`
     * is the translated display text. Plain strings and the older `name`-only
     * shape are accepted so nothing throws part way through a PHP upgrade.
     *
     * @param {Array} list The raw sector list from CONFIG.taxonomy.
     * @return {Array} Array of {value: String, label: String}; empty when unusable.
     */
    function normaliseSectors(list) {
        if (!isArray(list)) {
            return [];
        }
        var out = [];
        list.forEach(function(sector) {
            if (typeof sector === 'string') {
                if (sector) {
                    out.push({value: sector, label: sector});
                }
                return;
            }
            if (!sector) {
                return;
            }
            var value = sector.value || sector.name;
            if (!value) {
                return;
            }
            out.push({value: value, label: sector.label || sector.name || value});
        });
        return out;
    }

    /**
     * The industry taxonomy provided by PHP, normalised to {value, label, sectors}.
     *
     * @return {Array} Array of industry objects; empty when CONFIG.taxonomy is absent.
     */
    function getTaxonomy() {
        if (!CONFIG || !isArray(CONFIG.taxonomy)) {
            return [];
        }
        var out = [];
        CONFIG.taxonomy.forEach(function(entry) {
            if (typeof entry === 'string') {
                if (entry) {
                    out.push({value: entry, label: entry, sectors: []});
                }
                return;
            }
            if (!entry) {
                return;
            }
            var value = entry.value || entry.name;
            if (!value) {
                return;
            }
            out.push({
                value: value,
                label: entry.label || entry.name || value,
                sectors: normaliseSectors(entry.sectors)
            });
        });
        return out;
    }

    /**
     * The sector list for an industry, keyed by its canonical value.
     *
     * @param {String} industry The canonical industry value (the industry select's value).
     * @return {Array} Array of {value: String, label: String}; empty when unknown.
     */
    function getIndustrySectors(industry) {
        if (!industry) {
            return [];
        }
        var list = getTaxonomy();
        var i;
        for (i = 0; i < list.length; i++) {
            if (list[i].value === industry) {
                return list[i].sectors;
            }
        }
        // Tolerate a stored selection that was captured as a display label.
        for (i = 0; i < list.length; i++) {
            if (list[i].label === industry) {
                return list[i].sectors;
            }
        }
        return [];
    }

    /**
     * Append one option per industry to a select element. The option value is the
     * canonical English string; the option text is the translated label.
     *
     * @param {Element} el The select element to populate.
     * @return {void}
     */
    function populateIndustrySelect(el) {
        if (!el) {
            return;
        }
        getTaxonomy().forEach(function(ind) {
            var o = document.createElement('option');
            o.value = ind.value;
            o.textContent = ind.label;
            el.appendChild(o);
        });
    }

    /**
     * Rebuild a sector select for the chosen industry.
     *
     * @param {Element} el The sector select element.
     * @param {String} industry The canonical value of the currently chosen industry.
     * @return {void}
     */
    function populateSectorSelect(el, industry) {
        if (!el) {
            return;
        }
        el.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = str('js_sector_placeholder', 'Select sector (optional)...');
        el.appendChild(placeholder);
        getIndustrySectors(industry).forEach(function(sector) {
            var o = document.createElement('option');
            o.value = sector.value;
            o.textContent = sector.label;
            el.appendChild(o);
        });
        el.disabled = !industry;
    }
    // ----------------------------------------------------------------------------

    var ICONS = {
        shield: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 ' +
            '7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 ' +
            '0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>',
        book: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 ' +
            '0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/></svg>',
        lightbulb: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 ' +
            '1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 ' +
            '22h4"/></svg>',
        gear: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 ' +
            '0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 ' +
            '0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 ' +
            '0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 ' +
            '0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 ' +
            '.73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle ' +
            'cx="12" cy="12" r="3"/></svg>',
        heart: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 ' +
            '3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 ' +
            '5.5l7 7Z"/></svg>',
        star: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 ' +
            '8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        check: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 ' +
            '1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
        warning: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 ' +
            '0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        clock: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" ' +
            'r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        target: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" ' +
            'r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
        users: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 ' +
            '0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 ' +
            '0 0 1 0 7.75"/></svg>',
        tool: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 ' +
            '0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 ' +
            '7.94-7.94l-3.76 3.76z"/></svg>',
        flag: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 ' +
            '2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>',
        eye: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 ' +
            '0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" ' +
            'r="3"/></svg>',
        lock: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" ' +
            'x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
    };

    /**
     * Return the inline SVG markup for a named icon.
     *
     * @param {String} name The icon key.
     * @return {String} SVG markup; the star icon when the name is unknown.
     */
    function getIcon(name) {
        return ICONS[name] || ICONS.star;
    }

    // ========== SOUND EFFECTS (Web Audio API) ==========
    var audioCtx = null;

    /**
     * Lazily create the shared Web Audio context.
     *
     * @return {AudioContext} The shared audio context.
     */
    function getAudioCtx() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        return audioCtx;
    }

    /**
     * Play a short synthesised UI sound.
     *
     * @param {String} type One of correct, wrong, streak, complete, lock, flip, cardDone, swoosh.
     * @return {void}
     */
    function playSound(type) {
        // Bookkeeping first: a wrong answer must end the streak even when the
        // audio context is unavailable and everything below throws.
        if (type === 'wrong') {
            registerWrongAnswer();
        }

        try {
            var ctx = getAudioCtx();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            gain.gain.value = 0.15;

            if (type === 'correct') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(523.25, ctx.currentTime);
                osc.frequency.setValueAtTime(659.25, ctx.currentTime + 0.1);
                osc.frequency.setValueAtTime(783.99, ctx.currentTime + 0.2);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.4);
            } else if (type === 'wrong') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(200, ctx.currentTime);
                osc.frequency.setValueAtTime(150, ctx.currentTime + 0.15);
                gain.gain.value = 0.08;
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.3);
            } else if (type === 'streak') {
                // A brighter, faster run than 'correct', for a streak milestone.
                [659.25, 830.61, 987.77, 1318.51].forEach(function(freq, i) {
                    var so = ctx.createOscillator();
                    var sg = ctx.createGain();
                    so.connect(sg);
                    sg.connect(ctx.destination);
                    so.type = 'triangle';
                    so.frequency.value = freq;
                    sg.gain.setValueAtTime(0.0001, ctx.currentTime + i * 0.08);
                    sg.gain.exponentialRampToValueAtTime(0.16, ctx.currentTime + i * 0.08 + 0.02);
                    sg.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.08 + 0.3);
                    so.start(ctx.currentTime + i * 0.08);
                    so.stop(ctx.currentTime + i * 0.08 + 0.32);
                });
                osc.disconnect();
                gain.disconnect();
                return;
            } else if (type === 'complete') {
                var notes = [523.25, 659.25, 783.99, 1046.50];
                notes.forEach(function(freq, i) {
                    var o = ctx.createOscillator();
                    var g = ctx.createGain();
                    o.connect(g);
                    g.connect(ctx.destination);
                    o.type = 'sine';
                    o.frequency.value = freq;
                    g.gain.value = 0.12;
                    g.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15 * i + 0.3);
                    o.start(ctx.currentTime + 0.15 * i);
                    o.stop(ctx.currentTime + 0.15 * i + 0.3);
                });
            } else if (type === 'lock') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                gain.gain.value = 0.1;
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.15);
            } else if (type === 'flip') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(600, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(900, ctx.currentTime + 0.08);
                gain.gain.value = 0.1;
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.12);
            } else if (type === 'cardDone') {
                var cdNotes = [659.25, 783.99, 1046.50];
                cdNotes.forEach(function(freq, i) {
                    var o2 = ctx.createOscillator();
                    var g2 = ctx.createGain();
                    o2.connect(g2);
                    g2.connect(ctx.destination);
                    o2.type = 'sine';
                    o2.frequency.value = freq;
                    g2.gain.value = 0.1;
                    g2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12 * i + 0.2);
                    o2.start(ctx.currentTime + 0.12 * i);
                    o2.stop(ctx.currentTime + 0.12 * i + 0.2);
                });
            } else if (type === 'swoosh') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(400, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(800, ctx.currentTime + 0.15);
                gain.gain.value = 0.06;
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.18);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.18);
            }
        } catch (e) {
            // Audio not supported
        }
    }

    // ========== CONFETTI ==========

    /**
     * Fire a short confetti burst over the page. Skipped when the viewer has
     * requested reduced motion.
     *
     * @return {void}
     */
    function fireConfetti() {
        if (prefersReducedMotion()) {
            return;
        }
        var canvas = document.createElement('canvas');
        canvas.className = 'ala-confetti-canvas';
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');
        var particles = [];
        var colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#06b6d4'];

        for (var i = 0; i < 150; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height * 0.5 - canvas.height * 0.5,
                vx: (Math.random() - 0.5) * 8,
                vy: Math.random() * 3 + 2,
                color: colors[Math.floor(Math.random() * colors.length)],
                size: Math.random() * 8 + 4,
                rotation: Math.random() * 360,
                rotationSpeed: (Math.random() - 0.5) * 10,
                opacity: 1
            });
        }

        var startTime = Date.now();

        /**
         * One animation frame of the confetti burst.
         *
         * @return {void}
         */
        function animate() {
            var elapsed = Date.now() - startTime;
            if (elapsed > 3000) {
                canvas.remove();
                return;
            }
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(function(p) {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.1;
                p.rotation += p.rotationSpeed;
                if (elapsed > 2000) {
                    p.opacity = Math.max(0, 1 - (elapsed - 2000) / 1000);
                }
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rotation * Math.PI / 180);
                ctx.globalAlpha = p.opacity;
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                ctx.restore();
            });
            requestAnimationFrame(animate);
        }
        animate();
    }

    // ========== AJAX HELPERS ==========

    /**
     * POST an action to the module's AJAX endpoint.
     *
     * @param {String} action The server-side action name.
     * @param {Object} params Additional POST parameters.
     * @param {Function} callback Receives the decoded response, or an {ok: false, error} object.
     * @return {void}
     */
    function ajaxCall(action, params, callback) {
        params.action = action;
        params.sesskey = CONFIG.sesskey;
        params.cmid = CONFIG.cmid;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', CONFIG.ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) {
                return;
            }
            if (xhr.status < 200 || xhr.status >= 300) {
                callback({
                    ok: false,
                    error: str('js_err_http', 'Request failed') + ' (HTTP ' + xhr.status + ')'
                });
                return;
            }
            try {
                callback(JSON.parse(xhr.responseText));
            } catch (e) {
                callback({ok: false, error: str('js_err_parse', 'Could not read the server response.')});
            }
        };

        var encoded = [];
        for (var key in params) {
            if (params.hasOwnProperty(key)) {
                encoded.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
            }
        }
        xhr.send(encoded.join('&'));
    }

    // ========== SHUFFLE ==========

    /**
     * Return a shuffled copy of an array (Fisher-Yates).
     *
     * @param {Array} arr The source array; not modified.
     * @return {Array} A new shuffled array.
     */
    function shuffle(arr) {
        var a = arr.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var temp = a[i];
            a[i] = a[j];
            a[j] = temp;
        }
        return a;
    }

    // ========== UPDATE PROGRESS BAR ==========

    /**
     * Sync the header progress bar and step counter with the current position.
     *
     * @return {void}
     */
    function updateProgressBar() {
        var currentEl = document.getElementById('ala-current-step');
        var totalEl = document.getElementById('ala-total-steps');
        var fillEl = document.getElementById('ala-player-progress-fill');

        if (currentEl) {
            currentEl.textContent = (currentIndex + 1).toString();
        }
        if (totalEl) {
            totalEl.textContent = activities.length.toString();
        }
        if (fillEl) {
            var pct = ((currentIndex + 1) / activities.length) * 100;
            fillEl.style.width = pct + '%';
        }
    }

    // ========== ESTIMATED TIME ==========
    var clockSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 ' +
        '12 12 16 14"/></svg>';

    /**
     * Format an estimated duration as a short human readable string.
     *
     * @param {Number} totalSeconds The estimate in seconds.
     * @return {String} A localised duration label.
     */
    function formatEtaTime(totalSeconds) {
        var minutes = Math.ceil(totalSeconds / 60);
        if (minutes < 1) {
            return str('js_eta_underminute', 'Under 1 minute');
        }
        if (minutes === 1) {
            return '~1 ' + str('js_eta_minute', 'minute');
        }
        if (minutes < 60) {
            return '~' + minutes + ' ' + str('js_eta_minutes', 'minutes');
        }
        var hours = Math.floor(minutes / 60);
        var rem = minutes % 60;
        if (rem === 0) {
            return '~' + hours + ' ' + (hours === 1 ? str('js_eta_hour', 'hour') : str('js_eta_hours', 'hours'));
        }
        return '~' + hours + ' ' +
            (hours === 1 ? str('js_eta_hr', 'hr') : str('js_eta_hrs', 'hrs')) + ' ' +
            rem + ' ' + str('js_eta_min', 'min');
    }

    /**
     * Estimate how long a set of activities will take.
     *
     * @param {Array} acts The activity objects.
     * @return {Object} {seconds: Number, counts: Object} keyed by activity type.
     */
    function calculateActivityEta(acts) {
        var totalSeconds = 0;
        var counts = {};
        acts.forEach(function(act) {
            var t = act.type || 'unknown';
            counts[t] = (counts[t] || 0) + 1;
            switch (t) {
                case 'truefalseswipe':
                    totalSeconds += (act.statements ? act.statements.length : 5) * 45;
                    break;
                case 'cardselect':
                    totalSeconds += (act.cards ? act.cards.length : 4) * 30;
                    break;
                case 'flashcards':
                    totalSeconds += (act.cards ? act.cards.length : 4) * 40;
                    break;
                case 'matching':
                    totalSeconds += (act.pairs ? act.pairs.length : 4) * 40;
                    break;
                case 'ordering':
                    totalSeconds += (act.items ? act.items.length : 5) * 35;
                    break;
                case 'columnsort':
                    totalSeconds += (act.items ? act.items.length : 6) * 35;
                    break;
                case 'categorysort':
                    var catItems = 0;
                    if (act.items) {
                        catItems = act.items.length;
                    }
                    totalSeconds += Math.max(catItems, 4) * 35;
                    break;
                case 'fillinblank':
                    totalSeconds += (act.items ? act.items.length : 3) * 40;
                    break;
                default:
                    totalSeconds += 90;
            }
        });
        return {seconds: totalSeconds, counts: counts};
    }

    /**
     * Build the "estimated completion time" banner markup.
     *
     * @param {Array} acts The activity objects.
     * @return {String} Escaped HTML for the banner.
     */
    function buildEtaBannerHtml(acts) {
        var eta = calculateActivityEta(acts);
        var timeStr = formatEtaTime(eta.seconds);
        var parts = [];
        for (var key in eta.counts) {
            if (eta.counts.hasOwnProperty(key)) {
                parts.push(eta.counts[key] + ' ' + activityTypeLabel(key));
            }
        }
        var detail = acts.length + ' ' + str('js_eta_activities', 'activities') +
            (parts.length > 0 ? ' (' + parts.join(', ') + ')' : '');
        return '<div class="ala-eta-banner">' +
            '<div class="ala-eta-icon-wrap" aria-hidden="true">' + clockSvg + '</div>' +
            '<div class="ala-eta-body">' +
            '<span class="ala-eta-label">' +
            escapeHtml(str('js_eta_label', 'Estimated completion time')) + '</span>' +
            '<span class="ala-eta-time">' + escapeHtml(timeStr) + '</span>' +
            '<span class="ala-eta-detail">' + escapeHtml(detail) + '</span>' +
            '</div></div>';
    }

    // ========== SHOW FEEDBACK ==========
    /**
     * Immediately hide a feedback element (clears any inline style override).
     *
     * @param {Element} el The feedback element, may be null.
     * @return {void}
     */
    function hideFeedbackEl(el) {
        if (!el) {
            return;
        }
        el.style.removeProperty('display');
        el.classList.add('ala-feedback-hidden');
    }

    /**
     * Show the transient correct / incorrect feedback toast.
     *
     * @param {Boolean} correct Whether the answer was correct.
     * @param {String} message The message text (escaped unless isHtml is true).
     * @param {Boolean} isHtml Opt in to passing already-safe markup as the message.
     * @return {void}
     */
    function showFeedback(correct, message, isHtml) {
        if (!correct) {
            // Registered here rather than in playSound(): the sound is not reached
            // when the feedback element is missing, and the streak must not depend on it.
            registerWrongAnswer();
        }

        var feedbackId = isPreviewMode ? 'ala-preview-feedback' : 'ala-player-feedback';
        var feedbackEl = document.getElementById(feedbackId);
        if (!feedbackEl) {
            return;
        }

        // BUG-FEEDBACK-PERSIST (v1.5.46): Use classList.remove/add instead of overwriting
        // className. Overwriting className dropped the ala-feedback-hidden class in some
        // edge cases and also lost the base ala-player-feedback class in others, meaning the
        // CSS :not(.ala-feedback-hidden) show rule could misfire. Using classList ensures the
        // base class is always present and hidden/shown state is toggled cleanly.
        feedbackEl.style.removeProperty('display'); // Clear any lingering inline style
        feedbackEl.classList.remove('ala-feedback-correct', 'ala-feedback-incorrect');
        feedbackEl.classList.add(correct ? 'ala-feedback-correct' : 'ala-feedback-incorrect');
        // Reveal: remove the hidden class  -  CSS :not(.ala-feedback-hidden) then shows it.
        feedbackEl.classList.remove('ala-feedback-hidden');

        feedbackEl.setAttribute('role', 'status');
        feedbackEl.setAttribute('aria-live', 'polite');
        feedbackEl.innerHTML = '<div class="ala-feedback-content">' +
            '<span class="ala-feedback-icon" aria-hidden="true">' +
            (correct ? '&#10004;' : '&#10008;') + '</span>' +
            '<span class="ala-feedback-text">' +
            (isHtml ? message : escapeHtml(message)) + '</span>' +
            '</div>';

        playSound(correct ? 'correct' : 'wrong');

        // Cancel any previous auto-hide before starting a new one.
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
        }
        feedbackTimer = setTimeout(function() {
            hideFeedbackEl(feedbackEl);
            feedbackTimer = null;
        }, 1500);
    }

    // ========== SAVE PROGRESS ==========

    /**
     * Persist the current attempt progress to the server.
     *
     * @return {void}
     */
    function saveProgress() {
        if (isPreviewMode || !attemptId) {
            return;
        }
        var completedCount = 0;
        for (var key in progress) {
            if (progress[key] === true) {
                completedCount++;
            }
        }
        ajaxCall('saveprogress', {
            attemptid: attemptId,
            currentactivity: currentIndex,
            progressjson: JSON.stringify(progress),
            completedcount: completedCount
        }, function(result) {
            if (result && result.ok) {
                return;
            }
            // Surface the failure once only, so a flaky connection cannot spam the page.
            if (progressErrorShown) {
                return;
            }
            progressErrorShown = true;
            notifyError((result && result.error) ||
                str('js_err_saveprogress', 'Your progress could not be saved. Please check your connection.'));
        });
    }

    // ========== STREAK ==========

    /** @type {Number[]} Streak lengths that are celebrated. */
    var STREAK_MILESTONES = [4, 8, 12, 16];

    /** @type {String[]} Activity types with no answer that can be got wrong. */
    var UNSCORED_TYPES = ['flashcards'];

    /**
     * Record that the student got something wrong, which ends the current streak.
     *
     * @return {void}
     */
    function registerWrongAnswer() {
        if (isPreviewMode || isReviewMode || activitydone) {
            // Once an activity has been completed, later fiddling with it cannot
            // cost the student a streak they have already earned.
            return;
        }
        activityclean = false;
        hadWrongAnswers = true;
        streak = 0;
        updateStreakChip(false);
    }

    /**
     * Count a finished activity towards the streak and celebrate a milestone.
     *
     * @return {Boolean} True when a milestone popup was shown.
     */
    function registerActivityFinished() {
        if (isPreviewMode || isReviewMode) {
            return false;
        }
        if (!activityclean || !activityscored) {
            // Flashcards and anything else without a wrong answer are neutral:
            // they neither extend nor break the streak.
            return false;
        }

        streak++;
        updateStreakChip(true);

        if (STREAK_MILESTONES.indexOf(streak) === -1) {
            return false;
        }

        showStreakPopup(streak);
        return true;
    }

    /**
     * Show the running streak in the player header, from two in a row upwards.
     *
     * @param {Boolean} bump Whether to animate the chip because the streak just grew.
     * @return {void}
     */
    function updateStreakChip(bump) {
        var chip = document.getElementById('ala-streak-chip');
        if (!chip) {
            return;
        }

        if (streak < 2 || isPreviewMode || isReviewMode) {
            chip.className = 'ala-streak-chip';
            chip.innerHTML = '';
            return;
        }

        chip.className = 'ala-streak-chip ala-streak-chip-on';
        chip.innerHTML = '<span class="ala-streak-flame" aria-hidden="true">&#128293;</span>' +
            '<span class="ala-streak-chip-count">' + streak + '</span>' +
            '<span class="ala-streak-chip-label">' +
            escapeHtml(str('js_streak_chip_label', 'in a row')) + '</span>';

        if (bump && !prefersReducedMotion()) {
            chip.classList.remove('ala-streak-bump');
            void chip.offsetWidth;
            chip.classList.add('ala-streak-bump');
        }
    }

    /**
     * Celebrate a streak milestone with a short, self-dismissing popup.
     *
     * @param {Number} count The number of activities answered correctly in a row.
     * @return {void}
     */
    function showStreakPopup(count) {
        var player = document.getElementById('ala-player');
        if (!player) {
            return;
        }

        dismissStreakPopup();

        var pop = document.createElement('div');
        pop.id = 'ala-streak-pop';
        pop.className = 'ala-streak-pop';
        pop.setAttribute('role', 'status');
        pop.setAttribute('aria-live', 'polite');

        var card = document.createElement('div');
        card.className = 'ala-streak-card';

        var badge = document.createElement('div');
        badge.className = 'ala-streak-badge';
        badge.textContent = String(count);
        badge.setAttribute('aria-hidden', 'true');

        var title = document.createElement('p');
        title.className = 'ala-streak-title';
        title.textContent = str('js_streak_' + count + '_title', 'On a streak!');

        var msg = document.createElement('p');
        msg.className = 'ala-streak-msg';
        msg.textContent = str('js_streak_' + count + '_msg', 'Great job, keep it up!');

        card.appendChild(badge);
        card.addEventListener('click', dismissStreakPopup);
        player.appendChild(pop);

        // The live region has to be in the document before its content changes,
        // or screen readers do not announce it.
        setTimeout(function() {
            pop.appendChild(card);
            card.appendChild(title);
            card.appendChild(msg);
        }, 30);

        if (!voiceoverMuted) {
            playSound('streak');
        }
        fireConfetti();

        streakTimer = setTimeout(dismissStreakPopup, 2600);
        document.addEventListener('keydown', streakKeyHandler);
    }

    /**
     * Dismiss the streak popup on Escape.
     *
     * @param {KeyboardEvent} e The key event.
     * @return {void}
     */
    function streakKeyHandler(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            dismissStreakPopup();
        }
    }

    /**
     * Remove the streak popup and move focus to the next control.
     *
     * @return {void}
     */
    function dismissStreakPopup() {
        if (streakTimer !== null) {
            clearTimeout(streakTimer);
            streakTimer = null;
        }
        document.removeEventListener('keydown', streakKeyHandler);

        var pop = document.getElementById('ala-streak-pop');
        if (pop && pop.parentNode) {
            pop.parentNode.removeChild(pop);
        }

        var btn = document.getElementById('ala-next-btn');
        var active = document.activeElement;
        var infocus = !active || active === document.body || (pop && pop.contains(active));
        if (btn && !btn.disabled && infocus) {
            btn.focus();
        }
    }

    /**
     * Clear the per-activity streak state before an activity is rendered.
     *
     * @param {Object} activity The activity about to be rendered.
     * @return {void}
     */
    function resetActivityState(activity) {
        dismissStreakPopup();
        activityclean = true;
        activitydone = false;
        activityscored = !!activity && UNSCORED_TYPES.indexOf(activity.type) === -1;
    }

    /**
     * Disable the controls of an activity that has just been completed, so it
     * cannot be answered a second time while the student decides to move on.
     *
     * @return {void}
     */
    function lockFinishedActivity() {
        ['ala-check-order', 'ala-fib-check', 'ala-flashcard-next', 'ala-tfs-continue'].forEach(
            function(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.disabled = true;
                    el.setAttribute('aria-disabled', 'true');
                }
            }
        );
    }

    // ========== ADVANCE TO NEXT ==========

    /**
     * Show the control that moves on to the next activity.
     *
     * Replaces the automatic advance the player used to do on a timer: the
     * feedback stays on screen until the student chooses to continue.
     *
     * @return {void}
     */
    function offerNext() {
        if (activitydone) {
            // The activity has already been completed; a second completion must not
            // count towards the streak or stack up popups.
            return;
        }
        activitydone = true;
        lockFinishedActivity();

        if (isPreviewMode || isReviewMode) {
            // Preview and review have their own navigation.
            setTimeout(advanceToNext, 800);
            return;
        }

        registerActivityFinished();

        var host = document.getElementById('ala-player-next');
        if (!host) {
            // No host element (an older template): fall back to advancing on a timer.
            setTimeout(advanceToNext, 900);
            return;
        }

        // The feedback for this activity should stay up until the student moves on.
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
            feedbackTimer = null;
        }

        var islast = (currentIndex >= activities.length - 1);
        var label = islast
            ? str('js_next_finish', 'Finish')
            : str('js_next_activity', 'Next activity');

        host.innerHTML = '';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'ala-next-btn';
        btn.className = 'ala-btn ala-btn-primary ala-next-btn';
        btn.textContent = label;
        btn.appendChild(nextArrowIcon());
        btn.addEventListener('click', function() {
            btn.disabled = true;
            host.classList.remove('ala-next-visible');
            advanceToNext();
        });

        host.appendChild(btn);
        host.classList.add('ala-next-visible');

        // Focus lands on the button whether or not a milestone popup is showing:
        // the popup is a scrim the student can click straight through.
        btn.focus();
        if (typeof btn.scrollIntoView === 'function') {
            btn.scrollIntoView({block: 'nearest'});
        }
    }

    /**
     * The arrow that follows the label on the next control.
     *
     * @return {SVGElement} The icon, hidden from assistive tech.
     */
    function nextArrowIcon() {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('width', '16');
        svg.setAttribute('height', '16');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        var path = document.createElementNS(ns, 'path');
        path.setAttribute('d', 'm9 18 6-6-6-6');
        svg.appendChild(path);
        return svg;
    }

    /**
     * Remove the next control, if one is on screen.
     *
     * @return {void}
     */
    function clearNext() {
        var host = document.getElementById('ala-player-next');
        if (host) {
            host.innerHTML = '';
            host.classList.remove('ala-next-visible');
        }
    }


    /**
     * Mark the current activity done and move on, or finish the attempt.
     *
     * @return {void}
     */
    function advanceToNext() {
        // The student now advances by clicking, and the button disables itself on the
        // first click, but the guard is kept: preview mode still advances on a timer,
        // and it costs nothing to keep a second call from skipping an activity.
        if (advancing) {
            return;
        }
        advancing = true;

        if (isPreviewMode) {
            advancing = false;
            showFeedback(true, str('js_preview_complete', 'Activity complete! Returning to list...'));
            setTimeout(function() {
                closeTeacherPreview();
            }, 1500);
            return;
        }
        if (isReviewMode) {
            advancing = false;
            showFeedback(true, str('feedback_welldone', 'Well done!'));
            return;
        }
        // V1.6.8 FIX-FEEDBACK-GAP: Do NOT hide the feedback toast here. renderActivity()
        // already clears it as its very first action, so hiding it early just creates an
        // ugly blank gap (no feedback AND no activity) for ~800ms. Let the toast remain
        // visible through the delay — it disappears naturally when the next activity renders.
        currentIndex++;
        if (currentIndex >= activities.length) {
            saveProgress();
            advancing = false;
            completeAllActivities();
        } else {
            saveProgress();
            advancing = false;
            renderActivity(currentIndex);
        }
    }

    // ========== MOODLE NAVIGATION LOCK ==========
    var ALA_NAV_SELECTOR = [
        '[data-action="next-activity-link"]',
        '[data-action="next"]',
        '.activity-navigation .nav-link[data-action]',
        '.activity-navigation a',
        '.course-content-nextprev .next',
        '.nextprevpage .next',
        '.activity_navigation .nav-next a',
        '.prevnext .next a'
    ].join(', ');

    /**
     * Visually disable the course navigation links until the activities are done.
     * The href is deliberately left untouched so the links stay valid for
     * assistive technology, search and middle-click.
     *
     * @return {void}
     */
    function lockMoodleNav() {
        var els = document.querySelectorAll(ALA_NAV_SELECTOR);
        els.forEach(function(el) {
            if (el.getAttribute('data-ala-nav-locked')) {
                return;
            }
            el.setAttribute('data-ala-nav-locked', '1');
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.4';
            el.setAttribute('aria-disabled', 'true');
            el.setAttribute('title', str('js_nav_locked', 'Complete all activities to continue'));
        });
    }

    /**
     * Undo lockMoodleNav(), restoring the navigation links.
     *
     * @return {void}
     */
    function unlockMoodleNav() {
        // V1.5.13: Also sweep by selector so any elements added to the DOM after
        // lockMoodleNav() ran (e.g. dynamically rendered nav) also get unlocked.
        var allNavEls = document.querySelectorAll(ALA_NAV_SELECTOR);
        allNavEls.forEach(function(el) {
            el.style.removeProperty('pointer-events');
            el.style.removeProperty('opacity');
        });

        var els = document.querySelectorAll('[data-ala-nav-locked="1"]');
        els.forEach(function(el) {
            el.removeAttribute('data-ala-nav-locked');
            el.style.removeProperty('pointer-events');
            el.style.removeProperty('opacity');
            el.removeAttribute('title');
            el.removeAttribute('aria-disabled');
        });
    }

    // ========== COMPLETE ALL ==========

    /**
     * Swap the player for the congratulations screen.
     *
     * @return {void}
     */
    function showCompleteScreen() {
        unlockMoodleNav();

        // BUG-FEEDBACK-PERSIST (v1.5.46): ala-player-feedback is a SIBLING of
        // ala-player-content, so hiding ala-player-content does NOT hide the toast.
        // Clear the timer and dismiss the feedback immediately before showing the
        // complete screen, otherwise the toast overlaps the congratulations panel.
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
            feedbackTimer = null;
        }
        var completeFbEl = document.getElementById(isPreviewMode ? 'ala-preview-feedback' : 'ala-player-feedback');
        hideFeedbackEl(completeFbEl);

        var playerContent = document.getElementById('ala-player-content');
        var completeScreen = document.getElementById('ala-player-complete');
        if (playerContent) {
            playerContent.style.display = 'none';
        }
        if (completeScreen) {
            completeScreen.style.display = 'flex';
        }

        var fillEl = document.getElementById('ala-player-progress-fill');
        if (fillEl) {
            fillEl.style.width = '100%';
        }

        var continueBtn = document.getElementById('ala-continue-btn');
        if (continueBtn && CONFIG.nextactivityurl) {
            continueBtn.href = CONFIG.nextactivityurl;
            continueBtn.style.display = 'inline-flex';
        }

        var completetitle = document.querySelector('.ala-complete-title');
        if (completetitle && typeof completetitle.focus === 'function') {
            completetitle.focus();
        }

        playSound('complete');
        if (!hadWrongAnswers) {
            setTimeout(fireConfetti, 300);
        }
    }

    /**
     * Tell the server the attempt is complete, then show the complete screen.
     *
     * @return {void}
     */
    function completeAllActivities() {
        if (!attemptId) {
            return;
        }
        clearNext();
        dismissStreakPopup();
        ajaxCall('complete', {attemptid: attemptId}, function() {
            showCompleteScreen();
        });
    }

    // ========== PRACTICE AGAIN ==========

    /**
     * Restart the whole set of activities as a fresh attempt.
     *
     * @return {void}
     */
    function practiceAgain() {
        clearNext();
        dismissStreakPopup();
        streak = 0;
        activityclean = true;
        activitydone = false;
        updateStreakChip(false);
        currentIndex = 0;
        progress = {};
        attemptId = null;
        hadWrongAnswers = false;
        advancing = false; // V1.5.54: always reset so the fresh attempt isn't blocked

        // Immediately clear the feedback toast so old messages (e.g. "Activity Done") are
        // not visible during the startattempt AJAX gap. renderActivity(0) hides it too,
        // but only after the round-trip  -  this guarantees an instant hide on button click.
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer); feedbackTimer = null;
        }
        hideFeedbackEl(document.getElementById('ala-player-feedback'));

        var completeScreen = document.getElementById('ala-player-complete');
        var playerContent = document.getElementById('ala-player-content');
        var headerEl = document.getElementById('ala-player-header');
        var reviewNav = document.getElementById('ala-review-nav');

        if (completeScreen) {
            completeScreen.style.display = 'none';
        }
        if (playerContent) {
            playerContent.style.display = 'block';
        }
        if (headerEl) {
            headerEl.style.display = 'block';
        }
        if (reviewNav) {
            reviewNav.style.display = 'none';
        }

        var fillEl = document.getElementById('ala-player-progress-fill');
        if (fillEl) {
            fillEl.style.width = '0%';
        }

        ajaxCall('startattempt', {}, function(result) {
            if (!result.ok) {
                return;
            }
            attemptId = result.attemptid;
            renderActivity(0);
        });
    }

    // ========== REVIEW MODE ==========

    /**
     * Enter the read-only review of all activities and their answers.
     *
     * @return {void}
     */
    function enterReviewMode() {
        isReviewMode = true;
        reviewIndex = 0;

        var completeScreen = document.getElementById('ala-player-complete');
        var playerContent = document.getElementById('ala-player-content');
        var reviewNav = document.getElementById('ala-review-nav');
        var headerEl = document.getElementById('ala-player-header');

        if (completeScreen) {
            completeScreen.style.display = 'none';
        }
        if (playerContent) {
            playerContent.style.display = 'block';
        }
        if (reviewNav) {
            reviewNav.style.display = 'flex';
        }
        if (headerEl) {
            headerEl.style.display = 'none';
        }

        renderReviewActivity(reviewIndex);
        updateReviewNav();
    }

    /**
     * Leave review mode and return to the complete screen.
     *
     * @return {void}
     */
    function exitReviewMode() {
        isReviewMode = false;

        var completeScreen = document.getElementById('ala-player-complete');
        var playerContent = document.getElementById('ala-player-content');
        var reviewNav = document.getElementById('ala-review-nav');
        var headerEl = document.getElementById('ala-player-header');

        if (playerContent) {
            playerContent.style.display = 'none';
        }
        if (reviewNav) {
            reviewNav.style.display = 'none';
        }
        if (completeScreen) {
            completeScreen.style.display = 'flex';
        }
        if (headerEl) {
            headerEl.style.display = '';
            var fillEl = document.getElementById('ala-player-progress-fill');
            if (fillEl) {
                fillEl.style.width = '100%';
            }
        }
    }

    /**
     * Render one activity in review mode.
     *
     * @param {Number} index Index into the activities array.
     * @return {void}
     */
    function renderReviewActivity(index) {
        var container = document.getElementById('ala-player-content');
        if (!container || !activities[index]) {
            return;
        }

        container.style.display = 'block';
        container.classList.remove('ala-animate-slide-in');
        void container.offsetWidth;
        container.classList.add('ala-animate-slide-in');

        var activity = activities[index];
        switch (activity.type) {
            case 'cardselect': renderReviewCardSelect(activity, container); break;
            case 'columnsort': renderReviewColumnSort(activity, container); break;
            case 'categorysort': renderReviewCategorySort(activity, container); break;
            case 'matching': renderReviewMatching(activity, container); break;
            case 'ordering': renderReviewOrdering(activity, container); break;
            case 'flashcards': renderReviewFlashcards(activity, container); break;
            case 'truefalseswipe': renderReviewTrueFalseSwipe(activity, container); break;
            case 'fillinblank': renderReviewFillInBlank(activity, container); break;
            default:
                container.innerHTML = '<p>' +
                    escapeHtml(str('js_err_unknowntype', 'Unknown activity type')) + '</p>';
        }
        focusFirst(container);
    }

    /**
     * Render the card select activity in review mode.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderReviewCardSelect(activity, container) {
        var html = '<div class="ala-activity ala-cardselect ala-review-mode">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('cardselect')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-cards-grid">';
        activity.cards.forEach(function(card, idx) {
            var isCorrect = idx === activity.correctIndex;
            html += '<div class="ala-card-option' + (isCorrect ? ' ala-correct' : '') + '" style="pointer-events:none;">';
            html += '<div class="ala-card-icon">' + getIcon(card.icon || 'star') + '</div>';
            html += '<div class="ala-card-label">' + escapeHtml(card.label) + '</div>';
            html += '<div class="ala-card-desc">' + escapeHtml(card.description) + '</div>';
            html += '</div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    /**
     * Render the column sort activity in review mode.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderReviewColumnSort(activity, container) {
        var html = '<div class="ala-activity ala-columnsort ala-review-mode">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('columnsort')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) {
            html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        }
        html += '<div class="ala-columns-layout">';
        html += '<div class="ala-column ala-column-a"><div class="ala-column-header ala-column-header-a"><svg ' +
            'class="ala-col-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 ' +
            '17l-5-5"/></svg>' +
            escapeHtml(activity.columnA) + '</div><div class="ala-column-items">';
        activity.items.forEach(function(item) {
            if (item.column === 'A') {
                html += '<div class="ala-locked-item ala-correct">' + escapeHtml(item.text) + '</div>';
            }
        });
        html += '</div></div>';
        html += '<div class="ala-column ala-column-b"><div class="ala-column-header ala-column-header-b"><svg ' +
            'class="ala-col-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path ' +
            'd="m6 6 12 12"/></svg>' +
            escapeHtml(activity.columnB) + '</div><div class="ala-column-items">';
        activity.items.forEach(function(item) {
            if (item.column === 'B') {
                html += '<div class="ala-locked-item ala-correct">' + escapeHtml(item.text) + '</div>';
            }
        });
        html += '</div></div>';
        html += '</div></div>';
        container.innerHTML = html;
    }

    /**
     * Render the category sort activity in review mode.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderReviewCategorySort(activity, container) {
        var html = '<div class="ala-activity ala-categorysort ala-review-mode">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('categorysort')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) {
            html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        }
        html += '<div class="ala-categories-grid">';
        activity.categories.forEach(function(catName, catIdx) {
            html += '<div class="ala-category-bucket">';
            html += '<div class="ala-category-header">' + escapeHtml(catName) + '</div>';
            html += '<div class="ala-category-items">';
            activity.items.forEach(function(item) {
                // Item.category may be a numeric index or a string name  -  accept both.
                if (item.category === catName || item.category === catIdx) {
                    html += '<div class="ala-locked-item ala-correct">' + escapeHtml(item.text) + '</div>';
                }
            });
            html += '</div></div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    /**
     * Render the matching activity in review mode.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderReviewMatching(activity, container) {
        var html = '<div class="ala-activity ala-matching ala-review-mode">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('matching')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) {
            html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        }
        html += '<div class="ala-matching-layout">';
        html += '<div class="ala-matching-col ala-matching-left">';
        html += '<span class="ala-matching-col-label">' +
            escapeHtml(str('js_matching_from', 'Match from')) + '</span>';
        activity.pairs.forEach(function(pair) {
            html += '<div class="ala-match-item ala-match-left ala-matched">' + escapeHtml(pair.left) + '</div>';
        });
        html += '</div>';
        html += '<div class="ala-matching-col ala-matching-right">';
        html += '<span class="ala-matching-col-label">' +
            escapeHtml(str('js_matching_to', 'Match to')) + '</span>';
        activity.pairs.forEach(function(pair) {
            html += '<div class="ala-match-item ala-match-right ala-matched">' + escapeHtml(pair.right) + '</div>';
        });
        html += '</div>';
        html += '</div></div>';
        container.innerHTML = html;
    }

    /**
     * Render the ordering activity in review mode.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderReviewOrdering(activity, container) {
        var html = '<div class="ala-activity ala-ordering ala-review-mode">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('ordering')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) {
            html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        }
        html += '<div class="ala-ordering-list">';
        activity.items.forEach(function(item, idx) {
            html += '<div class="ala-order-item ala-correct" style="pointer-events:none;">';
            html += '<span class="ala-order-num">' + (idx + 1) + '</span>';
            html += '<span class="ala-order-text">' + escapeHtml(item) + '</span>';
            html += '</div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    /**
     * Render the flashcards activity in review mode.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderReviewFlashcards(activity, container) {
        var html = '<div class="ala-activity ala-flashcards ala-review-mode">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('flashcards')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) {
            html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        }
        html += '<div class="ala-review-flashcards-list">';
        activity.cards.forEach(function(card, idx) {
            html += '<div class="ala-review-flashcard-item">';
            html += '<div class="ala-review-fc-front"><strong>' + (idx + 1) + '.</strong> ' + escapeHtml(card.front) + '</div>';
            html += '<div class="ala-review-fc-back">' + escapeHtml(card.back) + '</div>';
            html += '</div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    /**
     * Render the true/false activity in review mode.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderReviewTrueFalseSwipe(activity, container) {
        var html = '<div class="ala-activity ala-truefalseswipe ala-review-mode">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('truefalseswipe')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) {
            html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        }
        html += '<div class="ala-review-tfs-list">';
        activity.statements.forEach(function(stmt, idx) {
            html += '<div class="ala-review-tfs-item">';
            html += '<div class="ala-review-tfs-statement">';
            html += '<span class="ala-review-tfs-num">' + (idx + 1) + '</span>';
            html += '<span>' + escapeHtml(stmt.text) + '</span>';
            html += '<span class="' + (stmt.correct ? 'ala-tfs-true-badge' : 'ala-tfs-false-badge') + '">' +
                escapeHtml(stmt.correct ? str('truefalse_true', 'True') : str('truefalse_false', 'False')) +
                '</span>';
            html += '</div>';
            html += '<div class="ala-review-tfs-explanation">' + escapeHtml(stmt.explanation) + '</div>';
            html += '</div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    /**
     * Render the fill in the blank activity in review mode.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderReviewFillInBlank(activity, container) {
        var html = '<div class="ala-activity ala-fillinblank ala-review-mode">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('fillinblank')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) {
            html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        }
        html += '<div class="ala-fib-text">';
        // Escape the passage FIRST, then substitute the ___N___ markers. The markers
        // contain no HTML-special characters so they survive escaping unchanged.
        var text = escapeHtml(activity.text);
        for (var i = activity.blanks.length; i >= 1; i--) {
            var placeholder = '___' + i + '___';
            var answer = activity.blanks[i - 1].answer;
            text = replaceLiteral(text, placeholder,
                '<span class="ala-fib-blank ala-fib-filled ala-correct">' + escapeHtml(answer) + '</span>');
        }
        html += '<p class="ala-fib-passage">' + text + '</p>';
        html += '</div></div>';
        container.innerHTML = html;
    }

    /**
     * Update the review-mode previous/next buttons and position indicator.
     *
     * @return {void}
     */
    function updateReviewNav() {
        var prevBtn = document.getElementById('ala-review-prev');
        var nextBtn = document.getElementById('ala-review-next');
        var indicator = document.getElementById('ala-review-indicator');

        if (prevBtn) {
            prevBtn.disabled = (reviewIndex <= 0);
        }
        if (nextBtn) {
            nextBtn.disabled = (reviewIndex >= activities.length - 1);
        }
        if (indicator) {
            var activity = activities[reviewIndex];
            var label = activityTypeLabel(activity.type);
            indicator.textContent = (reviewIndex + 1) + ' / ' + activities.length + '   -   ' + label;
        }
    }

    /**
     * Step back one activity in review mode.
     *
     * @return {void}
     */
    function reviewPrev() {
        if (reviewIndex > 0) {
            reviewIndex--;
            renderReviewActivity(reviewIndex);
            updateReviewNav();
        }
    }

    /**
     * Step forward one activity in review mode.
     *
     * @return {void}
     */
    function reviewNext() {
        if (reviewIndex < activities.length - 1) {
            reviewIndex++;
            renderReviewActivity(reviewIndex);
            updateReviewNav();
        }
    }

    // ===================================================================
    // ACTIVITY RENDERER: CARD SELECT
    // ===================================================================
    /**
     * Render the card select activity.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderCardSelect(activity, container) {
        var locked = false;
        var html = '<div class="ala-activity ala-cardselect">';
        html += '<span class="ala-activity-type-badge">' +
            escapeHtml(activityTypeLabel('cardselect')) + '</span>';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<p class="ala-activity-instruction">' +
            escapeHtml(str('cardselect_instructions', 'Choose the correct answer.')) + '</p>';
        // V1.5.35 BUG-TF-INLINE-RESULT: Inline result strip replaces floating showFeedback()
        // overlay. Testers reported OK Correct / x Incorrect appearing "outside the box /
        // between the option boxes" because showFeedback() used a separate fixed-position
        // element (ala-player-feedback) rendered entirely outside the cards grid.
        // Fix: add a hidden inline-result div inside the activity area (between instruction
        // and the cards grid); on click, show it with correct/incorrect status + the correct
        // card label ("Answer: True" / "Answer: False" etc). Reuses ala-tfs-mid-result CSS.
        html += '<div class="ala-cardselect-inline-result" id="ala-cardselect-inline-result" ' +
            'role="status" aria-live="polite"></div>';
        html += '<div class="ala-cards-grid">';

        activity.cards.forEach(function(card, idx) {
            html += '<div class="ala-card-option" data-index="' + idx + '" role="button" tabindex="0">';
            html += '<div class="ala-card-icon">' + getIcon(card.icon || 'star') + '</div>';
            html += '<div class="ala-card-label">' + escapeHtml(card.label) + '</div>';
            html += '<div class="ala-card-desc">' + escapeHtml(card.description) + '</div>';
            html += '</div>';
        });

        html += '</div></div>';
        container.innerHTML = html;

        var resultEl = container.querySelector('#ala-cardselect-inline-result');
        var tickSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 ' +
            '17l-5-5"/></svg>';
        var crossSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
            'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path ' +
            'd="m6 6 12 12"/></svg>';

        var cards = container.querySelectorAll('.ala-card-option');
        cards.forEach(function(card) {
            onActivate(card, function() {
                if (locked) {
                    return;
                }
                locked = true;
                var idx = parseInt(card.getAttribute('data-index'), 10);
                var correct = idx === activity.correctIndex;

                cards.forEach(function(c) {
                    c.classList.remove('ala-selected', 'ala-correct', 'ala-incorrect');
                    c.style.pointerEvents = 'none';
                    c.setAttribute('aria-disabled', 'true');
                });

                var correctCardObj = activity.cards[activity.correctIndex] || {};
                var correctLabel = correctCardObj.label ? escapeHtml(correctCardObj.label) : '';

                if (correct) {
                    card.classList.add('ala-correct');
                    if (resultEl) {
                        resultEl.className = 'ala-cardselect-inline-result ala-tfs-mid-result ala-tfs-result-correct';
                        resultEl.innerHTML = tickSvg + ' ' +
                            escapeHtml(str('feedback_correct', 'Correct!'));
                    }
                    playSound('correct');
                    progress[currentIndex] = true;
                    offerNext();
                } else {
                    registerWrongAnswer();
                    card.classList.add('ala-incorrect');
                    var correctCard = container.querySelector('[data-index="' + activity.correctIndex + '"]');
                    if (correctCard) {
                        correctCard.classList.add('ala-correct');
                    }
                    if (resultEl) {
                        resultEl.className = 'ala-cardselect-inline-result ala-tfs-mid-result ala-tfs-result-incorrect';
                        var answerLine = correctLabel
                            ? '<span style="display:block;font-size:12px;font-weight:500;' +
                                'margin-top:4px;opacity:0.85;">' +
                                escapeHtml(str('truefalse_answer', 'Answer')) + ': ' + correctLabel + '</span>'
                            : '';
                        resultEl.innerHTML = '<span style="display:flex;align-items:center;' +
                            'gap:5px;justify-content:center;">' + crossSvg + ' ' +
                            escapeHtml(str('feedback_incorrect', 'Incorrect!')) + '</span>' + answerLine;
                    }
                    playSound('wrong');

                    setTimeout(function() {
                        locked = false;
                        cards.forEach(function(c) {
                            c.classList.remove('ala-selected', 'ala-correct', 'ala-incorrect');
                            c.style.pointerEvents = '';
                            c.removeAttribute('aria-disabled');
                        });
                        if (resultEl) {
                            resultEl.className = 'ala-cardselect-inline-result';
                            resultEl.innerHTML = '';
                        }
                    }, 2000);
                }
            });
        });
    }

    // ===================================================================
    // ACTIVITY RENDERER: COLUMN SORT (2 columns, items one at a time)
    // ===================================================================
    /**
     * Render the column sort activity.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderColumnSort(activity, container) {
        var items = shuffle(activity.items.slice());
        var currentItemIdx = 0;
        var correctCount = 0;
        var totalItems = items.length;

        /**
         * Draw the current state of the column sort.
         *
         * @return {void}
         */
        function renderState() {
            var html = '<div class="ala-activity ala-columnsort">';
            html += '<span class="ala-activity-type-badge">' +
                escapeHtml(activityTypeLabel('columnsort')) + '</span>';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) {
                html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            }
            html += '<p class="ala-activity-instruction">' +
                escapeHtml(str('columnsort_instructions', 'Put each item into the correct column.')) + '</p>';

            html += '<div class="ala-columnsort-progress">';
            html += '<div class="ala-columnsort-progress-bar"><div class="ala-columnsort-progress-fill" style="width:' +
                (correctCount / totalItems * 100) + '%"></div></div>';
            html += '<span>' + correctCount + ' / ' + totalItems + '</span>';
            html += '</div>';

            html += '<div class="ala-columns-layout">';
            html += '<div class="ala-column ala-column-a" data-col="A"><div class="ala-column-header ala-column-header-a"><svg ' +
                'class="ala-col-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
                'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 ' +
                '17l-5-5"/></svg>' +
                escapeHtml(activity.columnA) + '</div><div class="ala-column-items" id="ala-col-a"></div></div>';
            html += '<div class="ala-column ala-column-b" data-col="B"><div class="ala-column-header ala-column-header-b"><svg ' +
                'class="ala-col-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
                'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 ' +
                '18"/><path d="m6 6 12 12"/></svg>' +
                escapeHtml(activity.columnB) + '</div><div class="ala-column-items" id="ala-col-b"></div></div>';
            html += '</div>';

            if (currentItemIdx < totalItems) {
                var item = items[currentItemIdx];
                html += '<div class="ala-current-item-container">';
                html += '<div class="ala-current-item ala-animate-slide-in" id="ala-sort-item">' + escapeHtml(item.text) + '</div>';
                html += '</div>';
            }

            html += '</div>';
            container.innerHTML = html;

            // Re-render locked items
            var colAEl = document.getElementById('ala-col-a');
            var colBEl = document.getElementById('ala-col-b');
            for (var i = 0; i < currentItemIdx; i++) {
                var lockedItem = document.createElement('div');
                lockedItem.className = 'ala-locked-item';
                lockedItem.textContent = items[i].text;
                if (items[i].column === 'A' && colAEl) {
                    colAEl.appendChild(lockedItem);
                }
                if (items[i].column === 'B' && colBEl) {
                    colBEl.appendChild(lockedItem);
                }
            }

            /**
             * Handle the student choosing a column for the current item.
             *
             * @param {String} chosenCol Either 'A' or 'B'.
             * @return {void}
             */
            function handleColumnChoice(chosenCol) {
                var item = items[currentItemIdx];
                var correct = item.column === chosenCol;

                if (correct) {
                    playSound('lock');
                    correctCount++;
                    currentItemIdx++;
                    renderState();
                    if (currentItemIdx >= totalItems) {
                        showFeedback(true, str('feedback_welldone', 'Well done!'));
                        progress[currentIndex] = true;
                        offerNext();
                    }
                } else {
                    var sortItem = document.getElementById('ala-sort-item');
                    if (sortItem) {
                        sortItem.classList.add('ala-shake');
                        setTimeout(function() {
                            sortItem.classList.remove('ala-shake');
                        }, 500);
                    }
                    showFeedback(false, str('feedback_incorrect', 'Incorrect!'));
                }
            }

            // Bind columns as activatable drop targets (click, tap or keyboard).
            var columns = container.querySelectorAll('.ala-column');
            columns.forEach(function(col) {
                if (currentItemIdx < totalItems) {
                    col.classList.add('ala-column-clickable');
                    makeButtonLike(col, str('js_a11y_placeincolumn', 'Place the current item in this column') +
                        ': ' + (col.getAttribute('data-col') === 'A' ? activity.columnA : activity.columnB));
                    onActivate(col, function() {
                        handleColumnChoice(col.getAttribute('data-col'));
                    });
                }

                // Drag and drop for desktop
                col.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    col.classList.add('ala-drop-target');
                });
                col.addEventListener('dragleave', function() {
                    col.classList.remove('ala-drop-target');
                });
                col.addEventListener('drop', function(e) {
                    e.preventDefault();
                    col.classList.remove('ala-drop-target');
                    handleColumnChoice(col.getAttribute('data-col'));
                });
            });

            // FIX-DRAG-TOUCH: always enable draggable regardless of isTouchDevice.
            // On touch-primary devices the dragstart event simply won't fire, so
            // enabling draggable is harmless. On hybrid (touch + mouse) laptops the
            // old !isTouchDevice guard caused drag to be silently disabled even
            // though the user was operating with a mouse.  Clicking a column still
            // works on pure touch devices via the click handler above.
            var sortItem = document.getElementById('ala-sort-item');
            if (sortItem) {
                sortItem.setAttribute('draggable', 'true');
                sortItem.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', 'item');
                    sortItem.classList.add('ala-dragging');
                });
                sortItem.addEventListener('dragend', function() {
                    sortItem.classList.remove('ala-dragging');
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: CATEGORY SORT (4 categories)
    // ===================================================================
    /**
     * Render the category sort activity.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderCategorySort(activity, container) {
        var unsorted = shuffle(activity.items.slice());
        var sorted = {};
        activity.categories.forEach(function(_, i) {
            sorted[i] = [];
        });
        var currentItemIdx = 0;

        /**
         * Draw the current state of the category sort.
         *
         * @return {void}
         */
        function renderState() {
            var html = '<div class="ala-activity ala-categorysort">';
            html += '<span class="ala-activity-type-badge">' +
                escapeHtml(activityTypeLabel('categorysort')) + '</span>';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) {
                html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            }
            html += '<p class="ala-activity-instruction">' +
                escapeHtml(str('categorysort_instructions', 'Put each item into the correct category.')) + '</p>';

            html += '<div class="ala-categorysort-progress">';
            html += '<div class="ala-columnsort-progress-bar"><div class="ala-columnsort-progress-fill" style="width:' +
                (currentItemIdx / unsorted.length * 100) + '%"></div></div>';
            html += '<span>' + currentItemIdx + ' / ' + unsorted.length + '</span>';
            html += '</div>';

            // Current item to sort
            if (currentItemIdx < unsorted.length) {
                html += '<div class="ala-current-item-container">';
                html += '<div class="ala-current-item ala-animate-slide-in" id="ala-cat-item">' +
                    escapeHtml(unsorted[currentItemIdx].text) + '</div>';
                html += '</div>';
            }

            // Category buckets
            html += '<div class="ala-categories-grid">';
            activity.categories.forEach(function(catName, catIdx) {
                html += '<div class="ala-category-bucket" data-cat="' + catIdx + '">';
                html += '<div class="ala-category-header">' + escapeHtml(catName) + '</div>';
                html += '<div class="ala-category-items">';
                (sorted[catIdx] || []).forEach(function(item) {
                    html += '<div class="ala-locked-item">' + escapeHtml(item.text) + '</div>';
                });
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';

            html += '</div>';
            container.innerHTML = html;

            /**
             * Handle the student choosing a category bucket for the current item.
             *
             * @param {Number} chosenCat Index of the chosen category.
             * @return {void}
             */
            function handleCategoryChoice(chosenCat) {
                var item = unsorted[currentItemIdx];
                // Item.category can be a numeric index (0-3) from AI generation or a
                // string category name for backward compatibility. Accept both forms.
                var correct = (item.category === chosenCat) ||
                              (item.category === activity.categories[chosenCat]);

                if (correct) {
                    playSound('lock');
                    sorted[chosenCat].push(item);
                    currentItemIdx++;
                    renderState();
                    if (currentItemIdx >= unsorted.length) {
                        showFeedback(true, str('feedback_welldone', 'Well done!'));
                        progress[currentIndex] = true;
                        offerNext();
                    }
                } else {
                    var catItem = document.getElementById('ala-cat-item');
                    if (catItem) {
                        catItem.classList.add('ala-shake');
                        setTimeout(function() {
                            catItem.classList.remove('ala-shake');
                        }, 500);
                    }
                    showFeedback(false, str('feedback_incorrect', 'Incorrect!'));
                }
            }

            // Bind category buckets as activatable + drag targets.
            var buckets = container.querySelectorAll('.ala-category-bucket');
            buckets.forEach(function(bucket) {
                if (currentItemIdx < unsorted.length) {
                    var catIdx = parseInt(bucket.getAttribute('data-cat'), 10);
                    bucket.classList.add('ala-bucket-clickable');
                    makeButtonLike(bucket, str('js_a11y_placeincategory', 'Place the current item in this category') +
                        ': ' + (activity.categories[catIdx] || ''));
                    onActivate(bucket, function() {
                        handleCategoryChoice(parseInt(bucket.getAttribute('data-cat'), 10));
                    });
                }

                bucket.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    bucket.classList.add('ala-drop-target');
                });
                bucket.addEventListener('dragleave', function() {
                    bucket.classList.remove('ala-drop-target');
                });
                bucket.addEventListener('drop', function(e) {
                    e.preventDefault();
                    bucket.classList.remove('ala-drop-target');
                    handleCategoryChoice(parseInt(bucket.getAttribute('data-cat'), 10));
                });
            });

            // FIX-DRAG-TOUCH: always enable draggable (see column-sort comment above).
            var catItem = document.getElementById('ala-cat-item');
            if (catItem) {
                catItem.setAttribute('draggable', 'true');
                catItem.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', 'item');
                    catItem.classList.add('ala-dragging');
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: MATCHING
    // ===================================================================
    /**
     * Render the matching pairs activity.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderMatching(activity, container) {
        var pairs = activity.pairs;
        var leftItems = shuffle(pairs.map(function(p, i) {
            return {text: p.left, pairIndex: i};
        }));
        // V1.5.20: Guarantee shuffle is actually different from original order (fixes "not shuffling" on small pair counts).
        while (pairs.length > 1 && leftItems.every(function(item, idx) {
            return item.pairIndex === idx;
        })) {
            leftItems = shuffle(pairs.map(function(p, i) {
                return {text: p.left, pairIndex: i};
            }));
        }
        var rightItems = shuffle(pairs.map(function(p, i) {
            return {text: p.right, pairIndex: i};
        }));
        while (pairs.length > 1 && rightItems.every(function(item, idx) {
            return item.pairIndex === idx;
        })) {
            rightItems = shuffle(pairs.map(function(p, i) {
                return {text: p.right, pairIndex: i};
            }));
        }
        var matched = {};
        var selectedLeft = null;
        var selectedRight = null;

        /**
         * Draw the current state of the matching activity.
         *
         * @return {void}
         */
        function renderState() {
            var html = '<div class="ala-activity ala-matching">';
            html += '<span class="ala-activity-type-badge">' +
                escapeHtml(activityTypeLabel('matching')) + '</span>';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) {
                html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            }
            html += '<p class="ala-activity-instruction">' +
                escapeHtml(str('matching_instructions', 'Match each item on the left with its pair on the right.')) +
                '</p>';

            html += '<div class="ala-matching-layout">';

            // Left column
            html += '<div class="ala-matching-col ala-matching-left">';
            html += '<span class="ala-matching-col-label">' +
                escapeHtml(str('js_matching_from', 'Match from')) + '</span>';
            leftItems.forEach(function(item, idx) {
                var isMatched = matched[item.pairIndex] === true;
                var isSelected = selectedLeft === idx;
                var cls = 'ala-match-item ala-match-left';
                if (isMatched) {
                    cls += ' ala-matched';
                }
                if (isSelected) {
                    cls += ' ala-selected';
                }
                html += '<div class="' + cls + '" data-side="left" data-idx="' + idx +
                    '" data-pair="' + item.pairIndex + '" role="button" tabindex="' +
                    (isMatched ? '-1' : '0') + '"' + (isMatched ? ' aria-disabled="true"' : '') + '>' +
                    escapeHtml(item.text) + '</div>';
            });
            html += '</div>';

            // Right column
            html += '<div class="ala-matching-col ala-matching-right">';
            html += '<span class="ala-matching-col-label">' +
                escapeHtml(str('js_matching_to', 'Match to')) + '</span>';
            rightItems.forEach(function(item, idx) {
                var isMatched = matched[item.pairIndex] === true;
                var isSelected = selectedRight === idx;
                var cls = 'ala-match-item ala-match-right';
                if (isMatched) {
                    cls += ' ala-matched';
                }
                if (isSelected) {
                    cls += ' ala-selected';
                }
                html += '<div class="' + cls + '" data-side="right" data-idx="' + idx +
                    '" data-pair="' + item.pairIndex + '" role="button" tabindex="' +
                    (isMatched ? '-1' : '0') + '"' + (isMatched ? ' aria-disabled="true"' : '') + '>' +
                    escapeHtml(item.text) + '</div>';
            });
            html += '</div>';

            html += '</div></div>';
            container.innerHTML = html;

            // Bind click handlers
            var matchItems = container.querySelectorAll('.ala-match-item');
            matchItems.forEach(function(el) {
                if (el.classList.contains('ala-matched')) {
                    return;
                }
                onActivate(el, function() {
                    var side = el.getAttribute('data-side');
                    var idx = parseInt(el.getAttribute('data-idx'), 10);

                    if (side === 'left') {
                        selectedLeft = idx;
                        container.querySelectorAll('.ala-match-left').forEach(function(e) {
                            e.classList.remove('ala-selected');
                        });
                        el.classList.add('ala-selected');
                    } else {
                        selectedRight = idx;
                        container.querySelectorAll('.ala-match-right').forEach(function(e) {
                            e.classList.remove('ala-selected');
                        });
                        el.classList.add('ala-selected');
                    }

                    // Check if both sides selected
                    if (selectedLeft !== null && selectedRight !== null) {
                        var leftPair = leftItems[selectedLeft].pairIndex;
                        var rightPair = rightItems[selectedRight].pairIndex;

                        if (leftPair === rightPair) {
                            matched[leftPair] = true;
                            playSound('lock');
                            selectedLeft = null;
                            selectedRight = null;

                            var allMatched = Object.keys(matched).length === pairs.length;
                            if (allMatched) {
                                renderState();
                                showFeedback(true, str('feedback_welldone', 'Well done!'));
                                progress[currentIndex] = true;
                                offerNext();
                            } else {
                                renderState();
                            }
                        } else {
                            showFeedback(false, str('feedback_incorrect', 'Incorrect!'));
                            var leftEl = container.querySelector('.ala-match-left[data-idx="' + selectedLeft + '"]');
                            var rightEl = container.querySelector('.ala-match-right[data-idx="' + selectedRight + '"]');
                            if (leftEl) {
                                leftEl.classList.add('ala-shake');
                            }
                            if (rightEl) {
                                rightEl.classList.add('ala-shake');
                            }
                            selectedLeft = null;
                            selectedRight = null;
                            setTimeout(function() {
                                renderState();
                            }, 600);
                        }
                    }
                });
            });
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: ORDERING
    // ===================================================================
    /**
     * Render the ordering activity.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderOrdering(activity, container) {
        var correctOrder = activity.items.slice();
        var currentOrder = shuffle(correctOrder.slice());
        // Ensure it's actually shuffled
        while (arraysEqual(currentOrder, correctOrder) && correctOrder.length > 1) {
            currentOrder = shuffle(correctOrder.slice());
        }
        var selectedIndex = null;

        /**
         * Draw the current state of the ordering activity.
         *
         * @return {void}
         */
        function renderState() {
            var html = '<div class="ala-activity ala-ordering">';
            html += '<span class="ala-activity-type-badge">' +
                escapeHtml(activityTypeLabel('ordering')) + '</span>';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) {
                html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            }
            html += '<p class="ala-activity-instruction">' +
                escapeHtml(activity.instruction ||
                    str('ordering_instructions', 'Tap two items to swap them into the correct order.')) + '</p>';

            html += '<div class="ala-ordering-list" id="ala-order-list">';
            currentOrder.forEach(function(item, idx) {
                var isSelected = selectedIndex === idx;
                html += '<div class="ala-order-item' + (isSelected ? ' ala-selected' : '') +
                    '" data-idx="' + idx + '" role="button" tabindex="0" aria-pressed="' +
                    (isSelected ? 'true' : 'false') + '"' +
                    (isTouchDevice ? '' : ' draggable="true"') + '>';
                html += '<span class="ala-order-handle" aria-hidden="true">';
                html += '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ' +
                    'stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1"/><circle cx="15" cy="5" r="1"/><circle ' +
                    'cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="19" ' +
                    'r="1"/></svg>';
                html += '</span>';
                html += '<span class="ala-order-num">' + (idx + 1) + '</span>';
                html += '<span class="ala-order-text">' + escapeHtml(item) + '</span>';
                html += '</div>';
            });
            html += '</div>';

            html += '<div class="ala-ordering-actions">';
            html += '<button type="button" class="ala-btn ala-btn-primary" id="ala-check-order">' +
                escapeHtml(str('check_btn', 'Check')) + '</button>';
            html += '</div>';

            html += '</div>';
            container.innerHTML = html;

            // Tap to reorder on mobile
            var orderItems = container.querySelectorAll('.ala-order-item');
            orderItems.forEach(function(el) {
                onActivate(el, function() {
                    var idx = parseInt(el.getAttribute('data-idx'), 10);

                    if (selectedIndex === null) {
                        selectedIndex = idx;
                        el.classList.add('ala-selected');
                        el.setAttribute('aria-pressed', 'true');
                    } else if (selectedIndex === idx) {
                        selectedIndex = null;
                        el.classList.remove('ala-selected');
                        el.setAttribute('aria-pressed', 'false');
                    } else {
                        // Swap items
                        var temp = currentOrder[selectedIndex];
                        currentOrder[selectedIndex] = currentOrder[idx];
                        currentOrder[idx] = temp;
                        selectedIndex = null;
                        renderState();
                    }
                });
            });

            // Drag and drop for desktop only
            if (!isTouchDevice) {
                var draggedIdx = null;
                orderItems.forEach(function(el) {
                    el.addEventListener('dragstart', function(e) {
                        draggedIdx = parseInt(el.getAttribute('data-idx'), 10);
                        el.classList.add('ala-dragging');
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', draggedIdx.toString());
                    });
                    el.addEventListener('dragend', function() {
                        el.classList.remove('ala-dragging');
                        container.querySelectorAll('.ala-order-item').forEach(function(e) {
                            e.classList.remove('ala-drop-target');
                        });
                    });
                    el.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        el.classList.add('ala-drop-target');
                    });
                    el.addEventListener('dragleave', function() {
                        el.classList.remove('ala-drop-target');
                    });
                    el.addEventListener('drop', function(e) {
                        e.preventDefault();
                        el.classList.remove('ala-drop-target');
                        var targetIdx = parseInt(el.getAttribute('data-idx'), 10);
                        if (draggedIdx !== null && draggedIdx !== targetIdx) {
                            var temp = currentOrder[draggedIdx];
                            currentOrder[draggedIdx] = currentOrder[targetIdx];
                            currentOrder[targetIdx] = temp;
                            renderState();
                        }
                    });
                });
            }

            // Check button
            var checkBtn = document.getElementById('ala-check-order');
            if (checkBtn) {
                checkBtn.addEventListener('click', function() {
                    if (arraysEqual(currentOrder, correctOrder)) {
                        showFeedback(true, str('feedback_welldone', 'Well done!'));
                        progress[currentIndex] = true;
                        // Mark all items as correct
                        container.querySelectorAll('.ala-order-item').forEach(function(el) {
                            el.classList.add('ala-correct');
                        });
                        offerNext();
                    } else {
                        showFeedback(false, str('feedback_incorrect', 'Incorrect!'));
                        // Highlight wrong positions
                        var items = container.querySelectorAll('.ala-order-item');
                        items.forEach(function(el, idx) {
                            if (currentOrder[idx] === correctOrder[idx]) {
                                el.classList.add('ala-correct');
                            } else {
                                el.classList.add('ala-incorrect');
                                el.classList.add('ala-shake');
                            }
                        });
                        setTimeout(function() {
                            items.forEach(function(el) {
                                el.classList.remove('ala-correct', 'ala-incorrect', 'ala-shake');
                            });
                        }, 1500);
                    }
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: FLASHCARDS
    // ===================================================================
    /**
     * Render the flashcards activity.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderFlashcards(activity, container) {
        var cards = activity.cards;
        var currentCardIdx = 0;
        var flipped = false;
        var animatingOut = false;

        /**
         * Draw the current flashcard.
         *
         * @return {void}
         */
        function renderState() {
            var html = '<div class="ala-activity ala-flashcards">';
            html += '<span class="ala-activity-type-badge">' +
                escapeHtml(activityTypeLabel('flashcards')) + '</span>';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) {
                html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            }
            html += '<p class="ala-activity-instruction">' +
                escapeHtml(str('flashcards_instructions', 'Tap each card to reveal the answer.')) + '</p>';

            html += '<div class="ala-flashcards-progress">';
            html += '<div class="ala-flashcards-dots">';
            for (var d = 0; d < cards.length; d++) {
                var dotClass = 'ala-fc-dot';
                if (d < currentCardIdx) {
                    dotClass += ' ala-fc-dot-done';
                } else if (d === currentCardIdx) {
                    dotClass += ' ala-fc-dot-active';
                }
                html += '<div class="' + dotClass + '" aria-hidden="true"></div>';
            }
            html += '</div>';
            html += '<span class="ala-fc-counter">' + (currentCardIdx + 1) + ' / ' + cards.length + '</span>';
            html += '</div>';

            var card = cards[currentCardIdx];
            html += '<div class="ala-flashcard-container">';
            html += '<div class="ala-flashcard' + (flipped ? ' ala-flipped' : '') +
                '" id="ala-flashcard"' + (flipped ? '' : ' role="button" tabindex="0" aria-label="' +
                escapeHtml(str('flashcard_tap', 'Tap to reveal answer')) + '"') + '>';
            html += '<div class="ala-flashcard-face ala-flashcard-front">';
            html += '<div class="ala-flashcard-number">' + (currentCardIdx + 1) + '</div>';
            html += '<div class="ala-flashcard-label">' +
                escapeHtml(str('flashcard_front', 'Question')) + '</div>';
            html += '<div class="ala-flashcard-text">' + escapeHtml(card.front) + '</div>';
            html += '<div class="ala-flashcard-hint">';
            html += '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" ' +
                'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 15l-2 5L9 ' +
                '9l11 4-5 2z"/><path d="M14.25 14.25L18 10"/></svg> ';
            html += escapeHtml(str('flashcard_tap', 'Tap to reveal answer'));
            html += '</div>';
            html += '</div>';
            html += '<div class="ala-flashcard-face ala-flashcard-back">';
            html += '<div class="ala-flashcard-back-icon">';
            html += '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" ' +
                'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" ' +
                'r="10"/><path d="m9 12 2 2 4-4"/></svg>';
            html += '</div>';
            html += '<div class="ala-flashcard-label">' +
                escapeHtml(str('flashcard_back', 'Answer')) + '</div>';
            html += '<div class="ala-flashcard-text">' + escapeHtml(card.back) + '</div>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            if (flipped) {
                var isLast = currentCardIdx >= cards.length - 1;
                html += '<div class="ala-flashcard-actions ala-fc-actions-appear">';
                html += '<button type="button" class="ala-btn ala-btn-primary ala-fc-next-btn" ' +
                    'id="ala-flashcard-next">';
                if (isLast) {
                    html += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
                        'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 ' +
                        '11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg> ';
                    html += escapeHtml(str('flashcard_done', 'All Done!'));
                } else {
                    html += escapeHtml(str('next_card_btn', 'Next Card'));
                    html += ' <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
                        'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 ' +
                        '6-6-6-6"/></svg>';
                }
                html += '</button>';
                html += '</div>';
            }

            html += '</div>';
            container.innerHTML = html;

            var flashcardEl = document.getElementById('ala-flashcard');
            if (flashcardEl && !flipped) {
                onActivate(flashcardEl, function() {
                    if (animatingOut) {
                        return;
                    }
                    flipped = true;
                    playSound('flip');
                    flashcardEl.classList.add('ala-flipped');
                    setTimeout(function() {
                        playSound('cardDone');
                    }, 400);
                    setTimeout(function() {
                        renderState();
                    }, 500);
                });
            }

            var nextBtn = document.getElementById('ala-flashcard-next');
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if (animatingOut) {
                        return;
                    }
                    animatingOut = true;
                    var cardContainer = container.querySelector('.ala-flashcard-container');
                    if (cardContainer) {
                        cardContainer.classList.add('ala-fc-slide-out');
                    }
                    playSound('swoosh');

                    setTimeout(function() {
                        animatingOut = false;
                        if (currentCardIdx < cards.length - 1) {
                            currentCardIdx++;
                            flipped = false;
                            renderState();
                            var newContainer = container.querySelector('.ala-flashcard-container');
                            if (newContainer) {
                                newContainer.classList.add('ala-fc-slide-in');
                                setTimeout(function() {
                                    newContainer.classList.remove('ala-fc-slide-in');
                                }, 350);
                            }
                        } else {
                            playSound('correct');
                            showFeedback(true, str('feedback_welldone', 'Well done!'));
                            progress[currentIndex] = true;
                            offerNext();
                        }
                    }, 300);
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: TRUE/FALSE SWIPE
    // ===================================================================
    /**
     * Render the true/false swipe activity.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderTrueFalseSwipe(activity, container) {
        var statements = activity.statements || [];
        var currentStmtIdx = 0;
        var correctCount = 0;
        var showingExplanation = false;
        var lastAnswerCorrect = false;
        var lastChosenTrue = null; // V1.5.13: track which button the student pressed

        /**
         * Draw the current statement.
         *
         * @return {void}
         */
        function renderState() {
            var html = '<div class="ala-activity ala-truefalseswipe">';
            html += '<span class="ala-activity-type-badge">' +
                escapeHtml(activityTypeLabel('truefalseswipe')) + '</span>';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) {
                html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            }
            html += '<p class="ala-activity-instruction">' +
                escapeHtml(str('truefalseswipe_instructions', 'Decide whether each statement is true or false.')) +
                '</p>';

            html += '<div class="ala-tfs-progress">';
            html += '<div class="ala-columnsort-progress-bar"><div class="ala-columnsort-progress-fill" style="width:' +
                (currentStmtIdx / statements.length * 100) + '%"></div></div>';
            html += '<span>' + currentStmtIdx + ' / ' + statements.length + '</span>';
            html += '</div>';

            if (currentStmtIdx < statements.length) {
                var stmt = statements[currentStmtIdx];
                html += '<div class="ala-tfs-card-container">';
                html += '<div class="ala-tfs-card ala-animate-slide-in" id="ala-tfs-card">';
                html += '<div class="ala-tfs-statement">' + escapeHtml(stmt.text) + '</div>';

                if (showingExplanation) {
                    // V1.5.23 BUG-TF-FEEDBACK-POS: Correct/Incorrect label moved INSIDE the
                    // card above the explanation (was injected between the True/False buttons
                    // in the buttons row, so it appeared between the option boxes  -  wrong).
                    html += '<div class="ala-tfs-mid-result ' +
                        (lastAnswerCorrect ? 'ala-tfs-result-correct' : 'ala-tfs-result-incorrect') +
                        '" role="status" aria-live="polite">';
                    if (lastAnswerCorrect) {
                        html += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
                            'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 ' +
                            '6 9 17l-5-5"/></svg>';
                        html += escapeHtml(str('correct', 'Correct!'));
                    } else {
                        html += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
                            'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 ' +
                            '6 6 18"/><path d="m6 6 12 12"/></svg>';
                        html += escapeHtml(str('incorrect', 'Incorrect!'));
                    }
                    html += '</div>';
                    html += '<div class="ala-tfs-explanation">';
                    html += '<div class="ala-tfs-explanation-answer">';
                    html += '<span class="ala-tfs-answer-label">' +
                        escapeHtml(str('truefalse_answer', 'Answer')) + ': </span>';
                    html += (stmt.correct
                        ? '<span class="ala-tfs-true-badge">' +
                            escapeHtml(str('truefalse_true', 'True')) + '</span>'
                        : '<span class="ala-tfs-false-badge">' +
                            escapeHtml(str('truefalse_false', 'False')) + '</span>');
                    html += '</div>';
                    html += '<p class="ala-tfs-explanation-text">' + escapeHtml(stmt.explanation) + '</p>';
                    html += '</div>';
                }

                html += '</div>';
                html += '</div>';

                if (!showingExplanation) {
                    // Unanswered: show normal True/False buttons
                    html += '<div class="ala-tfs-buttons">';
                    html += '<button type="button" class="ala-btn ala-tfs-btn-false" id="ala-tfs-false">';
                    html += '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" ' +
                        'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 ' +
                        '18"/><path d="m6 6 12 12"/></svg>';
                    html += ' ' + escapeHtml(str('truefalse_false', 'False'));
                    html += '</button>';
                    html += '<button type="button" class="ala-btn ala-tfs-btn-true" id="ala-tfs-true">';
                    html += '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" ' +
                        'stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 ' +
                        '17l-5-5"/></svg>';
                    html += ' ' + escapeHtml(str('truefalse_true', 'True'));
                    html += '</button>';
                    html += '</div>';
                } else {
                    // V1.5.13: After answering, keep both buttons visible in result state
                    // so the student can see exactly which option was correct/incorrect.
                    var stmtCorrectVal = stmt.correct === true || stmt.correct === 'true';
                    var falseCorrect = !stmtCorrectVal;
                    var falseChosen = (lastChosenTrue === false);
                    var trueCorrect = stmtCorrectVal;
                    var trueChosen = (lastChosenTrue === true);

                    var falseBtnCls = 'ala-btn ala-tfs-btn-false ala-tfs-btn-result';
                    var trueBtnCls = 'ala-btn ala-tfs-btn-true ala-tfs-btn-result';
                    var falseIconHtml = '';
                    var trueIconHtml = '';

                    if (falseCorrect) {
                        falseBtnCls += ' ala-tfs-btn-result-correct';
                        // Tick SVG
                        falseIconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" ' +
                            'fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" ' +
                            'stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                    } else if (falseChosen) {
                        falseBtnCls += ' ala-tfs-btn-result-incorrect';
                        // Cross SVG
                        falseIconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" ' +
                            'fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" ' +
                            'stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
                    } else {
                        falseBtnCls += ' ala-tfs-btn-result-neutral';
                    }

                    if (trueCorrect) {
                        trueBtnCls += ' ala-tfs-btn-result-correct';
                        trueIconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" ' +
                            'fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" ' +
                            'stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                    } else if (trueChosen) {
                        trueBtnCls += ' ala-tfs-btn-result-incorrect';
                        trueIconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" ' +
                            'fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" ' +
                            'stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
                    } else {
                        trueBtnCls += ' ala-tfs-btn-result-neutral';
                    }

                    // V1.5.23: 2-button result row (mid-result label moved into card above).
                    html += '<div class="ala-tfs-buttons ala-tfs-buttons-result">';
                    html += '<button type="button" class="' + falseBtnCls + '" disabled>';
                    if (falseIconHtml) {
                        html += falseIconHtml + ' ';
                    }
                    html += escapeHtml(str('truefalse_false', 'False'));
                    html += '</button>';
                    html += '<button type="button" class="' + trueBtnCls + '" disabled>';
                    if (trueIconHtml) {
                        html += trueIconHtml + ' ';
                    }
                    html += escapeHtml(str('truefalse_true', 'True'));
                    html += '</button>';
                    html += '</div>';

                    html += '<div class="ala-tfs-buttons ala-tfs-continue-row">';
                    html += '<button type="button" class="ala-btn ala-btn-primary" id="ala-tfs-continue">';
                    html += escapeHtml(currentStmtIdx < statements.length - 1
                        ? str('next_btn', 'Next')
                        : str('flashcard_done', 'Done'));
                    html += ' <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
                        'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 ' +
                        '6-6-6-6"/></svg>';
                    html += '</button>';
                    html += '</div>';
                }
            }

            html += '</div>';
            container.innerHTML = html;

            /**
             * Record the student's true/false answer and show the explanation.
             *
             * @param {Boolean} chosenTrue True when the student pressed True.
             * @return {void}
             */
            function handleAnswer(chosenTrue) {
                var stmt = statements[currentStmtIdx];
                var stmtCorrect = stmt.correct === true || stmt.correct === 'true';
                var correct = chosenTrue === stmtCorrect;
                lastAnswerCorrect = correct;
                lastChosenTrue = chosenTrue; // V1.5.13

                if (correct) {
                    playSound('correct');
                    correctCount++;
                } else {
                    registerWrongAnswer();
                    playSound('wrong');
                    var card = document.getElementById('ala-tfs-card');
                    if (card) {
                        card.classList.add('ala-shake');
                    }
                }

                showingExplanation = true;
                renderState();
            }

            var falseBtn = document.getElementById('ala-tfs-false');
            if (falseBtn) {
                falseBtn.addEventListener('click', function() {
                    handleAnswer(false);
                });
            }
            var trueBtn = document.getElementById('ala-tfs-true');
            if (trueBtn) {
                trueBtn.addEventListener('click', function() {
                    handleAnswer(true);
                });
            }

            var continueBtn = document.getElementById('ala-tfs-continue');
            if (continueBtn) {
                continueBtn.addEventListener('click', function() {
                    currentStmtIdx++;
                    showingExplanation = false;
                    lastChosenTrue = null; // V1.5.13: reset for next question
                    if (currentStmtIdx >= statements.length) {
                        var allCorrect = correctCount === statements.length;
                        if (!allCorrect) {
                            hadWrongAnswers = true;
                        }
                        // BUG-ALA-SOUND fix: previously called showFeedback(allCorrect, ...)
                        // which played playSound('wrong') whenever any answer was incorrect,
                        // even though the student has just COMPLETED the activity. At completion
                        // the sound should always be positive. Always pass true so the 'correct'
                        // sound plays and the green checkmark is shown regardless of accuracy.
                        showFeedback(true, str('truefalse_activity_complete', 'Activity done.'));
                        progress[currentIndex] = true;
                        offerNext();
                    } else {
                        renderState();
                    }
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: FILL IN THE BLANK
    // ===================================================================
    /**
     * Render the fill in the blank activity.
     *
     * @param {Object} activity The activity data.
     * @param {Element} container The element to render into.
     * @return {void}
     */
    function renderFillInBlank(activity, container) {
        var blanks = activity.blanks;
        var allWords = shuffle(blanks.map(function(b) {
            return b.answer;
        }).concat(activity.distractors || []));
        var filledBlanks = {};
        var selectedBlankPos = null;

        /**
         * Draw the current state of the passage and word bank.
         *
         * @return {void}
         */
        function renderState() {
            var html = '<div class="ala-activity ala-fillinblank">';
            html += '<span class="ala-activity-type-badge">' +
                escapeHtml(activityTypeLabel('fillinblank')) + '</span>';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) {
                html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            }
            html += '<p class="ala-activity-instruction">' +
                escapeHtml(str('fillinblank_instructions', 'Drag or tap a word to fill each blank.')) + '</p>';

            // Render text with blanks
            html += '<div class="ala-fib-text">';
            // Escape the passage FIRST, then substitute the ___N___ markers. The markers
            // contain no HTML-special characters so they survive escaping unchanged.
            var text = escapeHtml(activity.text);
            for (var i = blanks.length; i >= 1; i--) {
                var placeholder = '___' + i + '___';
                var pos = i - 1;
                var filled = filledBlanks[pos];
                var blankHtml;
                var blankAttrs = ' role="button" tabindex="0" aria-label="' +
                    escapeHtml(str('js_a11y_blank', 'Blank')) + ' ' + (pos + 1) + '"';
                if (filled) {
                    var isCorrect = filled === blanks[pos].answer;
                    var checkedClass = '';
                    if (filledBlanks[pos + '_checked']) {
                        checkedClass = isCorrect ? ' ala-correct' : ' ala-incorrect';
                    }
                    blankHtml = '<span class="ala-fib-blank ala-fib-filled' + checkedClass +
                        (selectedBlankPos === pos ? ' ala-selected' : '') +
                        '" data-pos="' + pos + '"' + blankAttrs + '>' + escapeHtml(filled) + '</span>';
                } else {
                    blankHtml = '<span class="ala-fib-blank ala-fib-empty' +
                        (selectedBlankPos === pos ? ' ala-selected' : '') +
                        '" data-pos="' + pos + '"' + blankAttrs + '>' + (pos + 1) + '</span>';
                }
                text = replaceLiteral(text, placeholder, blankHtml);
            }
            html += '<p class="ala-fib-passage">' + text + '</p>';
            html += '</div>';

            // Word bank
            html += '<div class="ala-fib-wordbank">';
            html += '<div class="ala-fib-wordbank-label">' +
                escapeHtml(str('fillinblank_wordbank', 'Word Bank')) + '</div>';
            html += '<div class="ala-fib-words">';
            var usedWords = {};
            for (var key in filledBlanks) {
                if (key.indexOf('_checked') === -1 && filledBlanks[key]) {
                    usedWords[filledBlanks[key]] = true;
                }
            }
            allWords.forEach(function(word) {
                var isUsed = usedWords[word] === true;
                html += '<button type="button" class="ala-fib-word' + (isUsed ? ' ala-fib-word-used' : '') +
                    '" data-word="' + escapeAttr(word) + '"' + (isUsed ? ' disabled' : '') + '>' +
                    escapeHtml(word) + '</button>';
            });
            html += '</div>';
            html += '</div>';

            // Check button
            var allFilled = true;
            for (var b = 0; b < blanks.length; b++) {
                if (!filledBlanks[b]) {
                    allFilled = false; break;
                }
            }
            if (allFilled) {
                html += '<div class="ala-fib-actions">';
                html += '<button type="button" class="ala-btn ala-btn-primary" id="ala-fib-check">' +
                    escapeHtml(str('check_btn', 'Check')) + '</button>';
                html += '</div>';
            }

            html += '</div>';
            container.innerHTML = html;

            // Bind blank clicks
            var blankEls = container.querySelectorAll('.ala-fib-blank');
            blankEls.forEach(function(el) {
                onActivate(el, function() {
                    var pos = parseInt(el.getAttribute('data-pos'), 10);
                    if (filledBlanks[pos + '_checked']) {
                        return;
                    }
                    if (filledBlanks[pos]) {
                        delete filledBlanks[pos];
                        selectedBlankPos = pos;
                        renderState();
                    } else {
                        selectedBlankPos = pos;
                        renderState();
                    }
                });
            });

            // Bind word bank clicks
            var wordEls = container.querySelectorAll('.ala-fib-word:not([disabled])');
            wordEls.forEach(function(el) {
                el.addEventListener('click', function() {
                    var word = el.getAttribute('data-word');
                    if (selectedBlankPos !== null) {
                        filledBlanks[selectedBlankPos] = word;
                        selectedBlankPos = null;
                        renderState();
                    } else {
                        for (var b = 0; b < blanks.length; b++) {
                            if (!filledBlanks[b]) {
                                filledBlanks[b] = word;
                                break;
                            }
                        }
                        renderState();
                    }
                });
            });

            // Bind check button
            var checkBtn = document.getElementById('ala-fib-check');
            if (checkBtn) {
                checkBtn.addEventListener('click', function() {
                    var allCorrect = true;
                    for (var b = 0; b < blanks.length; b++) {
                        filledBlanks[b + '_checked'] = true;
                        if (filledBlanks[b] !== blanks[b].answer) {
                            allCorrect = false;
                        }
                    }
                    if (allCorrect) {
                        showFeedback(true, str('feedback_welldone', 'Well done!'));
                        progress[currentIndex] = true;
                        renderState();
                        offerNext();
                    } else {
                        showFeedback(false, str('feedback_incorrect', 'Incorrect!'));
                        renderState();
                        setTimeout(function() {
                            for (var b = 0; b < blanks.length; b++) {
                                if (filledBlanks[b] !== blanks[b].answer) {
                                    delete filledBlanks[b];
                                }
                                delete filledBlanks[b + '_checked'];
                            }
                            selectedBlankPos = null;
                            renderState();
                        }, 1500);
                    }
                });
            }
        }

        renderState();
    }

    // ========== UTILITY ==========

    /**
     * Shallow equality test for two arrays.
     *
     * @param {Array} a First array.
     * @param {Array} b Second array.
     * @return {Boolean} True when both arrays hold the same values in the same order.
     */
    function arraysEqual(a, b) {
        if (a.length !== b.length) {
            return false;
        }
        for (var i = 0; i < a.length; i++) {
            if (a[i] !== b[i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Escape a value for safe interpolation into HTML, including attribute values.
     *
     * Numbers and false are stringified rather than dropped, so escapeHtml(0)
     * returns '0' and not the empty string.
     *
     * @param {*} text The value to escape.
     * @return {String} The escaped text; empty string for null/undefined/''.
     */
    function escapeHtml(text) {
        if (text === null || text === undefined || text === '') {
            return '';
        }
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ===================================================================
    // RENDER ACTIVITY (dispatcher)
    // ===================================================================
    /**
     * Icons used by the narration controls, keyed by control name.
     *
     * @type {Object}
     */
    var VOICEOVER_ICONS = {
        play: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' +
            'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'stroke-linejoin="round" aria-hidden="true" focusable="false">' +
            '<polygon points="6 3 20 12 6 21 6 3"/></svg>',
        stop: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' +
            'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'stroke-linejoin="round" aria-hidden="true" focusable="false">' +
            '<rect x="6" y="6" width="12" height="12" rx="2"/></svg>',
        unmuted: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' +
            'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'stroke-linejoin="round" aria-hidden="true" focusable="false">' +
            '<path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/>' +
            '<path d="M19 5a9 9 0 0 1 0 14"/></svg>',
        muted: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' +
            'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'stroke-linejoin="round" aria-hidden="true" focusable="false">' +
            '<path d="M11 5 6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/>' +
            '<line x1="16" y1="9" x2="22" y2="15"/></svg>'
    };

    /**
     * Enable or disable one narration control.
     *
     * @param {String} id Element id of the control.
     * @param {Boolean} enabled Whether the control should be usable.
     * @return {void}
     */
    function setVoiceoverControlState(id, enabled) {
        var btn = document.getElementById(id);
        if (btn) {
            btn.disabled = !enabled;
        }
    }

    /**
     * Reflect the current mute state on the mute control.
     *
     * @return {void}
     */
    function syncVoiceoverMuteButton() {
        var btn = document.getElementById('ala-voiceover-mute');
        if (!btn) {
            return;
        }
        var label = voiceoverMuted
            ? str('js_voiceover_unmute', 'Unmute narration')
            : str('js_voiceover_mute', 'Mute narration');
        btn.innerHTML = voiceoverMuted ? VOICEOVER_ICONS.muted : VOICEOVER_ICONS.unmuted;
        btn.setAttribute('title', label);
        btn.setAttribute('aria-label', label);
        btn.setAttribute('aria-pressed', voiceoverMuted ? 'true' : 'false');
        if (voiceoverMuted) {
            btn.classList.add('ala-vo-btn-active');
        } else {
            btn.classList.remove('ala-vo-btn-active');
        }
    }

    /**
     * Mute or unmute narration, taking effect immediately and for later activities.
     *
     * @return {void}
     */
    function toggleVoiceoverMute() {
        voiceoverMuted = !voiceoverMuted;
        if (voiceoverGain) {
            voiceoverGain.gain.value = voiceoverMuted ? 0 : 1;
        }
        syncVoiceoverMuteButton();
    }

    /**
     * Stop any playing voiceover narration, leaving the controls in place so
     * that the narration can be played again.
     *
     * @return {void}
     */
    function stopVoiceover() {
        if (currentVoiceoverSrc) {
            try {
                currentVoiceoverSrc.stop();
            } catch (e) {
                // Source already stopped or never started.
            }
            currentVoiceoverSrc = null;
        }
        setVoiceoverControlState('ala-voiceover-stop', false);
        setVoiceoverControlState('ala-voiceover-play', currentVoiceoverBuffer !== null);
    }

    /**
     * Play the decoded narration for the current activity from the beginning.
     *
     * @return {void}
     */
    function playVoiceover() {
        if (!currentVoiceoverBuffer) {
            return;
        }
        stopVoiceover();
        try {
            var ctx = getAudioCtx();
            if (!voiceoverGain) {
                voiceoverGain = ctx.createGain();
                voiceoverGain.connect(ctx.destination);
            }
            voiceoverGain.gain.value = voiceoverMuted ? 0 : 1;

            var audioSrc = ctx.createBufferSource();
            audioSrc.buffer = currentVoiceoverBuffer;
            audioSrc.connect(voiceoverGain);
            audioSrc.onended = function() {
                currentVoiceoverSrc = null;
                setVoiceoverControlState('ala-voiceover-stop', false);
                setVoiceoverControlState('ala-voiceover-play', true);
            };
            currentVoiceoverSrc = audioSrc;
            audioSrc.start(0);
            setVoiceoverControlState('ala-voiceover-stop', true);
            setVoiceoverControlState('ala-voiceover-play', true);
        } catch (e) {
            // Audio is not critical to the activity.
        }
    }

    /**
     * Build one icon control for the narration control group.
     *
     * @param {String} id Element id to give the control.
     * @param {String} icon Inline SVG markup for the control.
     * @param {String} label Accessible name and tooltip text.
     * @param {Function} handler Click handler.
     * @return {HTMLElement} The control.
     */
    function buildVoiceoverButton(id, icon, label, handler) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = id;
        btn.className = 'ala-vo-btn';
        btn.innerHTML = icon;
        btn.setAttribute('title', label);
        btn.setAttribute('aria-label', label);
        btn.addEventListener('click', handler);
        return btn;
    }

    /**
     * Build the narration control group shown in the corner of an activity.
     *
     * @return {HTMLElement} The control group.
     */
    function buildVoiceoverControls() {
        var group = document.createElement('div');
        group.id = 'ala-voiceover-controls';
        group.className = 'ala-voiceover-controls';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', str('js_voiceover_controls', 'Narration controls'));

        group.appendChild(buildVoiceoverButton(
            'ala-voiceover-play',
            VOICEOVER_ICONS.play,
            str('js_voiceover_play', 'Play narration'),
            playVoiceover
        ));
        group.appendChild(buildVoiceoverButton(
            'ala-voiceover-mute',
            VOICEOVER_ICONS.unmuted,
            str('js_voiceover_mute', 'Mute narration'),
            toggleVoiceoverMute
        ));

        var stopBtn = buildVoiceoverButton(
            'ala-voiceover-stop',
            VOICEOVER_ICONS.stop,
            str('js_voiceover_stop', 'Stop narration'),
            stopVoiceover
        );
        stopBtn.setAttribute('aria-label', str('js_voiceover_stop_label',
            'Stop the spoken narration for this activity'));
        group.appendChild(stopBtn);

        return group;
    }

    /**
     * Render the activity at the given index into the player (or preview) container.
     *
     * @param {Number} index Index into the activities array.
     * @return {void}
     */
    function renderActivity(index) {
        // V1.5.43 FIX-WELLDONE-OVERLAP: always clear the feedback toast before rendering a new activity
        // so the "Well done!" green toast never bleeds over the next activity's UI.
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
            feedbackTimer = null;
        }
        hideFeedbackEl(document.getElementById(isPreviewMode ? 'ala-preview-feedback' : 'ala-player-feedback'));
        clearNext();
        resetActivityState(activities[index]);

        // V1.6.8 FIX-AUDIO-BLEED: Stop any voiceover from the previous activity before
        // starting a new one. Without this, the old TTS audio kept playing through the
        // transition and overlapped the next activity's narration. The decoded buffer
        // is dropped too, so the play control cannot replay the wrong activity.
        stopVoiceover();
        currentVoiceoverBuffer = null;

        var containerId = isPreviewMode ? 'ala-preview-content' : 'ala-player-content';
        var container = document.getElementById(containerId);
        if (!container || !activities[index]) {
            return;
        }

        container.style.display = 'block';
        var activity = activities[index];

        if (!isPreviewMode) {
            updateProgressBar();
        }

        container.classList.remove('ala-animate-slide-in');
        void container.offsetWidth;
        container.classList.add('ala-animate-slide-in');

        switch (activity.type) {
            case 'cardselect':
                renderCardSelect(activity, container);
                break;
            case 'columnsort':
                renderColumnSort(activity, container);
                break;
            case 'categorysort':
                renderCategorySort(activity, container);
                break;
            case 'matching':
                renderMatching(activity, container);
                break;
            case 'ordering':
                renderOrdering(activity, container);
                break;
            case 'flashcards':
                renderFlashcards(activity, container);
                break;
            case 'truefalseswipe':
                renderTrueFalseSwipe(activity, container);
                break;
            case 'fillinblank':
                renderFillInBlank(activity, container);
                break;
            default:
                container.innerHTML = '<p>' +
                    escapeHtml(str('js_err_unknowntype', 'Unknown activity type')) + ': ' +
                    escapeHtml(activity.type) + '</p>';
        }

        focusFirst(container);

        // Narration, when this activity has any: the controls sit in the corner of
        // the activity card so playback can always be replayed, muted or stopped.
        if (activity.audioData && !isPreviewMode) {
            var controls = buildVoiceoverControls();
            var card = container.querySelector('.ala-activity') || container;
            card.insertBefore(controls, card.firstChild);
            syncVoiceoverMuteButton();
            setVoiceoverControlState('ala-voiceover-play', false);
            setVoiceoverControlState('ala-voiceover-stop', false);

            try {
                var ctx = getAudioCtx();
                var raw = atob(activity.audioData);
                var buf = new ArrayBuffer(raw.length);
                var view = new Uint8Array(buf);
                for (var bi = 0; bi < raw.length; bi++) {
                    view[bi] = raw.charCodeAt(bi);
                }
                ctx.decodeAudioData(buf, function(decoded) {
                    currentVoiceoverBuffer = decoded;
                    playVoiceover();
                }, function() {
                    // Decode error: skip narration silently.
                });
            } catch (e) {
                // Audio is not critical to the activity.
            }
        }
    }

    // ========== TEACHER EDIT MODE ==========

    /**
     * Build the card select specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields.
     */
    function buildEditFieldsCardSelect(activity) {
        var html = '';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_cards', 'Cards')) + '</label>';
        activity.cards.forEach(function(card, ci) {
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_card', 'Card') + ' ' + (ci + 1) +
                    (ci === activity.correctIndex ? ' ' + str('js_edit_correctanswer', '(correct answer)') : '')) +
                '</div>';
            html += '<input type="text" class="ala-edit-input ala-edit-card-label" data-ci="' + ci + '" placeholder="' +
                escapeAttr(str('js_edit_ph_label', 'Label')) + '" value="' + escapeAttr(card.label || '') + '" />';
            html += '<input type="text" class="ala-edit-input ala-edit-card-desc" data-ci="' + ci + '" placeholder="' +
                escapeAttr(str('js_edit_ph_description', 'Description')) + '" value="' + escapeAttr(card.description || '') +
                '" />';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Build the column sort specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields.
     */
    function buildEditFieldsColumnSort(activity) {
        var html = '';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_colaname', 'Column A Name')) + '</label>';
        html += '<input type="text" class="ala-edit-input" id="ala-edit-colA" value="' + escapeAttr(activity.columnA || '') +
            '" />';
        html += '</div>';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_colbname', 'Column B Name')) + '</label>';
        html += '<input type="text" class="ala-edit-input" id="ala-edit-colB" value="' + escapeAttr(activity.columnB || '') +
            '" />';
        html += '</div>';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_items', 'Items')) + '</label>';
        activity.items.forEach(function(item, ii) {
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_item', 'Item') + ' ' + (ii + 1) + ' (' +
                    str('js_edit_belongsto', 'belongs to') + ': ' + item.column + ')') +
                '</div>';
            html += '<input type="text" class="ala-edit-input ala-edit-sort-item" data-ii="' + ii + '" value="' +
                escapeAttr(item.text || '') + '" />';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Build the category sort specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields.
     */
    function buildEditFieldsCategorySort(activity) {
        var html = '';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_categories', 'Categories')) + '</label>';
        activity.categories.forEach(function(cat, ci) {
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_category', 'Category') + ' ' + (ci + 1)) + '</div>';
            html += '<input type="text" class="ala-edit-input ala-edit-category" data-ci="' + ci + '" value="' +
                escapeAttr(cat) + '" />';
            html += '</div>';
        });
        html += '</div>';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_items', 'Items')) + '</label>';
        activity.items.forEach(function(item, ii) {
            // Resolve numeric index  ->  name; string category displayed directly.
            var catLabel;
            if (typeof item.category === 'number') {
                catLabel = activity.categories[item.category] !== undefined
                    ? activity.categories[item.category]
                    : String(item.category);
            } else if (item.category === null || item.category === undefined) {
                catLabel = '';
            } else {
                catLabel = String(item.category);
            }
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_item', 'Item') + ' ' + (ii + 1) + ' (' +
                    str('js_edit_belongsto', 'belongs to') + ': ' + catLabel + ')') +
                '</div>';
            html += '<input type="text" class="ala-edit-input ala-edit-catitem" data-ii="' + ii + '" value="' +
                escapeAttr(item.text || '') + '" />';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Build the matching pairs specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields.
     */
    function buildEditFieldsMatching(activity) {
        var html = '';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_pairs', 'Matching Pairs')) + '</label>';
        activity.pairs.forEach(function(pair, pi) {
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_pair', 'Pair') + ' ' + (pi + 1)) + '</div>';
            html += '<div class="ala-edit-pair-row">';
            html += '<input type="text" class="ala-edit-input ala-edit-pair-left" data-pi="' + pi + '" placeholder="' +
                escapeAttr(str('js_edit_ph_left', 'Left')) + '" value="' + escapeAttr(pair.left || '') + '" />';
            html += '<span class="ala-edit-pair-arrow">&#8596;</span>';
            html += '<input type="text" class="ala-edit-input ala-edit-pair-right" data-pi="' + pi + '" placeholder="' +
                escapeAttr(str('js_edit_ph_right', 'Right')) + '" value="' + escapeAttr(pair.right || '') + '" />';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Build the ordering specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields.
     */
    function buildEditFieldsOrdering(activity) {
        var html = '';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_orderitems', 'Items (in correct order)')) +
            '</label>';
        activity.items.forEach(function(item, ii) {
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_step', 'Step') + ' ' + (ii + 1)) + '</div>';
            html += '<input type="text" class="ala-edit-input ala-edit-order-item" data-ii="' + ii + '" value="' +
                escapeAttr(item) + '" />';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Build the flashcards specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields.
     */
    function buildEditFieldsFlashcards(activity) {
        var html = '';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_flashcards', 'Flashcards')) + '</label>';
        activity.cards.forEach(function(card, ci) {
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_card', 'Card') + ' ' + (ci + 1)) + '</div>';
            html += '<input type="text" class="ala-edit-input ala-edit-fc-front" data-ci="' + ci + '" placeholder="' +
                escapeAttr(str('js_edit_ph_front', 'Front')) + '" value="' + escapeAttr(card.front || '') + '" />';
            html += '<input type="text" class="ala-edit-input ala-edit-fc-back" data-ci="' + ci + '" placeholder="' +
                escapeAttr(str('js_edit_ph_back', 'Back')) + '" value="' + escapeAttr(card.back || '') + '" />';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Build the true/false specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields.
     */
    function buildEditFieldsTrueFalseSwipe(activity) {
        var html = '';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_statements', 'Statements')) + '</label>';
        activity.statements.forEach(function(stmt, si) {
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_statement', 'Statement') + ' ' + (si + 1) + ' (' +
                    (stmt.correct ? str('truefalse_true', 'True') : str('truefalse_false', 'False')) + ')') +
                '</div>';
            html += '<input type="text" class="ala-edit-input ala-edit-tfs-text" data-si="' + si + '" placeholder="' +
                escapeAttr(str('js_edit_ph_statement', 'Statement')) + '" value="' + escapeAttr(stmt.text || '') + '" />';
            html += '<input type="text" class="ala-edit-input ala-edit-tfs-explanation" data-si="' + si + '" placeholder="' +
                escapeAttr(str('js_edit_ph_explanation', 'Explanation')) + '" value="' + escapeAttr(stmt.explanation || '') +
                '" />';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Build the fill in the blank specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields.
     */
    function buildEditFieldsFillInBlank(activity) {
        var html = '';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' +
            escapeHtml(str('js_edit_lbl_sentence', 'Sentence (use ___1___, ___2___ etc for blanks)')) + '</label>';
        html += '<textarea class="ala-edit-input" id="ala-edit-fib-text" rows="3">' + escapeHtml(activity.text || '') +
            '</textarea>';
        html += '</div>';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_blankanswers', 'Blank Answers')) +
            '</label>';
        activity.blanks.forEach(function(blank, bi) {
            html += '<div class="ala-edit-subgroup">';
            html += '<div class="ala-edit-subgroup-header">' +
                escapeHtml(str('js_edit_blank', 'Blank') + ' ' + (bi + 1)) + '</div>';
            html += '<input type="text" class="ala-edit-input ala-edit-fib-answer" data-bi="' + bi + '" value="' +
                escapeAttr(blank.answer || '') + '" />';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Build the activity-type specific fields of the teacher edit form.
     *
     * @param {Object} activity The activity being edited.
     * @return {String} Escaped HTML for the fields; empty for an unknown type.
     */
    function buildEditTypeFields(activity) {
        switch (activity.type) {
            case 'cardselect':
                return buildEditFieldsCardSelect(activity);
            case 'columnsort':
                return buildEditFieldsColumnSort(activity);
            case 'categorysort':
                return buildEditFieldsCategorySort(activity);
            case 'matching':
                return buildEditFieldsMatching(activity);
            case 'ordering':
                return buildEditFieldsOrdering(activity);
            case 'flashcards':
                return buildEditFieldsFlashcards(activity);
            case 'truefalseswipe':
                return buildEditFieldsTrueFalseSwipe(activity);
            case 'fillinblank':
                return buildEditFieldsFillInBlank(activity);
            default:
                return '';
        }
    }

    /**
     * Open the inline teacher edit form for one activity.
     *
     * @param {Number} index Index into the activities array.
     * @return {void}
     */
    function openTeacherEdit(index) {
        var activity = activities[index];
        if (!activity) {
            return;
        }

        clearUndoDelete();

        var manageSection = document.getElementById('ala-manage-section');
        var editSection = document.getElementById('ala-teacher-edit');
        var editContent = document.getElementById('ala-edit-content');

        if (manageSection) {
            manageSection.style.display = 'none';
        }
        if (editSection) {
            editSection.style.display = 'block';
        }

        var typeLabel = activityTypeLabel(activity.type);

        var editLabelEl = document.getElementById('ala-edit-label');
        if (editLabelEl) {
            editLabelEl.textContent = str('js_edit_heading', 'Editing: ') + typeLabel +
                ' - ' + (activity.title || '');
        }

        if (!editContent) {
            return;
        }

        var html = '<div class="ala-edit-form">';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_title', 'Title')) + '</label>';
        html += '<input type="text" class="ala-edit-input" id="ala-edit-title" value="' + escapeAttr(activity.title || '') + '" />';
        html += '</div>';

        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">' + escapeHtml(str('js_edit_lbl_question', 'Question')) + '</label>';
        html += '<input type="text" class="ala-edit-input" id="ala-edit-question" value="' + escapeAttr(activity.question || '') +
            '" />';
        html += '</div>';

        html += buildEditTypeFields(activity);

        html += '<div class="ala-edit-actions">';
        html += '<button type="button" id="ala-edit-save-btn" class="ala-btn ala-btn-primary">' +
            escapeHtml(str('js_edit_save', 'Save Changes')) + '</button>';
        html += '<button type="button" id="ala-edit-cancel-btn" class="ala-btn ala-btn-outline">' +
            escapeHtml(str('js_edit_cancel', 'Cancel')) + '</button>';
        html += '</div>';
        html += '</div>';

        editContent.innerHTML = html;

        // Bind save
        var saveBtn = document.getElementById('ala-edit-save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                saveActivityEdit(index);
            });
        }

        // Bind cancel
        var cancelBtn = document.getElementById('ala-edit-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                closeTeacherEdit();
            });
        }
    }

    /**
     * Read the teacher edit form back into the activity and save it.
     *
     * @param {Number} index Index into the activities array.
     * @return {void}
     */
    function saveActivityEdit(index) {
        var activity = JSON.parse(JSON.stringify(activities[index]));

        var titleEl = document.getElementById('ala-edit-title');
        if (titleEl) {
            activity.title = titleEl.value.trim();
        }

        var questionEl = document.getElementById('ala-edit-question');
        if (questionEl) {
            activity.question = questionEl.value.trim();
        }

        if (activity.type === 'cardselect') {
            var labelEls = document.querySelectorAll('.ala-edit-card-label');
            var descEls = document.querySelectorAll('.ala-edit-card-desc');
            labelEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'), 10);
                if (activity.cards[ci]) {
                    activity.cards[ci].label = el.value.trim();
                }
            });
            descEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'), 10);
                if (activity.cards[ci]) {
                    activity.cards[ci].description = el.value.trim();
                }
            });
        } else if (activity.type === 'columnsort') {
            var colAEl = document.getElementById('ala-edit-colA');
            var colBEl = document.getElementById('ala-edit-colB');
            if (colAEl) {
                activity.columnA = colAEl.value.trim();
            }
            if (colBEl) {
                activity.columnB = colBEl.value.trim();
            }

            var sortEls = document.querySelectorAll('.ala-edit-sort-item');
            sortEls.forEach(function(el) {
                var ii = parseInt(el.getAttribute('data-ii'), 10);
                if (activity.items[ii]) {
                    activity.items[ii].text = el.value.trim();
                }
            });
        } else if (activity.type === 'categorysort') {
            var catEls = document.querySelectorAll('.ala-edit-category');
            catEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'), 10);
                var oldCat = activity.categories[ci];
                var newCat = el.value.trim();
                activity.categories[ci] = newCat;
                activity.items.forEach(function(item) {
                    if (item.category === oldCat) {
                        item.category = newCat;
                    }
                });
            });
            var catItemEls = document.querySelectorAll('.ala-edit-catitem');
            catItemEls.forEach(function(el) {
                var ii = parseInt(el.getAttribute('data-ii'), 10);
                if (activity.items[ii]) {
                    activity.items[ii].text = el.value.trim();
                }
            });
        } else if (activity.type === 'matching') {
            var leftEls = document.querySelectorAll('.ala-edit-pair-left');
            var rightEls = document.querySelectorAll('.ala-edit-pair-right');
            leftEls.forEach(function(el) {
                var pi = parseInt(el.getAttribute('data-pi'), 10);
                if (activity.pairs[pi]) {
                    activity.pairs[pi].left = el.value.trim();
                }
            });
            rightEls.forEach(function(el) {
                var pi = parseInt(el.getAttribute('data-pi'), 10);
                if (activity.pairs[pi]) {
                    activity.pairs[pi].right = el.value.trim();
                }
            });
        } else if (activity.type === 'ordering') {
            var orderEls = document.querySelectorAll('.ala-edit-order-item');
            orderEls.forEach(function(el) {
                var ii = parseInt(el.getAttribute('data-ii'), 10);
                activity.items[ii] = el.value.trim();
            });
        } else if (activity.type === 'flashcards') {
            var fcFrontEls = document.querySelectorAll('.ala-edit-fc-front');
            var fcBackEls = document.querySelectorAll('.ala-edit-fc-back');
            fcFrontEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'), 10);
                if (activity.cards[ci]) {
                    activity.cards[ci].front = el.value.trim();
                }
            });
            fcBackEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'), 10);
                if (activity.cards[ci]) {
                    activity.cards[ci].back = el.value.trim();
                }
            });
        } else if (activity.type === 'truefalseswipe') {
            var tfsTextEls = document.querySelectorAll('.ala-edit-tfs-text');
            var tfsExpEls = document.querySelectorAll('.ala-edit-tfs-explanation');
            tfsTextEls.forEach(function(el) {
                var si = parseInt(el.getAttribute('data-si'), 10);
                if (activity.statements[si]) {
                    activity.statements[si].text = el.value.trim();
                }
            });
            tfsExpEls.forEach(function(el) {
                var si = parseInt(el.getAttribute('data-si'), 10);
                if (activity.statements[si]) {
                    activity.statements[si].explanation = el.value.trim();
                }
            });
        } else if (activity.type === 'fillinblank') {
            var fibTextEl = document.getElementById('ala-edit-fib-text');
            if (fibTextEl) {
                activity.text = fibTextEl.value.trim();
            }
            var fibAnswerEls = document.querySelectorAll('.ala-edit-fib-answer');
            fibAnswerEls.forEach(function(el) {
                var bi = parseInt(el.getAttribute('data-bi'), 10);
                if (activity.blanks[bi]) {
                    activity.blanks[bi].answer = el.value.trim();
                }
            });
        }

        var saveBtn = document.getElementById('ala-edit-save-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = str('js_edit_saving', 'Saving...');
        }

        ajaxCall('saveactivity', {
            activityindex: index,
            activityjson: JSON.stringify(activity)
        }, function(result) {
            if (result.ok) {
                activities[index] = activity;
                closeTeacherEdit();
                refreshActivityList();
            } else {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = str('js_edit_save', 'Save Changes');
                }
                notifyError(result.error || str('js_err_savefailed', 'Could not save your changes.'));
            }
        });
    }

    /**
     * Close the teacher edit form and return to the manage list.
     *
     * @return {void}
     */
    function closeTeacherEdit() {
        var manageSection = document.getElementById('ala-manage-section');
        var editSection = document.getElementById('ala-teacher-edit');
        var editContent = document.getElementById('ala-edit-content');

        if (editSection) {
            editSection.style.display = 'none';
        }
        if (manageSection) {
            manageSection.style.display = 'block';
        }
        if (editContent) {
            editContent.innerHTML = '';
        }
    }

    /**
     * Build one row of the teacher's activity list.
     *
     * @param {Object} act The activity data.
     * @param {Number} idx Zero based index of the activity.
     * @return {String} Escaped HTML for the row.
     */
    function buildPreviewItemHtml(act, idx) {
        var editLabel = escapeHtml(str('js_edit_activity', 'Edit activity'));
        var playLabel = escapeHtml(str('js_preview_activity', 'Preview activity'));
        // Naming the activity keeps the rows apart for anyone listening to the
        // list rather than looking at it.
        var deleteLabel = escapeHtml(strWithParam('js_delete_named', act.title));
        var onlyOne = activities.length <= 1;
        return '<div class="ala-preview-item ala-preview-clickable" data-activity-index="' + idx + '">' +
            '<span class="ala-preview-num">' + (idx + 1) + '</span>' +
            '<span class="ala-preview-type">' + escapeHtml(activityTypeLabel(act.type)) + '</span>' +
            '<span class="ala-preview-title">' + escapeHtml(act.title) + '</span>' +
            '<span class="ala-preview-edit" data-edit-index="' + idx + '" role="button" tabindex="0"' +
            ' title="' + editLabel + '" aria-label="' + editLabel + '">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' +
            'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'stroke-linejoin="round" aria-hidden="true">' +
            '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></span>' +
            '<span class="ala-preview-play" data-preview-index="' + idx + '" role="button" tabindex="0"' +
            ' title="' + playLabel + '" aria-label="' + playLabel + '">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' +
            'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'stroke-linejoin="round" aria-hidden="true"><polygon points="6 3 20 12 6 21 6 3"/></svg></span>' +
            '<span class="ala-preview-delete" data-delete-index="' + idx + '" role="button" tabindex="0"' +
            ' aria-disabled="' + (onlyOne ? 'true' : 'false') + '"' +
            ' title="' + deleteLabel + '" aria-label="' + deleteLabel + '">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' +
            'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/>' +
            '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>' +
            '<path d="M10 11v6"/><path d="M14 11v6"/></svg></span>' +
            '</div>';
    }

    /**
     * Rebuild the teacher's list of generated activities.
     *
     * @return {void}
     */
    function refreshActivityList() {
        var previewEl = document.getElementById('ala-activities-preview');
        if (!previewEl) {
            return;
        }

        var html = buildEtaBannerHtml(activities);
        activities.forEach(function(act, idx) {
            html += buildPreviewItemHtml(act, idx);
        });
        previewEl.innerHTML = html;

        bindPreviewItemClicks(previewEl);
    }

    /**
     * Bind the edit / preview controls in the teacher's activity list.
     *
     * @param {Element} previewEl The list container.
     * @return {void}
     */
    function bindPreviewItemClicks(previewEl) {
        var editBtns = previewEl.querySelectorAll('.ala-preview-edit');
        editBtns.forEach(function(btn) {
            onActivate(btn, function(e) {
                e.stopPropagation();
                openTeacherEdit(parseInt(btn.getAttribute('data-edit-index'), 10));
            });
        });

        var playBtns = previewEl.querySelectorAll('.ala-preview-play');
        playBtns.forEach(function(btn) {
            onActivate(btn, function(e) {
                e.stopPropagation();
                openTeacherPreview(parseInt(btn.getAttribute('data-preview-index'), 10));
            });
        });

        var deleteBtns = previewEl.querySelectorAll('.ala-preview-delete');
        deleteBtns.forEach(function(btn) {
            onActivate(btn, function(e) {
                e.stopPropagation();
                confirmDeleteActivity(parseInt(btn.getAttribute('data-delete-index'), 10));
            });
        });

        var items = previewEl.querySelectorAll('.ala-preview-clickable');
        items.forEach(function(item) {
            item.addEventListener('click', function() {
                openTeacherPreview(parseInt(item.getAttribute('data-activity-index'), 10));
            });
        });
    }

    /**
     * Ask the teacher to confirm, then remove one activity from the set.
     *
     * @param {Number} idx Zero based index of the activity to remove.
     * @return {void}
     */
    function confirmDeleteActivity(idx) {
        var act = activities[idx];
        if (!act) {
            return;
        }

        if (activities.length <= 1) {
            notifyError(str('error_delete_last',
                'This is the only activity left. Generate more before removing it.'));
            return;
        }

        Notification.confirm(
            str('js_delete_title', 'Remove this activity?'),
            strWithParam('js_delete_message', act.title),
            str('js_delete_confirm', 'Remove'),
            str('js_delete_cancel', 'Keep it'),
            function() {
                deleteActivity(idx);
            }
        );
    }

    /**
     * Remove one activity, then offer to put it back.
     *
     * @param {Number} idx Zero based index of the activity to remove.
     * @return {void}
     */
    function deleteActivity(idx) {
        var act = activities[idx];
        if (!act || deleteInFlight) {
            return;
        }

        var removedTitle = act.title || '';
        deleteInFlight = true;
        setDeleteControlsBusy(true);

        ajaxCall('deleteactivity', {
            activityindex: idx,
            // Sent so the server can refuse a request that was prepared against
            // a different version of the list, rather than removing whatever now
            // happens to sit at this position.
            expectedcount: activities.length,
            expectedtype: act.type || '',
            expectedtitle: removedTitle
        }, function(result) {
            deleteInFlight = false;
            setDeleteControlsBusy(false);

            if (!result || !result.ok) {
                notifyError((result && result.error) ||
                    str('js_err_deletefailed', 'The activity could not be removed.'));
                return;
            }

            activities = result.activities;
            refreshActivityList();
            showUndoDelete(idx, result.removed, removedTitle);
        });
    }

    /**
     * Disable or re-enable the remove controls while a request is in flight.
     *
     * @param {Boolean} busy Whether a removal is in progress.
     * @return {void}
     */
    function setDeleteControlsBusy(busy) {
        var btns = document.querySelectorAll('#ala-activities-preview .ala-preview-delete');
        Array.prototype.forEach.call(btns, function(btn) {
            btn.setAttribute('aria-disabled', busy ? 'true' : 'false');
            btn.classList.toggle('ala-is-busy', !!busy);
            if (busy) {
                btn.removeAttribute('tabindex');
            } else {
                btn.setAttribute('tabindex', '0');
            }
        });
    }

    /**
     * Show the undo bar for a few seconds after a removal.
     *
     * @param {Number} idx The position the activity was removed from.
     * @param {Object} removed The activity data, to send back on undo.
     * @param {String} title The activity title, for the message.
     * @return {void}
     */
    function showUndoDelete(idx, removed, title) {
        var previewEl = document.getElementById('ala-activities-preview');
        if (!previewEl) {
            return;
        }

        clearUndoDelete();

        var bar = document.createElement('div');
        bar.id = 'ala-undo-delete';
        bar.className = 'ala-undo-bar';

        // The message is the live region; the button sits outside it, because a
        // control inside a live region is announced as part of the status text.
        var message = document.createElement('span');
        message.className = 'ala-undo-text';
        message.setAttribute('role', 'status');
        message.setAttribute('aria-live', 'polite');
        message.textContent = strWithParam('js_deleted_notice', title);

        var undoBtn = document.createElement('button');
        undoBtn.type = 'button';
        undoBtn.className = 'ala-link-btn ala-undo-btn';
        undoBtn.textContent = str('js_delete_undo', 'Undo');
        undoBtn.addEventListener('click', function() {
            if (undoTimer) {
                clearTimeout(undoTimer);
                undoTimer = null;
            }
            undoBtn.disabled = true;
            restoreActivity(idx, removed, bar);
        });

        bar.appendChild(message);
        bar.appendChild(undoBtn);
        previewEl.parentNode.insertBefore(bar, previewEl);

        // The row that had focus has just been replaced, so send focus somewhere
        // useful rather than letting it fall to the top of the page.
        undoBtn.focus();

        undoTimer = setTimeout(clearUndoDelete, UNDO_WINDOW_MS);
    }

    /**
     * Remove the undo bar and cancel its timer.
     *
     * @return {void}
     */
    function clearUndoDelete() {
        if (undoTimer) {
            clearTimeout(undoTimer);
            undoTimer = null;
        }
        var bar = document.getElementById('ala-undo-delete');
        if (bar && bar.parentNode) {
            bar.parentNode.removeChild(bar);
        }
    }

    /**
     * Put a removed activity back where it came from.
     *
     * @param {Number} idx The position to restore it to.
     * @param {Object} removed The activity data returned by the removal.
     * @param {Element} bar The undo bar, kept on screen until the restore succeeds.
     * @return {void}
     */
    function restoreActivity(idx, removed, bar) {
        ajaxCall('restoreactivity', {
            activityindex: idx,
            expectedcount: activities.length,
            activityjson: JSON.stringify(removed)
        }, function(result) {
            if (!result || !result.ok) {
                // The activity only exists in this closure, so the offer has to
                // stay on screen for the teacher to try again.
                var retry = bar ? bar.querySelector('.ala-undo-btn') : null;
                if (retry) {
                    retry.disabled = false;
                }
                notifyError((result && result.error) ||
                    str('js_err_restorefailed', 'The activity could not be put back.'));
                return;
            }

            clearUndoDelete();
            activities = result.activities;
            refreshActivityList();
        });
    }

    /**
     * Escape a value for use inside a double-quoted HTML attribute.
     *
     * escapeHtml() is now fully attribute safe; this remains as a named alias so
     * call sites continue to read clearly.
     *
     * @param {*} text The value to escape.
     * @return {String} The escaped text.
     */
    function escapeAttr(text) {
        return escapeHtml(text);
    }

    // ========== TEACHER PREVIEW MODE ==========

    /**
     * Preview one activity exactly as a student would see it.
     *
     * @param {Number} index Index into the activities array.
     * @return {void}
     */
    function openTeacherPreview(index) {
        // The undo bar lives in the section about to be hidden, so take the
        // offer away rather than letting it expire where nobody can see it.
        clearUndoDelete();
        isPreviewMode = true;
        currentIndex = index;
        progress = {};

        var manageSection = document.getElementById('ala-manage-section');
        var previewSection = document.getElementById('ala-teacher-preview');
        var labelEl = document.getElementById('ala-preview-label');

        if (manageSection) {
            manageSection.style.display = 'none';
        }
        if (previewSection) {
            previewSection.style.display = 'block';
        }

        var activity = activities[index];
        if (labelEl && activity) {
            var typeLabel = activityTypeLabel(activity.type);
            labelEl.textContent = str('js_preview_heading', 'Preview: ') + typeLabel +
                ' - ' + (activity.title || '');
        }

        renderActivity(index);
    }

    /**
     * Leave preview mode and return to the manage list.
     *
     * @return {void}
     */
    function closeTeacherPreview() {
        isPreviewMode = false;

        var manageSection = document.getElementById('ala-manage-section');
        var previewSection = document.getElementById('ala-teacher-preview');
        var previewContent = document.getElementById('ala-preview-content');
        var previewFeedback = document.getElementById('ala-preview-feedback');

        if (previewSection) {
            previewSection.style.display = 'none';
        }
        if (manageSection) {
            manageSection.style.display = 'block';
        }
        if (previewContent) {
            previewContent.innerHTML = '';
        }
        if (previewFeedback) {
            previewFeedback.classList.add('ala-feedback-hidden');
        }
    }

    // ===================================================================
    // TEACHER: GENERATE ACTIVITIES
    // ===================================================================
    /**
     * Apply the selected/unselected state to one worker-level pill, keeping the
     * visual class and the aria-pressed state in sync.
     *
     * @param {Element} pill The pill element.
     * @param {Boolean} active Whether the pill is currently selected.
     * @return {void}
     */
    function setJobLevelPillState(pill, active) {
        if (!pill) {
            return;
        }
        pill.classList.toggle('ala-level-active', active);
        pill.setAttribute('aria-pressed', active ? 'true' : 'false');
    }

    /**
     * Reflect selectedAlaJobLevels onto every rendered worker-level pill, so the
     * class and aria-pressed state match the model after a bulk change (for
     * example the regenerate path restoring a previous selection).
     *
     * @return {void}
     */
    function syncJobLevelPills() {
        var container = document.getElementById('ala-job-level-pills');
        if (!container) {
            return;
        }
        container.querySelectorAll('.ala-level-pill').forEach(function(pill) {
            setJobLevelPillState(pill, selectedAlaJobLevels.indexOf(pill.getAttribute('data-value')) > -1);
        });
    }

    /**
     * Redraw the selected job-role chips.
     *
     * @return {void}
     */
    function renderAlaJobRoleChips() {
        var container = document.getElementById('ala-job-role-chips');
        if (!container) {
            return;
        }
        var removeLabel = escapeAttr(str('js_role_remove', 'Remove this job role'));
        container.innerHTML = selectedAlaJobRoles.map(function(role, idx) {
            return '<div class="ala-role-chip">' +
                '<span>' + escapeHtml(role) + '</span>' +
                '<button type="button" class="ala-chip-remove" data-idx="' + idx + '"' +
                ' aria-label="' + removeLabel + '">\u00d7</button>' +
                '</div>';
        }).join('');
        container.querySelectorAll('.ala-chip-remove').forEach(function(btn) {
            btn.addEventListener('click', function() {
                selectedAlaJobRoles.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
                renderAlaJobRoleChips();
            });
        });
        var input = document.getElementById('ala-job-role-input');
        if (input) {
            input.disabled = selectedAlaJobRoles.length >= 5;
        }
    }

    /**
     * Show or hide one collapsible fieldset and keep its ARIA state in sync.
     *
     * @param {Element} toggle The checkbox controlling the section.
     * @param {Element} fields The container being shown or hidden.
     * @return {void}
     */
    function applySectionToggle(toggle, fields) {
        var open = !!toggle.checked;
        fields.style.display = open ? 'block' : 'none';
        fields.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    /**
     * Bind the collapsible scenario and voiceover fieldsets to their checkboxes.
     *
     * Previously done by an inline <script> in view.php. Safe to call on any page:
     * students never see these controls, so every lookup is null checked, and the
     * function binds each toggle only once even if it is called again.
     *
     * @return {void}
     */
    function bindSectionToggles() {
        var sections = [
            {toggleId: 'ala-scenario-toggle', fieldsId: 'ala-scenario-fields'},
            {toggleId: 'ala-voiceover-toggle', fieldsId: 'ala-voiceover-fields'}
        ];

        sections.forEach(function(section) {
            var toggle = document.getElementById(section.toggleId);
            var fields = document.getElementById(section.fieldsId);
            if (!toggle || !fields) {
                return;
            }
            if (toggle.getAttribute('data-ala-toggle-bound') === '1') {
                return;
            }
            toggle.setAttribute('data-ala-toggle-bound', '1');
            toggle.setAttribute('aria-controls', section.fieldsId);
            applySectionToggle(toggle, fields);
            toggle.addEventListener('change', function() {
                applySectionToggle(toggle, fields);
            });
        });
    }

    /**
     * Wire up the teacher-facing generation, regeneration and management UI.
     *
     * @return {void}
     */
    function initTeacherForm() {
        // Restore textarea content from sessionStorage if a regeneration reload just occurred
        var savedContent = sessionStorage.getItem('ala_content_' + CONFIG.cmid);
        if (savedContent) {
            var restoredEl = document.getElementById('ala-content');
            if (restoredEl) {
                restoredEl.value = savedContent;
            }
            sessionStorage.removeItem('ala_content_' + CONFIG.cmid);
        }

        // Fetch credits
        ajaxCall('getcredits', {}, function(result) {
            var creditsEl = document.getElementById('ala-credits-value');
            if (creditsEl && result.ok) {
                creditsEl.textContent = result.credits;
            }
        });

        // Update total cost when activity count or voiceover toggle changes
        var activityCountEl = document.getElementById('ala-activitycount');
        var totalCostEl = document.getElementById('ala-total-cost');
        var costPerActEl = document.getElementById('ala-cost-per-activity');
        var voiceoverToggleEl = document.getElementById('ala-voiceover-toggle');
        /**
         * Recalculate the displayed credit cost for the current settings.
         *
         * @return {void}
         */
        function updateAlaCost() {
            var count = activityCountEl ? parseInt(activityCountEl.value, 10) || 5 : 5;
            var voOn = voiceoverToggleEl && voiceoverToggleEl.checked;
            var rate = voOn ? 3 : 2;
            var creditsUnit = str('js_credits_unit', 'credits');
            if (costPerActEl) {
                costPerActEl.textContent = rate + ' ' + creditsUnit;
            }
            if (totalCostEl) {
                totalCostEl.textContent = (count * rate) + ' ' + creditsUnit;
            }
        }
        if (activityCountEl) {
            activityCountEl.addEventListener('change', updateAlaCost);
        }
        if (voiceoverToggleEl) {
            // The show/hide of #ala-voiceover-fields is handled once, in
            // bindSectionToggles(); only the cost recalculation is bound here.
            voiceoverToggleEl.addEventListener('change', updateAlaCost);
        }
        // The Mustache template leaves the cost spans empty, and updateAlaCost() is
        // otherwise only reached via change events - run it once so the summary is
        // correct on first paint.
        updateAlaCost();

        // Activity type checkboxes, and the link that re-ticks all of them.
        var selectAllTypesBtn = document.getElementById('ala-types-all');
        if (selectAllTypesBtn) {
            selectAllTypesBtn.addEventListener('click', function() {
                document.querySelectorAll('#ala-activity-types .ala-type-checkbox').forEach(function(box) {
                    box.checked = true;
                });
            });
        }

        // Job level pills  -  multi-select toggle.
        var pillContainer = document.getElementById('ala-job-level-pills');
        if (pillContainer) {
            pillContainer.addEventListener('click', function(e) {
                var pill = e.target.closest('.ala-level-pill');
                if (!pill) {
                    return;
                }
                var val = pill.getAttribute('data-value');
                var idx = selectedAlaJobLevels.indexOf(val);
                if (idx > -1) {
                    selectedAlaJobLevels.splice(idx, 1);
                    setJobLevelPillState(pill, false);
                } else {
                    selectedAlaJobLevels.push(val);
                    setJobLevelPillState(pill, true);
                }
            });
            // The template renders every pill as aria-pressed="false"; make sure the
            // markup matches the model from the very first paint.
            syncJobLevelPills();
        }

        // Industry SELECT and sector SELECT population
        var alaIndustryEl = document.getElementById('ala-scenario-industry');
        var alaSectorEl = document.getElementById('ala-scenario-sector');
        if (alaIndustryEl) {
            populateIndustrySelect(alaIndustryEl);
            alaIndustryEl.addEventListener('change', function() {
                if (alaSectorEl) {
                    populateSectorSelect(alaSectorEl, alaIndustryEl.value);
                }
            });
        }

        // Job role chips  -  press Enter or comma to add, max 5.
        var roleInput = document.getElementById('ala-job-role-input');
        if (roleInput) {
            roleInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    var val = roleInput.value.trim().replace(/,$/, '');
                    if (val && selectedAlaJobRoles.indexOf(val) === -1 && selectedAlaJobRoles.length < 5) {
                        selectedAlaJobRoles.push(val);
                        renderAlaJobRoleChips();
                    }
                    roleInput.value = '';
                }
            });
        }

        // Generate button
        var genBtn = document.getElementById('ala-generate-btn');
        if (genBtn) {
            genBtn.addEventListener('click', function() {
                var content = document.getElementById('ala-content').value.trim();
                if (!content) {
                    Notification.alert(
                        str('js_dialog_title', 'AI Learning Activities'),
                        str('error_no_content', 'Please add some content before generating activities.')
                    );
                    return;
                }

                var language = document.getElementById('ala-language').value;
                var activitycount = parseInt(document.getElementById('ala-activitycount').value, 10);
                var activitytypes = selectedActivityTypes();

                if (activitytypes.length === 0) {
                    Notification.alert(
                        str('js_dialog_title', 'AI Learning Activities'),
                        str('js_types_none', 'Choose at least one activity type before generating.')
                    );
                    return;
                }

                // Show progress
                genBtn.disabled = true;
                genBtn.textContent = str('generating', 'Generating...');
                var progressBar = document.getElementById('ala-progress-bar');
                if (progressBar) {
                    progressBar.style.display = 'block';
                }

                var fill = document.getElementById('ala-progress-fill');
                var progressText = document.getElementById('ala-progress-text');
                var pct = 0;
                var interval = setInterval(function() {
                    pct = Math.min(pct + Math.random() * 8, 90);
                    if (fill) {
                        fill.style.width = pct + '%';
                    }
                }, 500);

                /* Cap the polling loop at ~7.5 minutes so a stuck job cannot poll forever. */
                var MAX_GEN_POLLS = 150;
                var pollCount = 0;

                var voiceoverOn = voiceoverToggleEl && voiceoverToggleEl.checked;
                var params = {
                    content: content,
                    language: language,
                    activitycount: activitycount,
                    activitytypes: activitytypes.join(',')
                };

                var scenarioToggle = document.getElementById('ala-scenario-toggle');
                if (scenarioToggle && scenarioToggle.checked) {
                    params.scenariomode = '1';
                    var countryEl = document.getElementById('ala-scenario-country');
                    var industryEl = document.getElementById('ala-scenario-industry');
                    if (countryEl && countryEl.value.trim()) {
                        params.scenariocountry = countryEl.value.trim();
                    }
                    if (industryEl && industryEl.value.trim()) {
                        params.scenarioindustry = industryEl.value.trim();
                    }
                    var sectorEl = document.getElementById('ala-scenario-sector');
                    if (sectorEl && sectorEl.value.trim()) {
                        params.scenariosector = sectorEl.value.trim();
                    }
                    if (selectedAlaJobLevels.length > 0) {
                        params.scenarioworkerlevel = selectedAlaJobLevels.join(', ');
                    }
                    if (selectedAlaJobRoles.length > 0) {
                        params.scenariojobroles = selectedAlaJobRoles.join(', ');
                    }
                }

                if (voiceoverOn) {
                    params.voiceoverenabled = '1';
                    var voiceGenderEl = document.getElementById('ala-voice-gender');
                    var voiceIdEl = document.getElementById('ala-voice-id');
                    params.voicegender = voiceGenderEl ? voiceGenderEl.value : 'female';
                    params.voiceid = voiceIdEl ? voiceIdEl.value : 'Zephyr';
                }

                /* V1.5.49 ASYNC: Start job (~500ms), then poll every 3s. Bypasses proxy timeout. */
                /**
                 * Abort generation, clear the fake progress animation and report the failure.
                 *
                 * @param {String} msg The message to show.
                 * @return {void}
                 */
                function handleGenError(msg) {
                    clearInterval(interval);
                    genBtn.disabled = false;
                    genBtn.textContent = str('js_generate_btn', 'Generate Activities');
                    if (progressBar) {
                        progressBar.style.display = 'none';
                    }
                    notifyError(msg || str('js_err_genfailed', 'Generation failed.'));
                }

                /**
                 * Poll the async generation job until it finishes, errors or times out.
                 *
                 * @param {String} jobId The job identifier returned by generate_async.
                 * @return {void}
                 */
                function doPoll(jobId) {
                    pollCount++;
                    if (pollCount > MAX_GEN_POLLS) {
                        handleGenError(str('js_err_gentimeout',
                            'Generation is taking longer than expected. Please try again.'));
                        return;
                    }
                    ajaxCall('poll_job', {jobId: jobId}, function(pollResult) {
                        if (!pollResult || !pollResult.ok) {
                            handleGenError((pollResult && pollResult.error) ||
                                str('js_err_pollfailed', 'Could not check the generation job.'));
                            return;
                        }
                        if (pollResult.status === 'done') {
                            clearInterval(interval);
                            if (fill) {
                                fill.style.width = '100%';
                            }
                            var result = pollResult.result;
                            if (result && result.ok && result.activities) {
                                // Save generated activities to the Moodle DB before reloading.
                                // The generate_async case starts the job and returns immediately
                                // without saving  -  so the save must happen here after polling.
                                var saveParams = {
                                    activitiesjson: JSON.stringify(result.activities),
                                    content: content,
                                    language: language,
                                    activitycount: activitycount,
                                    activitytypes: activitytypes.join(',')
                                };
                                if (voiceoverOn) {
                                    saveParams.voiceoverenabled = '1';
                                    var vgEl = document.getElementById('ala-voice-gender');
                                    var viEl = document.getElementById('ala-voice-id');
                                    saveParams.voicegender = vgEl ? vgEl.value : 'female';
                                    saveParams.voiceid = viEl ? viEl.value : 'Zephyr';
                                }
                                ajaxCall('save_generated', saveParams, function(saveResult) {
                                    if (saveResult && saveResult.ok) {
                                        if (saveResult.typesapplied === false) {
                                            notifyError(str('js_types_notapplied',
                                                'The generation service could not match your activity ' +
                                                'type choice, so every activity it produced has been kept.'));
                                        }
                                        if (progressText) {
                                            progressText.textContent =
                                                str('generate_success', 'Activities generated successfully!');
                                        }
                                        setTimeout(function() {
                                            var contentEl = document.getElementById('ala-content');
                                            if (contentEl) {
                                                sessionStorage.setItem('ala_content_' + CONFIG.cmid, contentEl.value);
                                            }
                                            var langEl = document.getElementById('ala-language');
                                            if (langEl) {
                                                sessionStorage.setItem('ala_language_' + CONFIG.cmid, langEl.value);
                                            }
                                            var countEl = document.getElementById('ala-activitycount');
                                            if (countEl) {
                                                sessionStorage.setItem('ala_activitycount_' + CONFIG.cmid, countEl.value);
                                            }
                                            var scenToggle = document.getElementById('ala-scenario-toggle');
                                            if (scenToggle) {
                                                sessionStorage.setItem(
                                                    'ala_scenariomode_' + CONFIG.cmid,
                                                    scenToggle.checked ? '1' : '0'
                                                );
                                                if (scenToggle.checked) {
                                                    var countryEl2 = document.getElementById('ala-scenario-country');
                                                    var industryEl2 = document.getElementById('ala-scenario-industry');
                                                    var sectorEl2 = document.getElementById('ala-scenario-sector');
                                                    if (countryEl2) {
                                                        sessionStorage.setItem(
                                                            'ala_country_' + CONFIG.cmid, countryEl2.value
                                                        );
                                                    }
                                                    if (industryEl2) {
                                                        sessionStorage.setItem(
                                                            'ala_industry_' + CONFIG.cmid, industryEl2.value
                                                        );
                                                    }
                                                    if (sectorEl2) {
                                                        sessionStorage.setItem(
                                                            'ala_sector_' + CONFIG.cmid, sectorEl2.value
                                                        );
                                                    }
                                                }
                                            }
                                            window.location.reload();
                                        }, 1000);
                                    } else {
                                        handleGenError((saveResult && saveResult.error) ||
                                            str('js_err_savegenerated', 'Could not save the generated activities.'));
                                    }
                                });
                            } else {
                                handleGenError((result && result.error) ||
                                    str('js_err_genfailed', 'Generation failed.'));
                            }
                            return;
                        }
                        if (pollResult.status === 'error') {
                            handleGenError(pollResult.error || str('js_err_genfailed', 'Generation failed.'));
                            return;
                        }
                        /* Pending / processing  -  keep polling */
                        setTimeout(function() {
                            doPoll(jobId);
                        }, 3000);
                    });
                }

                ajaxCall('generate_async', params, function(startResult) {
                    if (!startResult || !startResult.ok || !startResult.jobId) {
                        handleGenError((startResult && startResult.error) ||
                            str('js_err_genstart', 'Could not start generating activities.'));
                        return;
                    }
                    setTimeout(function() {
                        doPoll(startResult.jobId);
                    }, 3000);
                });
            });
        }

        // Regenerate button  -  show form pre-populated with last-used settings
        var regenBtn = document.getElementById('ala-regenerate-btn');
        if (regenBtn) {
            regenBtn.addEventListener('click', function() {
                // Restore last-used settings from sessionStorage
                var savedContent = sessionStorage.getItem('ala_content_' + CONFIG.cmid);
                if (savedContent) {
                    var contentEl = document.getElementById('ala-content');
                    if (contentEl) {
                        contentEl.value = savedContent;
                    }
                }
                var savedLang = sessionStorage.getItem('ala_language_' + CONFIG.cmid);
                if (savedLang) {
                    var langEl = document.getElementById('ala-language');
                    if (langEl) {
                        langEl.value = savedLang;
                    }
                }
                var savedCount = sessionStorage.getItem('ala_activitycount_' + CONFIG.cmid);
                if (savedCount) {
                    var countEl = document.getElementById('ala-activitycount');
                    if (countEl) {
                        countEl.value = savedCount;
                        countEl.dispatchEvent(new Event('change'));
                    }
                }
                var savedScenario = sessionStorage.getItem('ala_scenariomode_' + CONFIG.cmid);
                if (savedScenario === '1') {
                    var scenToggle = document.getElementById('ala-scenario-toggle');
                    if (scenToggle && !scenToggle.checked) {
                        scenToggle.checked = true;
                        scenToggle.dispatchEvent(new Event('change'));
                    }
                    var savedCountry = sessionStorage.getItem('ala_country_' + CONFIG.cmid);
                    var savedIndustry = sessionStorage.getItem('ala_industry_' + CONFIG.cmid);
                    var savedSector = sessionStorage.getItem('ala_sector_' + CONFIG.cmid);
                    if (savedCountry) {
                        var countryEl = document.getElementById('ala-scenario-country');
                        if (countryEl) {
                            countryEl.value = savedCountry;
                        }
                    }
                    var sectorEl = document.getElementById('ala-scenario-sector');
                    if (savedIndustry) {
                        var industryEl = document.getElementById('ala-scenario-industry');
                        if (industryEl) {
                            industryEl.value = savedIndustry;
                            // Repopulate the sector list for the restored industry,
                            // otherwise the saved sector has no option to select.
                            populateSectorSelect(sectorEl, industryEl.value);
                        }
                    }
                    if (savedSector && sectorEl) {
                        sectorEl.value = savedSector;
                    }
                }
                syncJobLevelPills();
                clearUndoDelete();
                var formSection = document.getElementById('ala-form-section');
                var manageSection = document.getElementById('ala-manage-section');
                if (formSection) {
                    formSection.style.display = 'block';
                }
                if (manageSection) {
                    manageSection.style.display = 'none';
                }
            });
        }

        // Regenerate Audio button  -  re-runs TTS for existing activities with updated voice settings
        var regenAudioBtn = document.getElementById('ala-regen-audio-btn');
        if (regenAudioBtn) {
            regenAudioBtn.addEventListener('click', function() {
                if (!CONFIG.activitiesjson) {
                    notifyError(str('js_err_noaudioactivities', 'There are no activities to generate audio for.'));
                    return;
                }
                var activitiesForAudio = typeof CONFIG.activitiesjson === 'string'
                    ? JSON.parse(CONFIG.activitiesjson) : CONFIG.activitiesjson;
                var voicelang = CONFIG.voicelanguage || 'en-AU';
                var vid = CONFIG.voiceid || 'Zephyr';
                regenAudioBtn.disabled = true;
                regenAudioBtn.textContent = str('js_audio_generating', 'Generating audio...');
                ajaxCall('regenerateaudio', {
                    activitiesjson: JSON.stringify(activitiesForAudio),
                    voicelanguage: voicelang,
                    voiceid: vid
                }, function(result) {
                    regenAudioBtn.disabled = false;
                    regenAudioBtn.textContent = str('js_audio_regenerate', 'Regenerate Audio');
                    if (result && result.ok && result.activities) {
                        notifySuccess(str('js_audio_regenerated', 'Audio regenerated successfully.'));
                        location.reload();
                    } else {
                        notifyError(str('js_err_audiofailed', 'Audio regeneration failed.') + ' ' +
                            ((result && result.error) || str('js_err_unknown', 'Unknown error.')));
                    }
                });
            });
        }

        // Back button for preview mode
        var backBtn = document.getElementById('ala-preview-back-btn');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                closeTeacherPreview();
            });
        }

        // Back button for edit mode
        var editBackBtn = document.getElementById('ala-edit-back-btn');
        if (editBackBtn) {
            editBackBtn.addEventListener('click', function() {
                closeTeacherEdit();
            });
        }

        // Preview existing activities
        if (CONFIG.hasactivities && CONFIG.activitiesjson) {
            var previewEl = document.getElementById('ala-activities-preview');
            if (previewEl) {
                try {
                    var acts = typeof CONFIG.activitiesjson === 'string'
                        ? JSON.parse(CONFIG.activitiesjson)
                        : CONFIG.activitiesjson;
                    activities = acts;
                    var html = '';
                    acts.forEach(function(act, idx) {
                        html += buildPreviewItemHtml(act, idx);
                    });
                    previewEl.innerHTML = html;

                    bindPreviewItemClicks(previewEl);
                } catch (e) {
                    previewEl.innerHTML = '<p>' +
                        escapeHtml(str('js_activities_ready', 'Activities generated and ready for students.')) +
                        '</p>';
                }
            }
        }
    }

    // ===================================================================
    // STUDENT: START PLAYER
    // ===================================================================
    /**
     * Start the student player: load the activities and begin (or resume) an attempt.
     *
     * @return {void}
     */
    function initStudentPlayer() {
        if (!CONFIG.activitiesjson) {
            return;
        }

        try {
            activities = typeof CONFIG.activitiesjson === 'string' ? JSON.parse(CONFIG.activitiesjson) : CONFIG.activitiesjson;
        } catch (e) {
            return;
        }

        if (!activities || activities.length === 0) {
            return;
        }

        // Bind review navigation buttons
        var reviewBtn = document.getElementById('ala-review-btn');
        if (reviewBtn) {
            reviewBtn.addEventListener('click', function() {
                enterReviewMode();
            });
        }
        var reviewPrevBtn = document.getElementById('ala-review-prev');
        if (reviewPrevBtn) {
            reviewPrevBtn.addEventListener('click', function() {
                reviewPrev();
            });
        }
        var reviewNextBtn = document.getElementById('ala-review-next');
        if (reviewNextBtn) {
            reviewNextBtn.addEventListener('click', function() {
                reviewNext();
            });
        }
        var reviewDoneBtn = document.getElementById('ala-review-done');
        if (reviewDoneBtn) {
            reviewDoneBtn.addEventListener('click', function() {
                exitReviewMode();
            });
        }
        var practiceAgainBtn = document.getElementById('ala-practice-again-btn');
        if (practiceAgainBtn) {
            practiceAgainBtn.addEventListener('click', function() {
                practiceAgain();
            });
        }

        var etaContainer = document.getElementById('ala-player-eta');
        if (etaContainer) {
            etaContainer.innerHTML = buildEtaBannerHtml(activities);
        }

        ajaxCall('startattempt', {}, function(result) {
            if (!result.ok) {
                return;
            }

            attemptId = result.attemptid;

            if (result.completed) {
                var playerContent = document.getElementById('ala-player-content');
                var completeScreen = document.getElementById('ala-player-complete');
                var headerEl = document.getElementById('ala-player-header');
                if (playerContent) {
                    playerContent.style.display = 'none';
                }
                if (headerEl) {
                    headerEl.style.display = 'none';
                }
                if (completeScreen) {
                    completeScreen.style.display = 'flex';
                }

                var fillEl = document.getElementById('ala-player-progress-fill');
                if (fillEl) {
                    fillEl.style.width = '100%';
                }

                var continueBtn = document.getElementById('ala-continue-btn');
                if (continueBtn && CONFIG.nextactivityurl) {
                    continueBtn.href = CONFIG.nextactivityurl;
                    continueBtn.style.display = 'inline-flex';
                }
                return;
            }

            lockMoodleNav();

            progress = result.progress || {};
            currentIndex = result.currentactivity || 0;

            while (currentIndex < activities.length && progress[currentIndex] === true) {
                currentIndex++;
            }

            if (currentIndex >= activities.length) {
                completeAllActivities();
            } else {
                renderActivity(currentIndex);
            }
        });
    }

    // ===================================================================
    // INIT
    // ===================================================================

    /**
     * Reads the configuration PHP wrote into the page as a JSON data island.
     *
     * The payload holds the language strings, the scenario vocabulary and the
     * activity manifest, which is far too large to be inlined as a js_call_amd()
     * argument.
     *
     * @return {Object|null} The parsed configuration, or null when it is absent or invalid.
     */
    function readPageConfig() {
        var el = document.getElementById('ala-player-config');
        if (!el || !el.textContent) {
            return null;
        }
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    return {
        /**
         * Entry point called by $PAGE->requires->js_call_amd().
         *
         * @param {Object} [config] Optional configuration; normally read from the page.
         * @return {void}
         */
        init: function(config) {
            CONFIG = config || readPageConfig() || window.ALA_CONFIG;
            if (!CONFIG) {
                return;
            }

            // V1.6.8 FIX-TOUCH-DETECT: navigator.maxTouchPoints > 0 is true on Windows
            // Chrome/Edge even on pure desktop machines (no touchscreen), causing drag-and-
            // drop to be wrongly disabled for all ordering/sorting activities. Removed that
            // check; now only use ontouchstart + pointer:coarse which reliably distinguish
            // genuine touch-primary devices from mouse-primary laptops.
            isTouchDevice = (('ontouchstart' in window) ||
                (window.matchMedia && window.matchMedia('(pointer: coarse)').matches));

            if (CONFIG.cancreate) {
                bindSectionToggles();
                initTeacherForm();
            } else if (CONFIG.hasactivities) {
                initStudentPlayer();
            }
        }
    };
});
