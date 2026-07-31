<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_aiactivities_save_progress' => [
        'classname' => 'mod_aiactivities\external\save_progress',
        'methodname' => 'execute',
        'description' => 'Save student progress for AI Learning Activities',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
