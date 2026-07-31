<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('aiactivities', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('aiactivities', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aiactivities:view', $context);

$cancreate = has_capability('mod/aiactivities:create', $context);

$aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
if (file_exists($aiconfiglib)) {
    require_once($aiconfiglib);
}

$siteid = '';
$apikey = '';
if (function_exists('local_aiconfig_get_siteid')) {
    $siteid = local_aiconfig_get_siteid();
}
if (function_exists('local_aiconfig_get_apikey')) {
    $apikey = local_aiconfig_get_apikey();
}

if (empty($siteid)) {
    $siteid = get_config('mod_aiactivities', 'siteid');
}
if (empty($apikey)) {
    $apikey = get_config('mod_aiactivities', 'apikey');
}

$PAGE->set_url('/mod/aiactivities/view.php', ['id' => $id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$PAGE->requires->css('/mod/aiactivities/styles/player.css');

$nextactivityurl = '';
$modinfo = get_fast_modinfo($course);
$cms = $modinfo->get_cms();
$cmids = array_keys($cms);
$currentpos = array_search($cm->id, $cmids);
if ($currentpos !== false) {
    for ($i = $currentpos + 1; $i < count($cmids); $i++) {
        $nextcm = $cms[$cmids[$i]];
        if ($nextcm->uservisible && !$nextcm->is_stealth()) {
            $nextactivityurl = $nextcm->url ? $nextcm->url->out(false) : '';
            break;
        }
    }
}

aiactivities_view($instance, $course, $cm, $context);

echo $OUTPUT->header();

if (empty($siteid) || empty($apikey)) {
    echo $OUTPUT->notification(get_string('not_configured', 'mod_aiactivities'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

$hasactivities = !empty($instance->activitiesjson);

if ($cancreate) {
    ?>
    <div id="ala-app" class="ala-container">
        <div class="ala-credits-badge">
            <svg class="ala-credits-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/>
                <path d="M12 18V6"/>
            </svg>
            <span id="ala-credits-value">--</span>
            <span class="ala-credits-label"><?php echo get_string('credits_label', 'mod_aiactivities'); ?></span>
        </div>

        <div id="ala-form-section" class="ala-card" <?php if ($hasactivities) echo 'style="display:none;"'; ?>>
            <h3 class="ala-card-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg>
                <?php echo get_string('page_heading', 'mod_aiactivities'); ?>
            </h3>
            <p class="ala-intro"><?php echo get_string('page_intro', 'mod_aiactivities'); ?></p>

            <form id="ala-form">
                <div class="ala-form-group">
                    <label for="ala-content" class="ala-label"><?php echo get_string('content_label', 'mod_aiactivities'); ?></label>
                    <textarea id="ala-content" class="ala-textarea" rows="10"
                        placeholder="<?php echo get_string('content_placeholder', 'mod_aiactivities'); ?>"></textarea>
                    <small class="ala-help"><?php echo get_string('content_help', 'mod_aiactivities'); ?></small>
                </div>

                <div class="ala-form-row">
                    <div class="ala-form-group ala-half">
                        <label for="ala-language" class="ala-label"><?php echo get_string('language_label', 'mod_aiactivities'); ?></label>
                        <select id="ala-language" class="ala-select">
                            <optgroup label="English">
                                <option value="en-AU" selected><?php echo get_string('lang_en_au', 'mod_aiactivities'); ?></option>
                                <option value="en-GB"><?php echo get_string('lang_en_gb', 'mod_aiactivities'); ?></option>
                                <option value="en-US"><?php echo get_string('lang_en_us', 'mod_aiactivities'); ?></option>
                                <option value="en-IN"><?php echo get_string('lang_en_in', 'mod_aiactivities'); ?></option>
                            </optgroup>
                            <optgroup label="European">
                                <option value="es-ES"><?php echo get_string('lang_es_es', 'mod_aiactivities'); ?></option>
                                <option value="fr-FR"><?php echo get_string('lang_fr_fr', 'mod_aiactivities'); ?></option>
                                <option value="de-DE"><?php echo get_string('lang_de_de', 'mod_aiactivities'); ?></option>
                                <option value="it-it"><?php echo get_string('lang_it_it', 'mod_aiactivities'); ?></option>
                                <option value="pt-BR"><?php echo get_string('lang_pt_br', 'mod_aiactivities'); ?></option>
                                <option value="nl-NL"><?php echo get_string('lang_nl_nl', 'mod_aiactivities'); ?></option>
                                <option value="pl-PL"><?php echo get_string('lang_pl_pl', 'mod_aiactivities'); ?></option>
                                <option value="tr-TR"><?php echo get_string('lang_tr_tr', 'mod_aiactivities'); ?></option>
                            </optgroup>
                            <optgroup label="Nordic">
                                <option value="sv-SE"><?php echo get_string('lang_sv_se', 'mod_aiactivities'); ?></option>
                                <option value="da-DK"><?php echo get_string('lang_da_dk', 'mod_aiactivities'); ?></option>
                                <option value="nb-NO"><?php echo get_string('lang_nb_no', 'mod_aiactivities'); ?></option>
                                <option value="fi-FI"><?php echo get_string('lang_fi_fi', 'mod_aiactivities'); ?></option>
                            </optgroup>
                            <optgroup label="Asian">
                                <option value="zh-CN"><?php echo get_string('lang_zh_cn', 'mod_aiactivities'); ?></option>
                                <option value="ja-JP"><?php echo get_string('lang_ja_jp', 'mod_aiactivities'); ?></option>
                                <option value="ko-KR"><?php echo get_string('lang_ko_kr', 'mod_aiactivities'); ?></option>
                                <option value="hi-IN"><?php echo get_string('lang_hi_in', 'mod_aiactivities'); ?></option>
                                <option value="th-TH"><?php echo get_string('lang_th_th', 'mod_aiactivities'); ?></option>
                                <option value="vi-VN"><?php echo get_string('lang_vi_vn', 'mod_aiactivities'); ?></option>
                                <option value="id-ID"><?php echo get_string('lang_id_id', 'mod_aiactivities'); ?></option>
                                <option value="ms-MY"><?php echo get_string('lang_ms_my', 'mod_aiactivities'); ?></option>
                                <option value="tl-PH"><?php echo get_string('lang_tl_ph', 'mod_aiactivities'); ?></option>
                            </optgroup>
                            <optgroup label="Other">
                                <option value="ar-SA"><?php echo get_string('lang_ar_sa', 'mod_aiactivities'); ?></option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="ala-form-group ala-half">
                        <label for="ala-activitycount" class="ala-label"><?php echo get_string('activitycount_label', 'mod_aiactivities'); ?></label>
                        <select id="ala-activitycount" class="ala-select">
                            <?php for ($i = 1; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>"<?php echo ($i === 8) ? ' selected' : ''; ?>><?php echo $i; ?> <?php echo ($i === 1) ? 'activity' : 'activities'; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="ala-scenario-section">
                    <div class="ala-form-group">
                        <label class="ala-toggle-label">
                            <input type="checkbox" id="ala-scenario-toggle" class="ala-toggle-checkbox">
                            <span class="ala-toggle-switch"></span>
                            <span class="ala-toggle-text"><?php echo get_string('scenario_toggle', 'mod_aiactivities'); ?></span>
                        </label>
                        <small class="ala-help"><?php echo get_string('scenario_help', 'mod_aiactivities'); ?></small>
                    </div>
                    <div id="ala-scenario-fields" class="ala-scenario-fields" style="display:none;">
                        <div class="ala-form-row ala-form-row-3">
                            <div class="ala-form-group ala-third">
                                <label for="ala-scenario-country" class="ala-label"><?php echo get_string('scenario_country', 'mod_aiactivities'); ?></label>
                                <input type="text" id="ala-scenario-country" class="ala-input" placeholder="<?php echo get_string('scenario_country_placeholder', 'mod_aiactivities'); ?>">
                            </div>
                            <div class="ala-form-group ala-third">
                                <label for="ala-scenario-industry" class="ala-label"><?php echo get_string('scenario_industry', 'mod_aiactivities'); ?></label>
                                <select id="ala-scenario-industry" class="ala-input">
                                    <option value="">Select industry...</option>
                                </select>
                            </div>
                            <div class="ala-form-group ala-third">
                                <label class="ala-label"><?php echo get_string('scenario_worker_level', 'mod_aiactivities'); ?> <small>(select one or more)</small></label>
                                <div class="ala-level-pills" id="ala-job-level-pills">
                                    <button type="button" class="ala-level-pill" data-value="Worker">Worker</button>
                                    <button type="button" class="ala-level-pill" data-value="Supervisor">Supervisor</button>
                                    <button type="button" class="ala-level-pill" data-value="Manager">Manager</button>
                                    <button type="button" class="ala-level-pill" data-value="Executive">Executive</button>
                                </div>
                            </div>
                        </div>
                        <div class="ala-form-row">
                            <div class="ala-form-group">
                                <label for="ala-scenario-sector" class="ala-label">Industry Sector <small>(optional)</small></label>
                                <select id="ala-scenario-sector" class="ala-input" disabled>
                                    <option value="">Select industry first...</option>
                                </select>
                            </div>
                        </div>
                        <div class="ala-form-row">
                            <div class="ala-form-group">
                                <label for="ala-job-role-input" class="ala-label">Job Roles <small>(up to 5 — press Enter to add)</small></label>
                                <div class="ala-role-chips" id="ala-job-role-chips"></div>
                                <input type="text" id="ala-job-role-input" class="ala-input" placeholder="e.g. Site Supervisor, Project Manager...">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ala-voiceover-section">
                    <div class="ala-form-group">
                        <label class="ala-toggle-label">
                            <input type="checkbox" id="ala-voiceover-toggle" class="ala-toggle-checkbox">
                            <span class="ala-toggle-switch"></span>
                            <span class="ala-toggle-text">Add AI Voiceover Narration <small>(+1 credit/activity)</small></span>
                        </label>
                        <small class="ala-help">Generate spoken audio narration for each activity using AI text-to-speech.</small>
                    </div>
                    <div id="ala-voiceover-fields" class="ala-voiceover-fields" style="display:none;">
                        <div class="ala-form-row ala-form-row-2">
                            <div class="ala-form-group ala-half">
                                <label for="ala-voice-gender" class="ala-label">Voice Gender</label>
                                <select id="ala-voice-gender" class="ala-input">
                                    <option value="female">Female</option>
                                    <option value="male">Male</option>
                                </select>
                            </div>
                            <div class="ala-form-group ala-half">
                                <label for="ala-voice-id" class="ala-label">Voice</label>
                                <select id="ala-voice-id" class="ala-input">
                                    <option value="Zephyr">Zephyr (Female)</option>
                                    <option value="Aoede">Aoede (Female)</option>
                                    <option value="Kore">Kore (Female)</option>
                                    <option value="Leda">Leda (Female)</option>
                                    <option value="Puck">Puck (Male)</option>
                                    <option value="Charon">Charon (Male)</option>
                                    <option value="Fenrir">Fenrir (Male)</option>
                                    <option value="Orus">Orus (Male)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ala-cost-summary">
                    <div class="ala-cost-row" id="ala-cost-per-activity-row">
                        <span class="ala-cost-label">Cost per activity</span>
                        <span class="ala-cost-value" id="ala-cost-per-activity">2 credits</span>
                    </div>
                    <div class="ala-cost-row ala-cost-total">
                        <span class="ala-cost-label">Total cost</span>
                        <span class="ala-cost-value" id="ala-total-cost">10 credits</span>
                    </div>
                </div>

                <div class="ala-form-actions">
                    <button type="button" id="ala-generate-btn" class="ala-btn ala-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v14"/><path d="m5 10 7 7 7-7"/></svg>
                        <?php echo get_string('generate_btn', 'mod_aiactivities'); ?>
                    </button>
                </div>

                <div id="ala-progress-bar" class="ala-progress-container" style="display:none;">
                    <div class="ala-progress-bar">
                        <div class="ala-progress-fill" id="ala-progress-fill"></div>
                    </div>
                    <p class="ala-progress-text" id="ala-progress-text"><?php echo get_string('generating', 'mod_aiactivities'); ?></p>
                </div>
            </form>
        </div>

        <?php if ($hasactivities): ?>
        <div id="ala-manage-section" class="ala-card ala-manage-card">
            <div class="ala-teacher-info-banner">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                <span>These activities are now live and available for students to complete. Use the options below to preview, edit, or regenerate.</span>
            </div>
            <div class="ala-manage-header">
                <h3 class="ala-card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    Activities Generated
                </h3>
                <div class="ala-manage-actions">
                    <?php if (!empty($instance->voiceoverenabled)): ?>
                    <button type="button" id="ala-regen-audio-btn" class="ala-btn ala-btn-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>
                        Regenerate Audio
                    </button>
                    <?php endif; ?>
                    <button type="button" id="ala-regenerate-btn" class="ala-btn ala-btn-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                        <?php echo get_string('regenerate_btn', 'mod_aiactivities'); ?>
                    </button>
                </div>
            </div>
            <div id="ala-activities-preview" class="ala-activities-preview"></div>
        </div>

        <div id="ala-teacher-preview" class="ala-card" style="display:none;">
            <div class="ala-manage-header">
                <button type="button" id="ala-preview-back-btn" class="ala-btn ala-btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    Back to Activities
                </button>
                <span id="ala-preview-label" class="ala-preview-label"></span>
            </div>
            <div id="ala-preview-content" class="ala-player-content"></div>
            <div id="ala-preview-feedback" class="ala-player-feedback ala-feedback-hidden"></div>
        </div>

        <div id="ala-teacher-edit" class="ala-card" style="display:none;">
            <div class="ala-manage-header">
                <button type="button" id="ala-edit-back-btn" class="ala-btn ala-btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    Back to Activities
                </button>
                <span id="ala-edit-label" class="ala-preview-label"></span>
            </div>
            <div id="ala-edit-content"></div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

if (!$cancreate && $hasactivities) {
    ?>
    <div id="ala-player" class="ala-container ala-player-container">
        <div id="ala-player-eta"></div>
        <div id="ala-player-header" class="ala-player-header">
            <div class="ala-player-progress">
                <span class="ala-player-progress-text">
                    <?php echo get_string('progress_label', 'mod_aiactivities'); ?>
                    <span id="ala-current-step">1</span>
                    <?php echo get_string('of_label', 'mod_aiactivities'); ?>
                    <span id="ala-total-steps">0</span>
                </span>
                <div class="ala-player-progress-bar">
                    <div class="ala-player-progress-fill" id="ala-player-progress-fill"></div>
                </div>
            </div>
        </div>
        <div id="ala-player-content" class="ala-player-content"></div>
        <div id="ala-player-feedback" class="ala-player-feedback ala-feedback-hidden"></div>
        <div id="ala-player-complete" class="ala-complete-screen" style="display:none;">
            <div class="ala-complete-content">
                <div class="ala-complete-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                </div>
                <h2 class="ala-complete-title"><?php echo get_string('complete_title', 'mod_aiactivities'); ?></h2>
                <p class="ala-complete-message"><?php echo get_string('complete_message', 'mod_aiactivities'); ?></p>
                <div class="ala-complete-actions">
                    <a id="ala-continue-btn" href="#" class="ala-btn ala-btn-primary" style="display:none;margin-top:20px;">
                        Continue
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                    <button type="button" id="ala-practice-again-btn" class="ala-btn ala-btn-outline" style="margin-top:12px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                        Practice Activities Again
                    </button>
                    <button type="button" id="ala-review-btn" class="ala-btn ala-btn-outline" style="margin-top:8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        Review Activities
                    </button>
                </div>
            </div>
        </div>
        <div id="ala-review-nav" class="ala-review-nav" style="display:none;">
            <button type="button" id="ala-review-prev" class="ala-btn ala-btn-outline ala-review-nav-btn" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Previous
            </button>
            <span id="ala-review-indicator" class="ala-review-indicator"></span>
            <button type="button" id="ala-review-next" class="ala-btn ala-btn-outline ala-review-nav-btn">
                Next
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
            <button type="button" id="ala-review-done" class="ala-btn ala-btn-primary ala-review-done-btn">
                Done
            </button>
        </div>
    </div>
    <?php
}

if (!$hasactivities && !$cancreate) {
    echo $OUTPUT->notification('No activities have been generated yet. Please check back later.', 'info');
}

?>
<script>
    var ALA_CONFIG = {
        cmid: <?php echo $cm->id; ?>,
        instanceid: <?php echo $instance->id; ?>,
        sesskey: '<?php echo sesskey(); ?>',
        cancreate: <?php echo $cancreate ? 'true' : 'false'; ?>,
        hasactivities: <?php echo $hasactivities ? 'true' : 'false'; ?>,
        activitiesjson: <?php echo $hasactivities ? \mod_aiactivities\manifest_storage::decompress($instance->activitiesjson) : 'null'; ?>,
        ajaxurl: '<?php echo (new moodle_url('/mod/aiactivities/ajax.php'))->out(false); ?>',
        strings: {
            generating: '<?php echo addslashes(get_string('generating', 'mod_aiactivities')); ?>',
            generate_success: '<?php echo addslashes(get_string('generate_success', 'mod_aiactivities')); ?>',
            error_no_content: '<?php echo addslashes(get_string('error_no_content', 'mod_aiactivities')); ?>',
            feedback_correct: '<?php echo addslashes(get_string('feedback_correct', 'mod_aiactivities')); ?>',
            feedback_incorrect: '<?php echo addslashes(get_string('feedback_incorrect', 'mod_aiactivities')); ?>',
            feedback_welldone: '<?php echo addslashes(get_string('feedback_welldone', 'mod_aiactivities')); ?>',
            complete_title: '<?php echo addslashes(get_string('complete_title', 'mod_aiactivities')); ?>',
            complete_message: '<?php echo addslashes(get_string('complete_message', 'mod_aiactivities')); ?>',
            check_btn: '<?php echo addslashes(get_string('check_btn', 'mod_aiactivities')); ?>',
            next_btn: '<?php echo addslashes(get_string('next_btn', 'mod_aiactivities')); ?>',
            next_card_btn: '<?php echo addslashes(get_string('next_card_btn', 'mod_aiactivities')); ?>',
            truefalse_answer: '<?php echo addslashes(get_string('truefalse_answer', 'mod_aiactivities')); ?>',
            tryagain_btn: '<?php echo addslashes(get_string('tryagain_btn', 'mod_aiactivities')); ?>',
            ordering_instructions: '<?php echo addslashes(get_string('activity_ordering_instructions', 'mod_aiactivities')); ?>',
            categorysort_instructions: '<?php echo addslashes(get_string('activity_categorysort_instructions', 'mod_aiactivities')); ?>',
            columnsort_instructions: '<?php echo addslashes(get_string('activity_columnsort_instructions', 'mod_aiactivities')); ?>',
            cardselect_instructions: '<?php echo addslashes(get_string('activity_cardselect_instructions', 'mod_aiactivities')); ?>',
            matching_instructions: '<?php echo addslashes(get_string('activity_matching_instructions', 'mod_aiactivities')); ?>',
            flashcards_instructions: '<?php echo addslashes(get_string('activity_flashcards_instructions', 'mod_aiactivities')); ?>',
            flashcard_front: '<?php echo addslashes(get_string('flashcard_front', 'mod_aiactivities')); ?>',
            flashcard_back: '<?php echo addslashes(get_string('flashcard_back', 'mod_aiactivities')); ?>',
            flashcard_tap: '<?php echo addslashes(get_string('flashcard_tap', 'mod_aiactivities')); ?>',
            flashcard_done: '<?php echo addslashes(get_string('flashcard_done', 'mod_aiactivities')); ?>',
            truefalseswipe_instructions: '<?php echo addslashes(get_string('activity_truefalseswipe_instructions', 'mod_aiactivities')); ?>',
            truefalse_true: '<?php echo addslashes(get_string('truefalse_true', 'mod_aiactivities')); ?>',
            truefalse_false: '<?php echo addslashes(get_string('truefalse_false', 'mod_aiactivities')); ?>',
            correct: '<?php echo addslashes(get_string('feedback_correct', 'mod_aiactivities')); ?>',
            incorrect: '<?php echo addslashes(get_string('incorrect', 'mod_aiactivities')); ?>',
            truefalse_activity_complete: '<?php echo addslashes(get_string('truefalse_activity_complete', 'mod_aiactivities')); ?>',
            fillinblank_instructions: '<?php echo addslashes(get_string('activity_fillinblank_instructions', 'mod_aiactivities')); ?>',
            fillinblank_wordbank: '<?php echo addslashes(get_string('fillinblank_wordbank', 'mod_aiactivities')); ?>'
        },
        nextactivityurl: '<?php echo addslashes($nextactivityurl); ?>',
        voiceoverenabled: <?php echo !empty($instance->voiceoverenabled) ? 'true' : 'false'; ?>,
        voicegender: '<?php echo addslashes($instance->voicegender ?? 'female'); ?>',
        voiceid: '<?php echo addslashes($instance->voiceid ?? 'Zephyr'); ?>'
    };
</script>
<script>
    (function() {
        var toggle = document.getElementById('ala-scenario-toggle');
        var fields = document.getElementById('ala-scenario-fields');
        if (toggle && fields) {
            toggle.addEventListener('change', function() {
                fields.style.display = this.checked ? 'block' : 'none';
            });
        }
        var voToggle = document.getElementById('ala-voiceover-toggle');
        var voFields = document.getElementById('ala-voiceover-fields');
        if (voToggle && voFields) {
            voToggle.addEventListener('change', function() {
                voFields.style.display = this.checked ? 'block' : 'none';
            });
        }
    })();
</script>
<?php

$PAGE->requires->js_call_amd('mod_aiactivities/player', 'init');

echo $OUTPUT->footer();
