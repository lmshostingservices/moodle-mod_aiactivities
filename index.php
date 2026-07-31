<?php
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$PAGE->set_url('/mod/aiactivities/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

$instances = get_all_instances_in_course('aiactivities', $course);

if (empty($instances)) {
    notice(get_string('noinstances', 'mod_aiactivities'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [get_string('name'), get_string('description')];

foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/aiactivities/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name)
    );
    $table->data[] = [$link, format_module_intro('aiactivities', $instance, $instance->coursemodule)];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
