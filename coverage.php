<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$courseid = required_param('id', PARAM_INT);
$calendarid = required_param('calendarid', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/coursecalendar:managecalendar', $context);

$calendar = local_coursecalendar_require_course_calendar($calendarid, $courseid);
$blueprint = local_coursecalendar_require_owned_blueprint((int)$calendar->blueprintid, (int)$USER->id);

$pageurl = new moodle_url('/local/coursecalendar/coverage.php', ['id' => $courseid, 'calendarid' => $calendarid]);
$builderurl = new moodle_url('/local/coursecalendar/calendar.php', ['id' => $courseid, 'calendarid' => $calendarid]);

$result = local_coursecalendar_coverage_check((int)$calendar->id, (int)$blueprint->id);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coveragepagetitle', 'local_coursecalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/coursecalendar/styles.css'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coveragepagetitle', 'local_coursecalendar'));
echo html_writer::link($builderurl, get_string('backtobuilder', 'local_coursecalendar'), ['class' => 'btn btn-secondary mb-3']);

echo html_writer::div(get_string('intro_coverage', 'local_coursecalendar'), 'local-coursecalendar-intro alert alert-info');

echo html_writer::tag('h4',
    get_string('coveragefoundheading', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('coveragefoundheading', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-coverage-found mt-3']
);
if (empty($result['found'])) {
    echo html_writer::div(get_string('coveragenofound', 'local_coursecalendar'), 'alert alert-info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-sm table-bordered']);
    echo '<tr><th>Topic</th><th>Type</th><th>Row</th><th>Col</th><th>Day</th><th>Mode</th></tr>';
    foreach ($result['found'] as $f) {
        $typebadge = html_writer::tag('span', s($f['type']), [
            'class' => 'local-coursecalendar-type-badge local-coursecalendar-type-' . strtolower($f['type']),
        ]);
        echo '<tr>';
        echo html_writer::tag('td', s($f['title']));
        echo html_writer::tag('td', $typebadge);
        echo html_writer::tag('td', $f['row']);
        echo html_writer::tag('td', $f['col']);
        echo html_writer::tag('td', s($f['headerday']));
        echo html_writer::tag('td', s($f['headermode']));
        echo '</tr>';
    }
    echo html_writer::end_tag('table');
}

echo html_writer::tag('h4',
    get_string('coveragemissingheading', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('coveragemissingheading', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-coverage-missing mt-3']
);
if (empty($result['missing'])) {
    echo html_writer::div(get_string('coveragenomissing', 'local_coursecalendar'), 'alert alert-success');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-sm table-bordered']);
    echo '<tr><th>Topic</th><th>Type</th></tr>';
    foreach ($result['missing'] as $m) {
        $typebadge = html_writer::tag('span', s($m['type']), [
            'class' => 'local-coursecalendar-type-badge local-coursecalendar-type-' . strtolower($m['type']),
        ]);
        echo '<tr>';
        echo html_writer::tag('td', s($m['title']));
        echo html_writer::tag('td', $typebadge);
        echo '</tr>';
    }
    echo html_writer::end_tag('table');
}

echo html_writer::tag('h4',
    get_string('coverageemptyheading', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('coverageemptyheading', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-coverage-empty mt-3']
);
if (empty($result['empty'])) {
    echo html_writer::div(get_string('coveragenoempty', 'local_coursecalendar'), 'alert alert-success');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-sm table-bordered']);
    echo '<tr><th>Row</th><th>Col</th><th>Day</th><th>Mode</th></tr>';
    foreach ($result['empty'] as $e) {
        echo '<tr>';
        echo html_writer::tag('td', $e['row']);
        echo html_writer::tag('td', $e['col']);
        echo html_writer::tag('td', s($e['headerday']));
        echo html_writer::tag('td', s($e['headermode']));
        echo '</tr>';
    }
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();
