<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $centralconfigurl = new moodle_url('/admin/settings.php', ['section' => 'local_aiconfig']);
    $centralconfiginstalled = file_exists($CFG->dirroot . '/local/aiconfig/version.php');

    if ($centralconfiginstalled) {
        $settings->add(new admin_setting_heading(
            'mod_aiactivities/centralconfig_notice',
            '',
            '<div style="padding: 12px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; margin-bottom: 16px;">' .
            '<strong style="color: #047857;">AI Grader Central Config is installed.</strong><br>' .
            'Site ID and API Key are managed centrally. ' .
            '<a href="' . $centralconfigurl->out() . '">Configure Central Settings</a>' .
            '</div>'
        ));
    } else {
        $settings->add(new admin_setting_heading(
            'mod_aiactivities/centralconfig_notice',
            '',
            '<div style="padding: 12px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; margin-bottom: 16px;">' .
            '<strong style="color: #b45309;">Recommended: Install AI Grader Central Config</strong><br>' .
            'Configure Site ID and API Key once for all AI Grader plugins.' .
            '</div>'
        ));
    }

    $settings->add(new admin_setting_configtext(
        'mod_aiactivities/apiurl',
        get_string('apiurl', 'mod_aiactivities'),
        get_string('apiurl_desc', 'mod_aiactivities'),
        'https://lms-labs.com',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aiactivities/siteid',
        get_string('siteid', 'mod_aiactivities'),
        get_string('siteid_desc', 'mod_aiactivities') . ($centralconfiginstalled ? ' (Fallback - Central Config takes priority)' : ''),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_aiactivities/apikey',
        get_string('apikey', 'mod_aiactivities'),
        get_string('apikey_desc', 'mod_aiactivities') . ($centralconfiginstalled ? ' (Fallback - Central Config takes priority)' : ''),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'mod_aiactivities/credits_heading',
        get_string('credits_heading', 'mod_aiactivities'),
        get_string('credits_info', 'mod_aiactivities')
    ));
}
