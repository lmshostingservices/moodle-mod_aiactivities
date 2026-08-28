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
 * Administration settings for mod_aiactivities.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $centralconfiginstalled = file_exists($CFG->dirroot . '/local/aiconfig/version.php');

    if ($centralconfiginstalled) {
        $centralconfigurl = new moodle_url('/admin/settings.php', ['section' => 'local_aiconfig']);
        $notice = get_string('centralconfig_installed', 'mod_aiactivities', $centralconfigurl->out());
        $noticetype = 'success';
        $fallbacknote = ' ' . get_string('centralconfig_fallback', 'mod_aiactivities');
    } else {
        $notice = get_string('centralconfig_recommended', 'mod_aiactivities');
        $noticetype = 'warning';
        $fallbacknote = '';
    }

    $settings->add(new admin_setting_heading(
        'mod_aiactivities/centralconfig_notice',
        '',
        html_writer::div($notice, 'alert alert-' . $noticetype)
    ));

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
        get_string('siteid_desc', 'mod_aiactivities') . $fallbacknote,
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_aiactivities/apikey',
        get_string('apikey', 'mod_aiactivities'),
        get_string('apikey_desc', 'mod_aiactivities') . $fallbacknote,
        ''
    ));

    $settings->add(new admin_setting_heading(
        'mod_aiactivities/credits_heading',
        get_string('credits_heading', 'mod_aiactivities'),
        get_string('credits_info', 'mod_aiactivities')
    ));
}
