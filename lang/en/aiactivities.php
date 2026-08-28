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
 * English language strings for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'AI Learning Activities';
$string['modulenameplural'] = 'AI Learning Activities';
$string['modulename_help'] = 'AI Learning Activities turns any pasted text content into 8 types of mobile-first interactive revision activities for students to complete in sequence.

The 8 activity types are: Put in Order (drag items into the correct sequence), Sort into Categories (drop items into labelled category columns), Sort into Columns (place items into columns as they appear one at a time), Select the Correct Answer (tap the correct card from a set of options), Match the Pairs (tap an item on the left then tap its matching partner on the right), Flashcards (tap each card to reveal the answer), True or False Swipe (read a statement and swipe or tap True or False), and Fill in the Blank (tap a gap then select the correct word from the word bank).

Scenario mode wraps every activity in a realistic workplace context — teachers specify country, industry, and worker level so that the framing reflects the students\' actual work environment. All activities support drag-and-drop on desktop and tap-based interaction on touchscreen and mobile devices.

Students receive sound effects and celebration animations on completion. Unlimited attempts are allowed by default. Activity completion can require all activities to be answered correctly across the full set. Credit cost: 2 credits per activity generated.';
$string['pluginadministration'] = 'AI Learning Activities administration';
$string['pluginname'] = 'AI Learning Activities';

$string['activityname'] = 'Activity name';
$string['noinstances'] = 'There are no AI Learning Activities in this course.';

$string['apiurl'] = 'API URL';
$string['apiurl_desc'] = 'The Essay Grader AI API endpoint URL.';
$string['siteid'] = 'Site ID';
$string['siteid_desc'] = 'Your Moodle site identifier for the AI Grader service.';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Your API key for the AI Grader service.';
$string['credits_heading'] = 'Credit usage';
$string['credits_info'] = 'AI Learning Activities uses 2 credits per activity generated. For example, generating 5 activities costs 10 credits.';
$string['credits_label'] = 'credits';

$string['not_configured'] = 'AI Learning Activities is not configured. Please ask your administrator to set up the Site ID and API Key in the plugin settings or install AI Grader Central Config.';

$string['page_heading'] = 'Generate Learning Activities';
$string['page_intro'] = 'Paste your learning content below and AI will create interactive revision activities for your students.';

$string['content_label'] = 'Learning content';
$string['content_placeholder'] = 'Paste your learning content here... This could be lecture notes, textbook content, training materials, procedures, safety guidelines, etc.';
$string['content_help'] = 'Paste the content you want students to revise. The AI will analyse this and create interactive activities based on key concepts, procedures, and facts.';

$string['language_label'] = 'Language';
$string['language_help'] = 'Select the language for the generated activities.';

$string['activitycount_label'] = 'Number of activities';
$string['activitycount_help'] = 'Choose how many learning activities to generate. Each activity costs 2 credits.';

$string['generate_btn'] = 'Generate activities';
$string['generating'] = 'Generating activities...';
$string['generate_success'] = 'Activities generated successfully!';
$string['regenerate_btn'] = 'Regenerate activities';

$string['completionallcorrect'] = 'Student must complete all activities correctly';
$string['completionallcorrect_help'] = 'When enabled, students must successfully complete every activity to achieve completion. Students have unlimited attempts.';
$string['completiondetail:completionallcorrect'] = 'Complete all activities correctly';

$string['start_btn'] = 'Start activities';
$string['next_btn'] = 'Next';
$string['next_card_btn'] = 'Next card';
$string['check_btn'] = 'Check answer';
$string['tryagain_btn'] = 'Try again';
$string['complete_title'] = 'All activities complete!';
$string['complete_message'] = 'Congratulations! You have successfully completed all learning activities.';
$string['progress_label'] = 'Activity';
$string['of_label'] = 'of';

$string['activity_ordering'] = 'Put in Order';
$string['activity_ordering_instructions'] = 'Arrange the items in the correct order by dragging or tapping.';
$string['activity_categorysort'] = 'Sort into Categories';
$string['activity_categorysort_instructions'] = 'Sort each item into the correct category. Wrong answers return to the start.';
$string['activity_columnsort'] = 'Sort into Columns';
$string['activity_columnsort_instructions'] = 'Place each item into the correct column as they appear.';
$string['activity_cardselect'] = 'Select the Correct Answer';
$string['activity_cardselect_instructions'] = 'Choose the card that best answers the question.';
$string['activity_matching'] = 'Match the Pairs';
$string['activity_matching_instructions'] = 'Tap an item on the left, then tap its matching partner on the right.';

$string['activity_flashcards'] = 'Flashcards';
$string['activity_flashcards_instructions'] = 'Tap each card to reveal the answer, then move to the next card.';
$string['flashcard_front'] = 'Question';
$string['flashcard_back'] = 'Answer';
$string['flashcard_tap'] = 'Tap to reveal';
$string['flashcard_done'] = 'Done';

$string['activity_truefalseswipe'] = 'True or False';
$string['activity_truefalseswipe_instructions'] = 'Read each statement and decide whether it is true or false.';
$string['truefalse_true'] = 'True';
$string['truefalse_false'] = 'False';
$string['truefalse_answer'] = 'Answer';

$string['activity_fillinblank'] = 'Fill in the Blank';
$string['activity_fillinblank_instructions'] = 'Tap a blank space, then tap a word from the bank below to fill it in.';
$string['fillinblank_wordbank'] = 'Word bank';

$string['feedback_correct'] = 'Correct!';
$string['feedback_incorrect'] = 'Not quite right. Try again!';
$string['feedback_welldone'] = 'Well done!';
$string['feedback_keepgoing'] = 'Keep going!';
$string['incorrect'] = 'Incorrect!';
$string['truefalse_activity_complete'] = 'Activity done.';

$string['scenario_toggle'] = 'Scenario-based questions';
$string['scenario_help'] = 'When enabled, the AI will wrap each activity in a realistic workplace scenario. Optionally specify a country, industry, and worker level to make the scenarios more relevant.';
$string['scenario_country'] = 'Country';
$string['scenario_country_placeholder'] = 'e.g. Australia';
$string['scenario_industry'] = 'Industry';
$string['scenario_industry_placeholder'] = 'e.g. Construction';
$string['scenario_worker_level'] = 'Worker Level';
$string['scenario_worker_placeholder'] = 'e.g. Apprentice';

$string['error_no_content'] = 'Please paste some learning content.';
$string['error_generation_failed'] = 'Failed to generate activities. Please try again.';
$string['error_not_enough_credits'] = 'Not enough credits. You need {$a} credits for this generation.';

$string['privacy:metadata:attempts'] = 'Information about user attempts in AI Learning Activities.';
$string['privacy:metadata:attempts:userid'] = 'The ID of the user who made the attempt.';
$string['privacy:metadata:attempts:progressjson'] = 'The progress data for the attempt.';
$string['privacy:metadata:attempts:score'] = 'The score achieved.';
$string['privacy:metadata:attempts:timecreated'] = 'The time the attempt was created.';

$string['aiactivities:addinstance'] = 'Add a new AI Learning Activities instance';
$string['aiactivities:view'] = 'View AI Learning Activities';
$string['aiactivities:create'] = 'Create/generate AI Learning Activities content';

$string['lang_en_au'] = 'English (Australian)';
$string['lang_en_gb'] = 'English (British)';
$string['lang_en_us'] = 'English (American)';
$string['lang_en_in'] = 'English (Indian)';
$string['lang_es_es'] = 'Spanish (Spain)';
$string['lang_fr_fr'] = 'French (France)';
$string['lang_de_de'] = 'German';
$string['lang_pt_br'] = 'Portuguese (Brazil)';
$string['lang_zh_cn'] = 'Chinese (Simplified)';
$string['lang_ja_jp'] = 'Japanese';
$string['lang_ko_kr'] = 'Korean';
$string['lang_ar_sa'] = 'Arabic';
$string['lang_hi_in'] = 'Hindi';
$string['lang_it_it'] = 'Italian';
$string['lang_nl_nl'] = 'Dutch';
$string['lang_sv_se'] = 'Swedish';
$string['lang_da_dk'] = 'Danish';
$string['lang_nb_no'] = 'Norwegian';
$string['lang_fi_fi'] = 'Finnish';
$string['lang_pl_pl'] = 'Polish';
$string['lang_th_th'] = 'Thai';
$string['lang_vi_vn'] = 'Vietnamese';
$string['lang_id_id'] = 'Indonesian';
$string['lang_ms_my'] = 'Malay';
$string['lang_tl_ph'] = 'Filipino';
$string['lang_tr_tr'] = 'Turkish';

// Strings used by the view page and templates.
$string['activitycount_many'] = '{$a} activities';
$string['back_to_activities'] = 'Back to activities';
$string['continue_btn'] = 'Continue';
$string['cost_per_activity'] = 'Cost per activity';
$string['cost_total'] = 'Total cost';
$string['done_btn'] = 'Done';
$string['langgroup_asian'] = 'Asian';
$string['langgroup_english'] = 'English';
$string['langgroup_european'] = 'European';
$string['langgroup_nordic'] = 'Nordic';
$string['langgroup_other'] = 'Other';
$string['level_executive'] = 'Executive';
$string['level_manager'] = 'Manager';
$string['level_supervisor'] = 'Supervisor';
$string['level_worker'] = 'Worker';
$string['manage_heading'] = 'Activities generated';
$string['manage_live_banner'] = 'These activities are live and available for students to complete. Use the options below to preview, edit, or regenerate them.';
$string['no_activities_yet'] = 'No activities have been generated yet. Please check back later.';
$string['optional_hint'] = '(optional)';
$string['practice_again_btn'] = 'Practice activities again';
$string['previous_btn'] = 'Previous';
$string['regenerate_audio_btn'] = 'Regenerate audio';
$string['review_btn'] = 'Review activities';
$string['scenario_jobroles'] = 'Job roles';
$string['scenario_jobroles_hint'] = '(up to 5, press Enter to add each one)';
$string['scenario_jobroles_placeholder'] = 'For example: Site supervisor, Project manager';
$string['scenario_industry_placeholder_select'] = 'Select an industry...';
$string['scenario_sector'] = 'Industry sector';
$string['scenario_sector_placeholder'] = 'Select an industry first...';
$string['scenario_worker_level_hint'] = '(select one or more)';
$string['voice_gender'] = 'Voice gender';
$string['voice_gender_female'] = 'Female';
$string['voice_gender_male'] = 'Male';
$string['voice_label'] = 'Voice';
$string['voice_option_female'] = '{$a} (female)';
$string['voice_option_male'] = '{$a} (male)';
$string['voiceover_credit_note'] = '(+1 credit per activity)';
$string['voiceover_help'] = 'Generate spoken audio narration for each activity using AI text to speech.';
$string['voiceover_toggle'] = 'Add AI voiceover narration';

// Settings page.
$string['centralconfig_installed'] = 'AI Grader Central Config is installed. The site ID and API key are managed centrally. <a href="{$a}">Configure the central settings</a>.';
$string['centralconfig_recommended'] = 'Recommended: install AI Grader Central Config to set the site ID and API key once for all AI Grader plugins.';
$string['centralconfig_fallback'] = 'This value is only used when AI Grader Central Config is not configured.';

// Course reset.
$string['reset_attempts'] = 'Delete all student attempts in AI Learning Activities';

// Errors.
$string['error_audio_failed'] = 'The narration audio could not be generated. Please try again.';
$string['error_connection'] = 'The generation service could not be reached. Please check the API URL and try again.';
$string['error_credits_failed'] = 'The credit balance could not be retrieved.';
$string['error_generation_start'] = 'The generation job could not be started. Please try again.';
$string['error_invalid_activities'] = 'The activity data was not in a format this plugin understands.';
$string['error_invalid_index'] = 'That activity does not exist.';
$string['error_invalid_progress'] = 'The progress data was not in a format this plugin understands.';
$string['error_job_status'] = 'The status of the generation job could not be checked.';
$string['error_no_activities'] = 'No activities have been generated for this instance yet.';
$string['error_unknown_action'] = 'That action is not supported.';
$string['invalidattempt'] = 'That attempt does not belong to you.';

// Privacy.
$string['privacy:metadata:attempts:currentactivity'] = 'The activity the user had reached.';
$string['privacy:metadata:attempts:completedcount'] = 'How many activities the user has completed.';
$string['privacy:metadata:attempts:status'] = 'Whether the attempt is in progress or complete.';
$string['privacy:metadata:attempts:timemodified'] = 'The time the attempt was last changed.';
$string['privacy:metadata:service'] = 'Learning content entered by a teacher is sent to the configured generation service so that activities can be created from it. No student data is sent.';
$string['privacy:metadata:service:siteid'] = 'The site identifier that the generation service uses to identify the subscription.';
$string['privacy:metadata:service:content'] = 'The learning content the teacher entered.';
$string['privacy:metadata:service:language'] = 'The language the activities should be generated in.';
$string['privacy:metadata:service:scenario'] = 'The optional scenario settings: country, industry, sector, worker level and job roles.';
$string['privacy:metadata:service:voice'] = 'The narration settings: whether narration is requested, and which voice to use.';
$string['privacy:path:attempts'] = 'AI Learning Activities attempts';
$string['status_completed'] = 'Complete';
$string['status_inprogress'] = 'In progress';

// Strings used by the player module.
$string['correct'] = 'Correct!';
$string['js_a11y_blank'] = 'Blank';
$string['js_a11y_placeincategory'] = 'Place the current item in this category';
$string['js_a11y_placeincolumn'] = 'Place the current item in this column';
$string['js_activities_ready'] = 'Activities generated and ready for students.';
$string['js_audio_generating'] = 'Generating audio...';
$string['js_audio_regenerate'] = 'Regenerate audio';
$string['js_audio_regenerated'] = 'Audio regenerated successfully.';
$string['js_credits_unit'] = 'credits';
$string['js_dialog_title'] = 'AI learning activities';
$string['js_edit_activity'] = 'Edit activity';
$string['js_edit_belongsto'] = 'belongs to';
$string['js_edit_blank'] = 'Blank';
$string['js_edit_cancel'] = 'Cancel';
$string['js_edit_card'] = 'Card';
$string['js_edit_category'] = 'Category';
$string['js_edit_correctanswer'] = '(correct answer)';
$string['js_edit_heading'] = 'Editing: ';
$string['js_edit_item'] = 'Item';
$string['js_edit_lbl_blankanswers'] = 'Blank answers';
$string['js_edit_lbl_cards'] = 'Cards';
$string['js_edit_lbl_categories'] = 'Categories';
$string['js_edit_lbl_colaname'] = 'Column A name';
$string['js_edit_lbl_colbname'] = 'Column B name';
$string['js_edit_lbl_flashcards'] = 'Flashcards';
$string['js_edit_lbl_items'] = 'Items';
$string['js_edit_lbl_orderitems'] = 'Items (in correct order)';
$string['js_edit_lbl_pairs'] = 'Matching pairs';
$string['js_edit_lbl_question'] = 'Question';
$string['js_edit_lbl_sentence'] = 'Sentence (use ___1___, ___2___ etc for blanks)';
$string['js_edit_lbl_statements'] = 'Statements';
$string['js_edit_lbl_title'] = 'Title';
$string['js_edit_pair'] = 'Pair';
$string['js_edit_ph_back'] = 'Back';
$string['js_edit_ph_description'] = 'Description';
$string['js_edit_ph_explanation'] = 'Explanation';
$string['js_edit_ph_front'] = 'Front';
$string['js_edit_ph_label'] = 'Label';
$string['js_edit_ph_left'] = 'Left';
$string['js_edit_ph_right'] = 'Right';
$string['js_edit_ph_statement'] = 'Statement';
$string['js_edit_save'] = 'Save changes';
$string['js_edit_saving'] = 'Saving...';
$string['js_edit_statement'] = 'Statement';
$string['js_edit_step'] = 'Step';
$string['js_err_audiofailed'] = 'Audio regeneration failed.';
$string['js_err_genfailed'] = 'Generation failed.';
$string['js_err_genstart'] = 'Could not start generating activities.';
$string['js_err_gentimeout'] = 'Generation is taking longer than expected. please try again.';
$string['js_err_http'] = 'Request failed';
$string['js_err_noaudioactivities'] = 'There are no activities to generate audio for.';
$string['js_err_parse'] = 'Could not read the server response.';
$string['js_err_pollfailed'] = 'Could not check the generation job.';
$string['js_err_savefailed'] = 'Could not save your changes.';
$string['js_err_savegenerated'] = 'Could not save the generated activities.';
$string['js_err_saveprogress'] = 'Your progress could not be saved. please check your connection.';
$string['js_err_unknown'] = 'Unknown error.';
$string['js_err_unknowntype'] = 'Unknown activity type';
$string['js_eta_activities'] = 'activities';
$string['js_eta_hour'] = 'hour';
$string['js_eta_hours'] = 'hours';
$string['js_eta_hr'] = 'hr';
$string['js_eta_hrs'] = 'hrs';
$string['js_eta_label'] = 'Estimated completion time';
$string['js_eta_min'] = 'min';
$string['js_eta_minute'] = 'minute';
$string['js_eta_minutes'] = 'minutes';
$string['js_eta_underminute'] = 'Under 1 minute';
$string['js_generate_btn'] = 'Generate activities';
$string['js_matching_from'] = 'Match from';
$string['js_matching_to'] = 'Match to';
$string['js_nav_locked'] = 'Complete all activities to continue';
$string['js_preview_activity'] = 'Preview activity';
$string['js_preview_complete'] = 'Activity complete! returning to list...';
$string['js_preview_heading'] = 'Preview: ';
$string['js_role_remove'] = 'Remove this job role';
$string['js_sector_placeholder'] = 'Select sector (optional)...';
$string['js_type_cardselect'] = 'Card select';
$string['js_type_categorysort'] = 'Category sort';
$string['js_type_columnsort'] = 'Column sort';
$string['js_type_fillinblank'] = 'Fill in the blank';
$string['js_type_flashcards'] = 'Flashcards';
$string['js_type_matching'] = 'Matching pairs';
$string['js_type_ordering'] = 'Put in order';
$string['js_type_truefalse'] = 'True or False';
$string['js_voiceover_controls'] = 'Narration controls';
$string['js_voiceover_mute'] = 'Mute narration';
$string['js_voiceover_play'] = 'Play narration';
$string['js_voiceover_stop'] = 'Stop narration';
$string['js_voiceover_unmute'] = 'Unmute narration';
$string['js_voiceover_stop_label'] = 'Stop the spoken narration for this activity';

// Industry and sector vocabulary for scenario mode.

$string['industry_agedcare'] = 'Aged Care';
$string['sector_agedca_residentialagedcare'] = 'Residential Aged Care';
$string['sector_agedca_homecareservices'] = 'Home Care Services';
$string['sector_agedca_dementiacare'] = 'Dementia Care';
$string['sector_agedca_palliativecare'] = 'Palliative Care';
$string['sector_agedca_communityagedcare'] = 'Community Aged Care';
$string['sector_agedca_retirementliving'] = 'Retirement Living';
$string['sector_agedca_respitecare'] = 'Respite Care';
$string['sector_agedca_alliedhealthinagedcare'] = 'Allied Health in Aged Care';
$string['industry_agriculture'] = 'Agriculture';
$string['sector_agricu_croppingandgrain'] = 'Cropping & Grain';
$string['sector_agricu_livestockandcattle'] = 'Livestock & Cattle';
$string['sector_agricu_dairyfarming'] = 'Dairy Farming';
$string['sector_agricu_horticulture'] = 'Horticulture';
$string['sector_agricu_viticultureandwine'] = 'Viticulture & Wine';
$string['sector_agricu_aquaculture'] = 'Aquaculture';
$string['sector_agricu_poultry'] = 'Poultry';
$string['sector_agricu_shearingandwool'] = 'Shearing & Wool';
$string['sector_agricu_agriculturalcontracting'] = 'Agricultural Contracting';
$string['sector_agricu_irrigationandwatermanagement'] = 'Irrigation & Water Management';
$string['industry_automotive'] = 'Automotive';
$string['sector_automo_lightvehiclemechanical'] = 'Light Vehicle Mechanical';
$string['sector_automo_heavyvehiclemechanical'] = 'Heavy Vehicle Mechanical';
$string['sector_automo_autoelectrical'] = 'Auto Electrical';
$string['sector_automo_panelbeatingandspraypainting'] = 'Panel Beating & Spray Painting';
$string['sector_automo_motorcycletechnician'] = 'Motorcycle Technician';
$string['sector_automo_marinemechanical'] = 'Marine Mechanical';
$string['sector_automo_automotivepartsandaccessories'] = 'Automotive Parts & Accessories';
$string['sector_automo_vehiclesales'] = 'Vehicle Sales';
$string['sector_automo_tyrefitting'] = 'Tyre Fitting';
$string['industry_aviation'] = 'Aviation';
$string['sector_aviati_commercialaviation'] = 'Commercial Aviation';
$string['sector_aviati_generalaviation'] = 'General Aviation';
$string['sector_aviati_aircraftmaintenance'] = 'Aircraft Maintenance';
$string['sector_aviati_groundoperations'] = 'Ground Operations';
$string['sector_aviati_airtrafficcontrol'] = 'Air Traffic Control';
$string['sector_aviati_cabincrew'] = 'Cabin Crew';
$string['sector_aviati_aviationsecurity'] = 'Aviation Security';
$string['sector_aviati_helicopteroperations'] = 'Helicopter Operations';
$string['industry_buildingandconstruction'] = 'Building & Construction';
$string['sector_buildi_residentialconstruction'] = 'Residential Construction';
$string['sector_buildi_commercialconstruction'] = 'Commercial Construction';
$string['sector_buildi_civilconstruction'] = 'Civil Construction';
$string['sector_buildi_miningconstruction'] = 'Mining Construction';
$string['sector_buildi_industrialconstruction'] = 'Industrial Construction';
$string['sector_buildi_highriseconstruction'] = 'High-Rise Construction';
$string['sector_buildi_renovationandrefurbishment'] = 'Renovation & Refurbishment';
$string['sector_buildi_demolition'] = 'Demolition';
$string['sector_buildi_scaffolding'] = 'Scaffolding';
$string['sector_buildi_formwork'] = 'Formwork';
$string['sector_buildi_concreting'] = 'Concreting';
$string['sector_buildi_steelfixing'] = 'Steel Fixing';
$string['sector_buildi_carpentry'] = 'Carpentry';
$string['sector_buildi_bricklaying'] = 'Bricklaying';
$string['sector_buildi_tiling'] = 'Tiling';
$string['sector_buildi_paintinganddecorating'] = 'Painting & Decorating';
$string['sector_buildi_plastering'] = 'Plastering';
$string['sector_buildi_roofing'] = 'Roofing';
$string['sector_buildi_glazing'] = 'Glazing';
$string['sector_buildi_waterproofing'] = 'Waterproofing';
$string['industry_businessservices'] = 'Business Services';
$string['sector_busine_accountingandbookkeeping'] = 'Accounting & Bookkeeping';
$string['sector_busine_humanresources'] = 'Human Resources';
$string['sector_busine_marketingandadvertising'] = 'Marketing & Advertising';
$string['sector_busine_legalservices'] = 'Legal Services';
$string['sector_busine_consulting'] = 'Consulting';
$string['sector_busine_recruitment'] = 'Recruitment';
$string['sector_busine_traininganddevelopment'] = 'Training & Development';
$string['sector_busine_propertymanagement'] = 'Property Management';
$string['sector_busine_cleaningservices'] = 'Cleaning Services';
$string['sector_busine_securityservices'] = 'Security Services';
$string['industry_childcare'] = 'Childcare';
$string['sector_childc_longdaycare'] = 'Long Day Care';
$string['sector_childc_familydaycare'] = 'Family Day Care';
$string['sector_childc_outsideschoolhourscare'] = 'Outside School Hours Care';
$string['sector_childc_kindergartenpreschool'] = 'Kindergarten/Preschool';
$string['sector_childc_occasionalcare'] = 'Occasional Care';
$string['sector_childc_inhomecare'] = 'In-Home Care';
$string['sector_childc_specialneedssupport'] = 'Special Needs Support';
$string['sector_childc_earlyintervention'] = 'Early Intervention';
$string['industry_communityservices'] = 'Community Services';
$string['sector_commun_disabilitysupport'] = 'Disability Support';
$string['sector_commun_mentalhealthsupport'] = 'Mental Health Support';
$string['sector_commun_youthwork'] = 'Youth Work';
$string['sector_commun_familyservices'] = 'Family Services';
$string['sector_commun_homelessnessservices'] = 'Homelessness Services';
$string['sector_commun_drugandalcoholservices'] = 'Drug & Alcohol Services';
$string['sector_commun_aboriginalandtorresstraitislanderservices'] = 'Aboriginal & Torres Strait Islander Services';
$string['sector_commun_refugeeandmigrantservices'] = 'Refugee & Migrant Services';
$string['sector_commun_domesticviolencesupport'] = 'Domestic Violence Support';
$string['sector_commun_casemanagement'] = 'Case Management';
$string['industry_education'] = 'Education';
$string['sector_educat_primaryeducation'] = 'Primary Education';
$string['sector_educat_secondaryeducation'] = 'Secondary Education';
$string['sector_educat_vocationaleducationvet'] = 'Vocational Education (VET)';
$string['sector_educat_highereducationuniversity'] = 'Higher Education/University';
$string['sector_educat_tafe'] = 'Vocational education and training';
$string['sector_educat_adulteducation'] = 'Adult Education';
$string['sector_educat_specialeducation'] = 'Special Education';
$string['sector_educat_earlychildhoodeducation'] = 'Early Childhood Education';
$string['sector_educat_onlinedistanceeducation'] = 'Online/Distance Education';
$string['sector_educat_educationsupport'] = 'Education Support';
$string['sector_educat_trainingadministration'] = 'Training Administration';
$string['sector_educat_schooladministration'] = 'School Administration';
$string['sector_educat_privatetrainingproviderrto'] = 'Private Training Provider (RTO)';
$string['industry_electrical'] = 'Electrical';
$string['sector_electr_domesticelectrical'] = 'Domestic Electrical';
$string['sector_electr_commercialelectrical'] = 'Commercial Electrical';
$string['sector_electr_industrialelectrical'] = 'Industrial Electrical';
$string['sector_electr_instrumentation'] = 'Instrumentation';
$string['sector_electr_refrigerationandairconditioning'] = 'Refrigeration & Air Conditioning';
$string['sector_electr_solarinstallation'] = 'Solar Installation';
$string['sector_electr_dataandcommunications'] = 'Data & Communications';
$string['sector_electr_fireprotectionsystems'] = 'Fire Protection Systems';
$string['sector_electr_liftinstallation'] = 'Lift Installation';
$string['industry_engineering'] = 'Engineering';
$string['sector_engine_mechanicalengineering'] = 'Mechanical Engineering';
$string['sector_engine_civilengineering'] = 'Civil Engineering';
$string['sector_engine_structuralengineering'] = 'Structural Engineering';
$string['sector_engine_electricalengineering'] = 'Electrical Engineering';
$string['sector_engine_chemicalengineering'] = 'Chemical Engineering';
$string['sector_engine_miningengineering'] = 'Mining Engineering';
$string['sector_engine_environmentalengineering'] = 'Environmental Engineering';
$string['sector_engine_projectengineering'] = 'Project Engineering';
$string['sector_engine_maintenanceengineering'] = 'Maintenance Engineering';
$string['industry_finance'] = 'Finance';
$string['sector_financ_banking'] = 'Banking';
$string['sector_financ_insurance'] = 'Insurance';
$string['sector_financ_financialplanning'] = 'Financial Planning';
$string['sector_financ_mortgagebroking'] = 'Mortgage Broking';
$string['sector_financ_creditandlending'] = 'Credit & Lending';
$string['sector_financ_superannuation'] = 'Superannuation';
$string['sector_financ_investmentmanagement'] = 'Investment Management';
$string['sector_financ_payroll'] = 'Payroll';
$string['sector_financ_accountspayablereceivable'] = 'Accounts Payable/Receivable';
$string['sector_financ_auditing'] = 'Auditing';
$string['industry_foodprocessing'] = 'Food Processing';
$string['sector_foodpr_meatprocessing'] = 'Meat Processing';
$string['sector_foodpr_seafoodprocessing'] = 'Seafood Processing';
$string['sector_foodpr_dairyprocessing'] = 'Dairy Processing';
$string['sector_foodpr_bakery'] = 'Bakery';
$string['sector_foodpr_beveragemanufacturing'] = 'Beverage Manufacturing';
$string['sector_foodpr_confectionery'] = 'Confectionery';
$string['sector_foodpr_fruitandvegetableprocessing'] = 'Fruit & Vegetable Processing';
$string['sector_foodpr_readymealsandconveniencefoods'] = 'Ready Meals & Convenience Foods';
$string['sector_foodpr_qualityassurance'] = 'Quality Assurance';
$string['sector_foodpr_foodsafety'] = 'Food Safety';
$string['industry_government'] = 'Government';
$string['sector_govern_localgovernment'] = 'Local Government';
$string['sector_govern_stategovernment'] = 'State Government';
$string['sector_govern_federalgovernment'] = 'Federal Government';
$string['sector_govern_emergencyservices'] = 'Emergency Services';
$string['sector_govern_regulatoryandcompliance'] = 'Regulatory & Compliance';
$string['sector_govern_policyandplanning'] = 'Policy & Planning';
$string['sector_govern_customerservice'] = 'Customer Service';
$string['sector_govern_parksandrecreation'] = 'Parks & Recreation';
$string['sector_govern_infrastructure'] = 'Infrastructure';
$string['sector_govern_communityengagement'] = 'Community Engagement';
$string['industry_healthcare'] = 'Healthcare';
$string['sector_health_acutecarehospital'] = 'Acute Care/Hospital';
$string['sector_health_primarycaregp'] = 'Primary Care/GP';
$string['sector_health_alliedhealth'] = 'Allied Health';
$string['sector_health_mentalhealth'] = 'Mental Health';
$string['sector_health_communityhealth'] = 'Community Health';
$string['sector_health_dental'] = 'Dental';
$string['sector_health_pharmacy'] = 'Pharmacy';
$string['sector_health_pathology'] = 'Pathology';
$string['sector_health_radiology'] = 'Radiology';
$string['sector_health_emergencyservices'] = 'Emergency Services';
$string['sector_health_surgical'] = 'Surgical';
$string['sector_health_rehabilitation'] = 'Rehabilitation';
$string['sector_health_infectioncontrol'] = 'Infection Control';
$string['sector_health_agedcarenursing'] = 'Aged Care Nursing';
$string['sector_health_midwifery'] = 'Midwifery';
$string['sector_health_disabilityhealth'] = 'Disability Health';
$string['sector_health_aboriginalhealth'] = 'Aboriginal Health';
$string['industry_hospitality'] = 'Hospitality';
$string['sector_hospit_hotelsandaccommodation'] = 'Hotels & Accommodation';
$string['sector_hospit_restaurantsandcafes'] = 'Restaurants & Cafes';
$string['sector_hospit_barsandpubs'] = 'Bars & Pubs';
$string['sector_hospit_catering'] = 'Catering';
$string['sector_hospit_eventsandfunctions'] = 'Events & Functions';
$string['sector_hospit_fastfoodandquickservice'] = 'Fast Food & Quick Service';
$string['sector_hospit_clubsandgaming'] = 'Clubs & Gaming';
$string['sector_hospit_commercialcookery'] = 'Commercial Cookery';
$string['sector_hospit_patisserie'] = 'Patisserie';
$string['sector_hospit_frontoffice'] = 'Front Office';
$string['sector_hospit_housekeeping'] = 'Housekeeping';
$string['industry_informationtechnology'] = 'Information Technology';
$string['sector_inform_softwaredevelopment'] = 'Software Development';
$string['sector_inform_networkadministration'] = 'Network Administration';
$string['sector_inform_cybersecurity'] = 'Cybersecurity';
$string['sector_inform_cloudcomputing'] = 'Cloud Computing';
$string['sector_inform_databaseadministration'] = 'Database Administration';
$string['sector_inform_itsupporthelpdesk'] = 'IT Support/Help Desk';
$string['sector_inform_webdevelopment'] = 'Web Development';
$string['sector_inform_dataanalytics'] = 'Data Analytics';
$string['sector_inform_systemsadministration'] = 'Systems Administration';
$string['sector_inform_itprojectmanagement'] = 'IT Project Management';
$string['industry_logistics'] = 'Logistics';
$string['sector_logist_supplychainmanagement'] = 'Supply Chain Management';
$string['sector_logist_freightforwarding'] = 'Freight Forwarding';
$string['sector_logist_customsandborder'] = 'Customs & Border';
$string['sector_logist_inventorymanagement'] = 'Inventory Management';
$string['sector_logist_distribution'] = 'Distribution';
$string['sector_logist_thirdpartylogistics3pl'] = 'Third-Party Logistics (3PL)';
$string['sector_logist_lastmiledelivery'] = 'Last Mile Delivery';
$string['sector_logist_coldchainlogistics'] = 'Cold Chain Logistics';
$string['sector_logist_dangerousgoods'] = 'Dangerous Goods';
$string['industry_manufacturing'] = 'Manufacturing';
$string['sector_manufa_foodandbeveragemanufacturing'] = 'Food & Beverage Manufacturing';
$string['sector_manufa_pharmaceuticalmanufacturing'] = 'Pharmaceutical Manufacturing';
$string['sector_manufa_chemicalmanufacturing'] = 'Chemical Manufacturing';
$string['sector_manufa_metalfabrication'] = 'Metal Fabrication';
$string['sector_manufa_plasticsandrubber'] = 'Plastics & Rubber';
$string['sector_manufa_textiles'] = 'Textiles';
$string['sector_manufa_furnituremanufacturing'] = 'Furniture Manufacturing';
$string['sector_manufa_electronicsmanufacturing'] = 'Electronics Manufacturing';
$string['sector_manufa_printing'] = 'Printing';
$string['sector_manufa_packaging'] = 'Packaging';
$string['sector_manufa_processmanufacturing'] = 'Process Manufacturing';
$string['industry_mining'] = 'Mining';
$string['sector_mining_opencutmining'] = 'Open Cut Mining';
$string['sector_mining_undergroundmining'] = 'Underground Mining';
$string['sector_mining_coalmining'] = 'Coal Mining';
$string['sector_mining_ironore'] = 'Iron Ore';
$string['sector_mining_goldmining'] = 'Gold Mining';
$string['sector_mining_mineralprocessing'] = 'Mineral Processing';
$string['sector_mining_exploration'] = 'Exploration';
$string['sector_mining_drilling'] = 'Drilling';
$string['sector_mining_minesiteservices'] = 'Mine Site Services';
$string['sector_mining_tailingsmanagement'] = 'Tailings Management';
$string['sector_mining_minerehabilitation'] = 'Mine Rehabilitation';
$string['industry_plumbing'] = 'Plumbing';
$string['sector_plumbi_domesticplumbing'] = 'Domestic Plumbing';
$string['sector_plumbi_commercialplumbing'] = 'Commercial Plumbing';
$string['sector_plumbi_industrialplumbing'] = 'Industrial Plumbing';
$string['sector_plumbi_gasfitting'] = 'Gas Fitting';
$string['sector_plumbi_roofinganddrainage'] = 'Roofing & Drainage';
$string['sector_plumbi_fireprotectionplumbing'] = 'Fire Protection Plumbing';
$string['sector_plumbi_irrigation'] = 'Irrigation';
$string['sector_plumbi_watertreatment'] = 'Water Treatment';
$string['sector_plumbi_mechanicalservices'] = 'Mechanical Services';
$string['industry_retail'] = 'Retail';
$string['sector_retail_supermarketsandgrocery'] = 'Supermarkets & Grocery';
$string['sector_retail_fashionandapparel'] = 'Fashion & Apparel';
$string['sector_retail_electronicsandtechnology'] = 'Electronics & Technology';
$string['sector_retail_hardwareandbuilding'] = 'Hardware & Building';
$string['sector_retail_pharmacyretail'] = 'Pharmacy Retail';
$string['sector_retail_furnitureandhomewares'] = 'Furniture & Homewares';
$string['sector_retail_automotiveretail'] = 'Automotive Retail';
$string['sector_retail_sportinggoods'] = 'Sporting Goods';
$string['sector_retail_onlineecommerce'] = 'Online/E-commerce';
$string['sector_retail_luxuryretail'] = 'Luxury Retail';
$string['industry_security'] = 'Security';
$string['sector_securi_staticsecurity'] = 'Static Security';
$string['sector_securi_mobilepatrol'] = 'Mobile Patrol';
$string['sector_securi_eventsecurity'] = 'Event Security';
$string['sector_securi_closeprotection'] = 'Close Protection';
$string['sector_securi_lossprevention'] = 'Loss Prevention';
$string['sector_securi_corporatesecurity'] = 'Corporate Security';
$string['sector_securi_cashintransit'] = 'Cash in Transit';
$string['sector_securi_cctvandmonitoring'] = 'Camera monitoring';
$string['sector_securi_accesscontrol'] = 'Access Control';
$string['sector_securi_cybersecurityoperations'] = 'Cybersecurity Operations';
$string['industry_sportandrecreation'] = 'Sport & Recreation';
$string['sector_sporta_fitnessandpersonaltraining'] = 'Fitness & Personal Training';
$string['sector_sporta_aquatics'] = 'Aquatics';
$string['sector_sporta_outdoorrecreation'] = 'Outdoor Recreation';
$string['sector_sporta_sportscoaching'] = 'Sports Coaching';
$string['sector_sporta_sportsadministration'] = 'Sports Administration';
$string['sector_sporta_communityrecreation'] = 'Community Recreation';
$string['sector_sporta_eventmanagement'] = 'Event Management';
$string['sector_sporta_golfandturfmanagement'] = 'Golf & Turf Management';
$string['sector_sporta_sportsmedicinesupport'] = 'Sports Medicine Support';
$string['industry_tourism'] = 'Tourism';
$string['sector_touris_travelagencies'] = 'Travel Agencies';
$string['sector_touris_touroperations'] = 'Tour Operations';
$string['sector_touris_attractionsandthemeparks'] = 'Attractions & Theme Parks';
$string['sector_touris_ecotourism'] = 'Eco-Tourism';
$string['sector_touris_adventuretourism'] = 'Adventure Tourism';
$string['sector_touris_culturaltourism'] = 'Cultural Tourism';
$string['sector_touris_cruiseoperations'] = 'Cruise Operations';
$string['sector_touris_tourismmarketing'] = 'Tourism Marketing';
$string['sector_touris_visitorinformationservices'] = 'Visitor Information Services';
$string['sector_touris_indigenoustourism'] = 'Indigenous Tourism';
$string['industry_transport'] = 'Transport';
$string['sector_transp_roadtransport'] = 'Road Transport';
$string['sector_transp_railtransport'] = 'Rail Transport';
$string['sector_transp_maritimetransport'] = 'Maritime Transport';
$string['sector_transp_airtransport'] = 'Air Transport';
$string['sector_transp_publictransport'] = 'Public Transport';
$string['sector_transp_taxiandrideshare'] = 'Taxi & Rideshare';
$string['sector_transp_courierservices'] = 'Courier Services';
$string['sector_transp_busoperations'] = 'Bus Operations';
$string['sector_transp_heavyvehicleoperations'] = 'Heavy Vehicle Operations';
$string['sector_transp_transportadministration'] = 'Transport Administration';
$string['industry_utilities'] = 'Utilities';
$string['sector_utilit_electricitygeneration'] = 'Electricity Generation';
$string['sector_utilit_electricitydistribution'] = 'Electricity Distribution';
$string['sector_utilit_gasdistribution'] = 'Gas Distribution';
$string['sector_utilit_watersupply'] = 'Water Supply';
$string['sector_utilit_wastewatertreatment'] = 'Wastewater Treatment';
$string['sector_utilit_renewableenergy'] = 'Renewable Energy';
$string['sector_utilit_smartgrid'] = 'Smart Grid';
$string['sector_utilit_meterreading'] = 'Meter Reading';
$string['sector_utilit_networkmaintenance'] = 'Network Maintenance';
$string['industry_warehousing'] = 'Warehousing';
$string['sector_wareho_generalwarehousing'] = 'General Warehousing';
$string['sector_wareho_coldstorage'] = 'Cold Storage';
$string['sector_wareho_distributioncentres'] = 'Distribution Centres';
$string['sector_wareho_crossdocking'] = 'Cross-Docking';
$string['sector_wareho_hazardousgoodsstorage'] = 'Hazardous Goods Storage';
$string['sector_wareho_automatedwarehousing'] = 'Automated Warehousing';
$string['sector_wareho_orderfulfillment'] = 'Order Fulfillment';
$string['sector_wareho_returnsprocessing'] = 'Returns Processing';
$string['sector_wareho_inventorycontrol'] = 'Inventory Control';
$string['industry_other'] = 'Other';
$string['sector_other_generalindustry'] = 'General Industry';
$string['sector_other_crossindustry'] = 'Cross-Industry';
$string['sector_other_emergingindustry'] = 'Emerging Industry';
