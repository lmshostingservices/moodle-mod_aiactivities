<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_aiactivities_upgrade($oldversion) {
    if ($oldversion < 2026072300) {
        upgrade_mod_savepoint(true, 2026072300, 'aiactivities');
    }
    return true;
}
