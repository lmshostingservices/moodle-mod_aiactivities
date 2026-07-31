<?php
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
$string['credits_heading'] = 'Credit Usage';
$string['credits_info'] = 'AI Learning Activities uses 2 credits per activity generated. For example, generating 5 activities costs 10 credits.';
$string['credits_label'] = 'credits';

$string['not_configured'] = 'AI Learning Activities is not configured. Please ask your administrator to set up the Site ID and API Key in the plugin settings or install AI Grader Central Config.';

$string['page_heading'] = 'Generate Learning Activities';
$string['page_intro'] = 'Paste your learning content below and AI will create interactive revision activities for your students.';

$string['content_label'] = 'Learning Content';
$string['content_placeholder'] = 'Paste your learning content here... This could be lecture notes, textbook content, training materials, procedures, safety guidelines, etc.';
$string['content_help'] = 'Paste the content you want students to revise. The AI will analyse this and create interactive activities based on key concepts, procedures, and facts.';

$string['language_label'] = 'Language';
$string['language_help'] = 'Select the language for the generated activities.';

$string['activitycount_label'] = 'Number of Activities';
$string['activitycount_help'] = 'Choose how many learning activities to generate. Each activity costs 2 credits.';

$string['generate_btn'] = 'Generate Activities';
$string['generating'] = 'Generating activities...';
$string['generate_success'] = 'Activities generated successfully!';
$string['regenerate_btn'] = 'Regenerate Activities';

$string['completionallcorrect'] = 'Student must complete all activities correctly';
$string['completionallcorrect_help'] = 'When enabled, students must successfully complete every activity to achieve completion. Students have unlimited attempts.';
$string['completiondetail:completionallcorrect'] = 'Complete all activities correctly';

$string['start_btn'] = 'Start Activities';
$string['next_btn'] = 'Next';
$string['next_card_btn'] = 'Next Card';
$string['check_btn'] = 'Check Answer';
$string['tryagain_btn'] = 'Try Again';
$string['complete_title'] = 'All Activities Complete!';
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
$string['fillinblank_wordbank'] = 'Word Bank';

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

