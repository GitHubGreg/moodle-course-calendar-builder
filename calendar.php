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

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$courseid = required_param('id', PARAM_INT);
$calendarid = required_param('calendarid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/coursecalendar:managecalendar', $context);

$calendar = local_coursecalendar_require_course_calendar($calendarid, $courseid);
$blueprint = local_coursecalendar_require_owned_blueprint((int)$calendar->blueprintid, (int)$USER->id);

$pageurl = new moodle_url('/local/coursecalendar/calendar.php', ['id' => $courseid, 'calendarid' => $calendarid]);
$backurl = new moodle_url('/local/coursecalendar/manage.php', ['id' => $courseid, 'blueprintctx' => $blueprint->id]);
$headerdayoptions = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$headermodeoptions = ['Lecture', 'Lab'];
$activetopics = local_coursecalendar_get_blueprint_topics((int)$blueprint->id, false);
$alltopics = local_coursecalendar_get_blueprint_topics((int)$blueprint->id, true);

if ($action !== '' && data_submitted()) {
    require_sesskey();

    switch ($action) {
        case 'addweekrow':
            local_coursecalendar_ensure_base_grid((int)$calendar->id, (int)$USER->id);
            local_coursecalendar_add_week_row((int)$calendar->id, (int)$USER->id);
            redirect($pageurl, get_string('weekrowadded', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'removelastweekrow':
            if (!local_coursecalendar_remove_last_week_row((int)$calendar->id)) {
                redirect($pageurl, get_string('errornoweekrowstoremove', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }
            redirect($pageurl, get_string('weekrowremoved', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'saveheader':
            $colnum = required_param('colnum', PARAM_INT);
            if ($colnum < 1 || $colnum > 3) {
                redirect($pageurl, get_string('errorinvalidheadercol', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }

            $contenthtml = trim(optional_param('contenthtml', '', PARAM_RAW));
            $headerday = trim(optional_param('headerday', '', PARAM_TEXT));
            $headermode = trim(optional_param('headermode', '', PARAM_TEXT));
            if (!in_array($headerday, $headerdayoptions, true) || !in_array($headermode, $headermodeoptions, true)) {
                redirect($pageurl, get_string('errorinvalidheaderconfig', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }

            local_coursecalendar_upsert_block(
                (int)$calendar->id,
                0,
                $colnum,
                'HEADER',
                $contenthtml,
                (int)$USER->id,
                $headerday,
                $headermode
            );
            redirect($pageurl, get_string('headercellsaved', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'savecell':
            $rownum = required_param('rownum', PARAM_INT);
            $colnum = required_param('colnum', PARAM_INT);
            if ($rownum < 0 || $colnum < 0 || $colnum > 4) {
                redirect($pageurl, get_string('errorinvalidcell', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }
            if ($rownum === 0) {
                redirect($pageurl, get_string('errorheaderreadonly', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }
            if ($colnum === 0) {
                redirect($pageurl, get_string('errorweeklabelreadonly', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }

            $blocktype = core_text::strtoupper(trim(optional_param('blocktype', 'TEXT', PARAM_ALPHA)));
            if (!in_array($blocktype, ['TEXT', 'TOPIC'], true)) {
                redirect($pageurl, get_string('errorinvalidblocktype', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }

            $topicid = null;
            $contenthtml = trim(optional_param('contenthtml', '', PARAM_RAW));
            $cellheading = trim(optional_param('cellheading', '', PARAM_RAW));
            $highlighted = optional_param('highlighted', 0, PARAM_BOOL) ? 1 : 0;
            $verticallycentred = optional_param('verticallycentred', 0, PARAM_BOOL) ? 1 : 0;
            if ($blocktype === 'TOPIC') {
                $topicid = optional_param('topicid', 0, PARAM_INT);
                if ($topicid <= 0) {
                    redirect($pageurl, get_string('errorinvalidtopicselection', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
                }
                $topic = local_coursecalendar_require_owned_topic($topicid, (int)$USER->id);
                if ((int)$topic->blueprintid !== (int)$blueprint->id) {
                    redirect($pageurl, get_string('errorinvalidtopicselection', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
                }
                $contenthtml = '';
            }
            if ($blocktype === 'TEXT' && $contenthtml === '' && $cellheading === '' && !$highlighted && !$verticallycentred) {
                $deleted = $DB->delete_records('local_coursecalendar_calendar_blocks', [
                    'calendarid' => (int)$calendar->id,
                    'rownum' => $rownum,
                    'colnum' => $colnum,
                ]);
                $message = $deleted ? get_string('cellcleared', 'local_coursecalendar') : get_string('cellalreadyempty', 'local_coursecalendar');
                redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
            }

            local_coursecalendar_upsert_block(
                (int)$calendar->id,
                $rownum,
                $colnum,
                $blocktype,
                $contenthtml,
                (int)$USER->id,
                null,
                null,
                $topicid,
                $cellheading,
                $highlighted,
                $verticallycentred
            );
            redirect($pageurl, get_string('cellsaved', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'updatetopicfromcell':
            $topicid = required_param('topicid', PARAM_INT);
            $topic = local_coursecalendar_require_owned_topic($topicid, (int)$USER->id);
            if ((int)$topic->blueprintid !== (int)$blueprint->id) {
                redirect($pageurl, get_string('errorinvalidtopicselection', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }

            $topic->title = trim(required_param('topictitle', PARAM_TEXT));
            $topic->type = local_coursecalendar_normalise_topic_type(required_param('topictype', PARAM_ALPHANUMEXT));
            $topic->contenthtml = trim(optional_param('topiccontenthtml', '', PARAM_RAW));
            if ($topic->title === '') {
                redirect($pageurl, get_string('errortopictitlerequired', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }

            $topic->timemodified = time();
            $topic->usermodified = (int)$USER->id;
            $DB->update_record('local_coursecalendar_blueprint_topics', $topic);
            redirect($pageurl, get_string('topicupdatedfrombuilder', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'savecourseinfo':
            $introhtml = trim(optional_param('introhtml', '', PARAM_RAW));
            $linkshtml = trim(optional_param('linkshtml', '', PARAM_RAW));
            local_coursecalendar_save_course_info($courseid, $introhtml, $linkshtml, (int)$USER->id);
            redirect($pageurl, get_string('courseinfosaved', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'deletecell':
            $rownum = required_param('rownum', PARAM_INT);
            $colnum = required_param('colnum', PARAM_INT);
            if ($rownum <= 0 || $colnum <= 0 || $colnum > 4) {
                redirect($pageurl, get_string('errorinvalidcell', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
            }

            $deleted = $DB->delete_records('local_coursecalendar_calendar_blocks', [
                'calendarid' => (int)$calendar->id,
                'rownum' => $rownum,
                'colnum' => $colnum,
            ]);
            $message = $deleted ? get_string('celldeleted', 'local_coursecalendar') : get_string('cellalreadyempty', 'local_coursecalendar');
            redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'autopopulate':
            $result = local_coursecalendar_auto_populate((int)$calendar->id, (int)$blueprint->id, (int)$USER->id);
            $msg = get_string('autopopulatedone', 'local_coursecalendar', $result);
            redirect($pageurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'fillproblemsessions':
            $filled = local_coursecalendar_fill_problem_sessions((int)$calendar->id, (int)$USER->id);
            redirect($pageurl, get_string('problemsessionsfilled', 'local_coursecalendar', $filled), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'deletenonheader':
            $deleted = local_coursecalendar_delete_non_header_blocks((int)$calendar->id);
            redirect($pageurl, get_string('nonheaderdeleted', 'local_coursecalendar', $deleted), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'deletenonheadernontext':
            $deleted = local_coursecalendar_delete_non_header_non_text_blocks((int)$calendar->id);
            redirect($pageurl, get_string('nonheadernontextdeleted', 'local_coursecalendar', $deleted), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

local_coursecalendar_ensure_base_grid((int)$calendar->id, (int)$USER->id);
$blocksmap = local_coursecalendar_get_blocks_map((int)$calendar->id);
$courseinfo = local_coursecalendar_get_course_info($courseid);
$maxrow = 0;
foreach (array_keys($blocksmap) as $rownum) {
    $maxrow = max($maxrow, (int)$rownum);
}

editors_head_setup();
$htmleditor = editors_get_preferred_editor(FORMAT_HTML);
$htmleditoroptions = [
    'context' => $context,
    'autosave' => false,
    'enable_filemanagement' => false,
];

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('builderpageheading', 'local_coursecalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/coursecalendar/styles.css'));

echo $OUTPUT->header();
echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-pageheader']);
echo $OUTPUT->heading(get_string('builderpageheading', 'local_coursecalendar'));
echo html_writer::tag('button', get_string('showtourbtn', 'local_coursecalendar'), [
    'type' => 'button',
    'id' => 'local-coursecalendar-showtour',
    'class' => 'btn btn-sm btn-outline-info local-coursecalendar-showtour',
    'data-tour-name' => 'local_coursecalendar_builder',
]);
echo html_writer::end_tag('div');
echo html_writer::link($backurl, get_string('backtomanage', 'local_coursecalendar'), ['class' => 'btn btn-secondary mb-3']);

echo html_writer::div(get_string('intro_calendar', 'local_coursecalendar'), 'local-coursecalendar-intro alert alert-info');

$managecontenturl = new moodle_url('/local/coursecalendar/manage.php', [
    'id' => $courseid,
    'blueprintctx' => (int)$blueprint->id,
]);
$previewurl = new moodle_url('/local/coursecalendar/view.php', [
    'id' => $courseid,
    'calendarid' => $calendarid,
]);
$rulesurl = new moodle_url('/local/coursecalendar/rules.php', [
    'id' => $courseid,
    'calendarid' => $calendarid,
]);

echo html_writer::tag('h4',
    get_string('section_builderactions', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_builderactions', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-section-title']
);
echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-page-actions', 'id' => 'local-coursecalendar-builder-actions']);
echo html_writer::link($managecontenturl, get_string('managecontentlink', 'local_coursecalendar'), ['class' => 'btn btn-sm btn-outline-primary mr-2']);
echo html_writer::link($rulesurl, get_string('manageruleslink', 'local_coursecalendar'), ['class' => 'btn btn-sm btn-outline-warning mr-2']);
echo html_writer::link($previewurl, get_string('openpreviewlink', 'local_coursecalendar'), ['class' => 'btn btn-sm btn-outline-info mr-2', 'target' => '_blank']);
$embedurl = new moodle_url('/local/coursecalendar/embed.php', [
    'id' => $courseid,
    'calendarid' => $calendarid,
]);
echo html_writer::tag('button', get_string('copyiframesubmit', 'local_coursecalendar'), [
    'type' => 'button',
    'id' => 'local-coursecalendar-copy-iframe',
    'class' => 'btn btn-sm btn-outline-secondary',
    'data-preview-url' => $embedurl->out(false),
]);
$coverageurl = new moodle_url('/local/coursecalendar/coverage.php', [
    'id' => $courseid,
    'calendarid' => $calendarid,
]);
echo html_writer::link($coverageurl, get_string('coveragechecklink', 'local_coursecalendar'), ['class' => 'btn btn-sm btn-outline-info ml-2']);
echo html_writer::end_tag('div');

echo html_writer::tag('h4',
    get_string('section_automation', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_automation', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-section-title']
);
echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-page-actions', 'id' => 'local-coursecalendar-automation-actions']);
$automationactions = [
    'autopopulate' => ['label' => 'autopopulatebtn', 'class' => 'btn-success', 'confirm' => 'autopopulateconfirm'],
    'fillproblemsessions' => ['label' => 'fillproblemsessionsbtn', 'class' => 'btn-outline-success', 'confirm' => 'fillproblemsessionsconfirm'],
    'deletenonheader' => ['label' => 'deletenonheaderbtn', 'class' => 'btn-outline-danger', 'confirm' => 'deletenonheaderconfirm'],
    'deletenonheadernontext' => ['label' => 'deletenonheadernontextbtn', 'class' => 'btn-outline-danger', 'confirm' => 'deletenonheadernontextconfirm'],
];
foreach ($automationactions as $act => $cfg) {
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-inline-form',
        'onsubmit' => 'return confirm(' . json_encode(get_string($cfg['confirm'], 'local_coursecalendar')) . ')']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $act]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-sm ' . $cfg['class'],
        'value' => get_string($cfg['label'], 'local_coursecalendar')]);
    echo html_writer::end_tag('form');
}
echo html_writer::end_tag('div');

echo html_writer::tag('h4',
    get_string('section_buildertoolbar', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_buildertoolbar', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-section-title']
);
echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-toolbar', 'id' => 'local-coursecalendar-toolbar']);
echo html_writer::tag('span', get_string('unsavedchangesbadge', 'local_coursecalendar'), [
    'id' => 'local-coursecalendar-unsaved-badge',
    'class' => 'badge badge-warning mr-2',
    'style' => 'display:none',
]);
echo html_writer::tag('button', get_string('saveallsubmit', 'local_coursecalendar'), [
    'type' => 'button',
    'id' => 'local-coursecalendar-saveall',
    'class' => 'btn btn-sm btn-success mr-2',
]);
echo html_writer::tag('button', get_string('undobtn', 'local_coursecalendar'), [
    'type' => 'button',
    'id' => 'local-coursecalendar-undo',
    'class' => 'btn btn-sm btn-outline-secondary mr-1',
    'disabled' => 'disabled',
]);
echo html_writer::tag('button', get_string('redobtn', 'local_coursecalendar'), [
    'type' => 'button',
    'id' => 'local-coursecalendar-redo',
    'class' => 'btn btn-sm btn-outline-secondary',
    'disabled' => 'disabled',
]);
echo html_writer::end_tag('div');

$calendarlabel = s($calendar->semester) . ' ' . (int)$calendar->year;
if (!empty($calendar->title)) {
    $calendarlabel .= ' - ' . format_string($calendar->title);
}
echo html_writer::div(get_string('buildercontextlabel', 'local_coursecalendar', $calendarlabel), 'local-coursecalendar-shell');

echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-inline-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addweekrow']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('addweekrowsubmit', 'local_coursecalendar')]);
echo html_writer::end_tag('form');
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-inline-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'removelastweekrow']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-outline-secondary', 'value' => get_string('removelastweekrowsubmit', 'local_coursecalendar')]);
echo html_writer::end_tag('form');

echo html_writer::tag('h4',
    get_string('section_introtexts', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_introtexts', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-section-title', 'id' => 'local-coursecalendar-section-introtexts']
);
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-card local-coursecalendar-introtexts-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savecourseinfo']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::start_div('local-coursecalendar-introtexts-grid');
echo html_writer::start_div('local-coursecalendar-introtext-panel');
echo html_writer::tag('label', get_string('courseinfointroleftlabel', 'local_coursecalendar'), [
    'for' => 'local-coursecalendar-intro-left',
    'class' => 'font-weight-bold',
]);
echo html_writer::tag('textarea', $courseinfo ? s((string)$courseinfo->introhtml) : '', [
    'id' => 'local-coursecalendar-intro-left',
    'name' => 'introhtml',
    'class' => 'form-control',
    'rows' => 12,
]);
$htmleditor->use_editor('local-coursecalendar-intro-left', $htmleditoroptions);
echo html_writer::end_div();
echo html_writer::start_div('local-coursecalendar-introtext-panel');
echo html_writer::tag('label', get_string('courseinfointrorightlabel', 'local_coursecalendar'), [
    'for' => 'local-coursecalendar-intro-right',
    'class' => 'font-weight-bold',
]);
echo html_writer::tag('textarea', $courseinfo ? s((string)$courseinfo->linkshtml) : '', [
    'id' => 'local-coursecalendar-intro-right',
    'name' => 'linkshtml',
    'class' => 'form-control',
    'rows' => 12,
]);
$htmleditor->use_editor('local-coursecalendar-intro-right', $htmleditoroptions);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => get_string('saveintrotextssubmit', 'local_coursecalendar'),
]);
echo html_writer::end_tag('form');

echo html_writer::tag('h4',
    get_string('section_buildergrid', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_buildergrid', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-section-title']
);
echo html_writer::start_tag('table', ['class' => 'table table-bordered local-coursecalendar-grid', 'id' => 'local-coursecalendar-grid']);
for ($row = 0; $row <= $maxrow; $row++) {
    echo html_writer::start_tag('tr');
    for ($col = 0; $col <= 4; $col++) {
        $cell = $blocksmap[$row][$col] ?? null;
        $content = $cell ? (string)$cell->contenthtml : '';
        $tag = ($row === 0) ? 'th' : 'td';
        if ($row === 0 && ($col === 0 || $col === 4)) {
            echo html_writer::start_tag($tag, [
                'class' => 'local-coursecalendar-grid-cell',
                'data-cc-row' => $row,
                'data-cc-col' => $col,
                'data-cc-blocktype' => 'HEADER',
                'data-cc-content' => $content,
                'data-cc-editable' => '0',
            ]);
            echo html_writer::tag('div', format_text($content, FORMAT_HTML), ['class' => 'local-coursecalendar-readonly-cell']);
            echo html_writer::end_tag($tag);
            continue;
        }
        if ($row === 0 && $col >= 1 && $col <= 3) {
            $headerday = $cell && !empty($cell->headerday) ? (string)$cell->headerday : $headerdayoptions[$col - 1];
            $headermode = $cell && !empty($cell->headermode) ? (string)$cell->headermode : 'Lecture';
            echo html_writer::start_tag($tag, [
                'class' => 'local-coursecalendar-grid-cell',
                'data-cc-row' => 0,
                'data-cc-col' => $col,
                'data-cc-blocktype' => 'HEADER',
                'data-cc-content' => $content,
                'data-cc-headerday' => $headerday,
                'data-cc-headermode' => $headermode,
                'data-cc-editable' => '0',
            ]);
            echo html_writer::start_tag('form', ['method' => 'post']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveheader']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'colnum', 'value' => $col]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $headercontentid = 'local-coursecalendar-header-content-' . $col;
            echo html_writer::tag('textarea', s($content), [
                'id' => $headercontentid,
                'name' => 'contenthtml',
                'rows' => 4,
                'class' => 'form-control mb-2',
            ]);
            $htmleditor->use_editor($headercontentid, $htmleditoroptions);
            echo html_writer::start_tag('select', ['name' => 'headerday', 'class' => 'custom-select mb-2']);
            foreach ($headerdayoptions as $option) {
                $attrs = ['value' => $option];
                if ($option === $headerday) {
                    $attrs['selected'] = 'selected';
                }
                echo html_writer::tag('option', $option, $attrs);
            }
            echo html_writer::end_tag('select');
            echo html_writer::start_tag('select', ['name' => 'headermode', 'class' => 'custom-select mb-2']);
            foreach ($headermodeoptions as $option) {
                $attrs = ['value' => $option];
                if ($option === $headermode) {
                    $attrs['selected'] = 'selected';
                }
                echo html_writer::tag('option', $option, $attrs);
            }
            echo html_writer::end_tag('select');
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => 'btn btn-sm btn-outline-secondary',
                'value' => get_string('saveheadersubmit', 'local_coursecalendar'),
            ]);
            echo html_writer::end_tag('form');
            echo html_writer::end_tag($tag);
            continue;
        }
        if ($col === 0) {
            echo html_writer::start_tag($tag, [
                'class' => 'local-coursecalendar-grid-cell',
                'data-cc-row' => $row,
                'data-cc-col' => 0,
                'data-cc-blocktype' => 'TEXT',
                'data-cc-content' => $content,
                'data-cc-editable' => '0',
            ]);
            echo html_writer::tag('div', format_text($content, FORMAT_HTML), ['class' => 'local-coursecalendar-readonly-cell']);
            echo html_writer::end_tag($tag);
            continue;
        }
        $blocktype = $cell ? (string)$cell->blocktype : 'TEXT';
        if (!in_array($blocktype, ['TEXT', 'TOPIC'], true)) {
            $blocktype = 'TEXT';
        }
        $cellheading = $cell ? (string)$cell->cellheading : '';
        $highlighted = $cell ? ((int)$cell->highlighted === 1) : false;
        $verticallycentred = $cell ? ((int)$cell->verticallycentred === 1) : false;
        $selectedtopicid = ($cell && !empty($cell->topicid)) ? (int)$cell->topicid : 0;
        $selectedtopic = ($selectedtopicid > 0 && isset($alltopics[$selectedtopicid])) ? $alltopics[$selectedtopicid] : null;

        $cellclasses = ['local-coursecalendar-grid-cell'];
        if ($highlighted) {
            $cellclasses[] = 'local-coursecalendar-highlighted';
        }
        if ($verticallycentred) {
            $cellclasses[] = 'local-coursecalendar-vcentred';
        }
        echo html_writer::start_tag($tag, [
            'class' => implode(' ', $cellclasses),
            'data-cc-row' => $row,
            'data-cc-col' => $col,
            'data-cc-blocktype' => $blocktype,
            'data-cc-content' => $content,
            'data-cc-topicid' => $selectedtopicid,
            'data-cc-cellheading' => $cellheading,
            'data-cc-highlighted' => $highlighted ? 1 : 0,
            'data-cc-vcentred' => $verticallycentred ? 1 : 0,
            'data-cc-editable' => '1',
        ]);

        if ($cellheading !== '') {
            echo html_writer::tag('div', format_text($cellheading, FORMAT_HTML), ['class' => 'local-coursecalendar-cellheading']);
        }
        if ($blocktype === 'TOPIC' && $selectedtopic) {
            $typebadge = html_writer::tag('span', s($selectedtopic->type), ['class' => 'local-coursecalendar-type-badge local-coursecalendar-type-' . strtolower($selectedtopic->type)]);
            $inactivetag = '';
            if ((int)$selectedtopic->isactive === 0) {
                $inactivetag = ' ' . html_writer::tag('span', get_string('topicinactive', 'local_coursecalendar'), ['class' => 'badge badge-warning']);
            }
            echo html_writer::tag('div', $typebadge . ' ' . format_string($selectedtopic->title) . $inactivetag, ['class' => 'local-coursecalendar-topic-display']);
            if (!empty($selectedtopic->contenthtml)) {
                echo html_writer::tag('div', format_text($selectedtopic->contenthtml, FORMAT_HTML), ['class' => 'local-coursecalendar-topic-preview']);
            }
        } else if ($blocktype === 'TEXT' && $content !== '') {
            echo html_writer::tag('div', format_text($content, FORMAT_HTML), ['class' => 'local-coursecalendar-text-preview']);
        }

        echo html_writer::start_tag('details', ['class' => 'local-coursecalendar-cell-editor']);
        echo html_writer::tag('summary', get_string('editcellsummary', 'local_coursecalendar'), ['class' => 'btn btn-sm btn-link']);
        echo html_writer::start_tag('form', ['method' => 'post']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rownum', 'value' => $row]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'colnum', 'value' => $col]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::start_tag('select', ['name' => 'blocktype', 'class' => 'custom-select mb-2']);
        $textattrs = ['value' => 'TEXT'];
        if ($blocktype === 'TEXT') {
            $textattrs['selected'] = 'selected';
        }
        echo html_writer::tag('option', get_string('blocktypetext', 'local_coursecalendar'), $textattrs);
        $topicattrs = ['value' => 'TOPIC'];
        if ($blocktype === 'TOPIC') {
            $topicattrs['selected'] = 'selected';
        }
        echo html_writer::tag('option', get_string('blocktypetopic', 'local_coursecalendar'), $topicattrs);
        echo html_writer::end_tag('select');
        echo html_writer::start_tag('select', ['name' => 'topicid', 'class' => 'custom-select mb-2']);
        echo html_writer::tag('option', get_string('selecttopicplaceholder', 'local_coursecalendar'), ['value' => '']);
        foreach ($activetopics as $topic) {
            $optiontext = s($topic->title) . ' (' . s($topic->type) . ')';
            $attrs = ['value' => (int)$topic->id];
            if ((int)$topic->id === $selectedtopicid) {
                $attrs['selected'] = 'selected';
            }
            echo html_writer::tag('option', $optiontext, $attrs);
        }
        if ($selectedtopic && (int)$selectedtopic->isactive === 0) {
            echo html_writer::tag('option', s($selectedtopic->title) . ' (' . s($selectedtopic->type) . ', ' .
                get_string('topicinactive', 'local_coursecalendar') . ')', [
                'value' => (int)$selectedtopic->id,
                'selected' => 'selected',
            ]);
        }
        echo html_writer::end_tag('select');
        if ($blocktype === 'TOPIC' && $selectedtopic) {
            echo html_writer::tag('div', get_string('celltopiccontenthandledseparately', 'local_coursecalendar'), [
                'class' => 'local-coursecalendar-muted-note mb-2',
            ]);
        } else {
            $cellcontentid = 'local-coursecalendar-cell-content-' . $row . '-' . $col;
            echo html_writer::tag('textarea', s($content), [
                'id' => $cellcontentid,
                'name' => 'contenthtml',
                'rows' => 6,
                'class' => 'form-control mb-2',
                'placeholder' => get_string('contenthtmlplaceholder', 'local_coursecalendar'),
            ]);
            $htmleditor->use_editor($cellcontentid, $htmleditoroptions);
        }
        echo html_writer::tag('textarea', s($cellheading), [
            'name' => 'cellheading',
            'rows' => 2,
            'class' => 'form-control mb-2',
            'placeholder' => get_string('cellheadingplaceholder', 'local_coursecalendar'),
        ]);
        $highlightid = 'local-coursecalendar-highlighted-' . $row . '-' . $col;
        $vcenterid = 'local-coursecalendar-vcenter-' . $row . '-' . $col;
        $highlightattrs = [
            'type' => 'checkbox',
            'name' => 'highlighted',
            'value' => '1',
            'id' => $highlightid,
            'class' => 'mr-1',
        ];
        if ($highlighted) {
            $highlightattrs['checked'] = 'checked';
        }
        $vcenterattrs = [
            'type' => 'checkbox',
            'name' => 'verticallycentred',
            'value' => '1',
            'id' => $vcenterid,
            'class' => 'mr-1',
        ];
        if ($verticallycentred) {
            $vcenterattrs['checked'] = 'checked';
        }
        echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-checkbox-row']);
        echo html_writer::empty_tag('input', $highlightattrs);
        echo html_writer::tag('label', get_string('cellhighlightedlabel', 'local_coursecalendar'), [
            'for' => $highlightid,
            'class' => 'mr-3',
        ]);
        echo html_writer::empty_tag('input', $vcenterattrs);
        echo html_writer::tag('label', get_string('cellverticalcentredlabel', 'local_coursecalendar'), [
            'for' => $vcenterid,
        ]);
        echo html_writer::end_tag('div');
        echo html_writer::tag('button', get_string('savecellsubmit', 'local_coursecalendar'), [
            'type' => 'submit',
            'name' => 'action',
            'value' => 'savecell',
            'class' => 'btn btn-sm btn-outline-secondary mr-2',
        ]);
        echo html_writer::tag('button', get_string('deletecellsubmit', 'local_coursecalendar'), [
            'type' => 'submit',
            'name' => 'action',
            'value' => 'deletecell',
            'class' => 'btn btn-sm btn-outline-danger',
        ]);
        echo html_writer::end_tag('form');

        if ($blocktype === 'TOPIC' && $selectedtopic) {
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'class' => 'local-coursecalendar-shared-topic-editor',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicid', 'value' => (int)$selectedtopic->id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

            echo html_writer::tag('h6', get_string('sharedtopiceditorheading', 'local_coursecalendar'));
            echo html_writer::tag('div', get_string('sharedtopiceditwarning', 'local_coursecalendar'), [
                'class' => 'alert alert-warning local-coursecalendar-shared-topic-warning',
            ]);

            $topictitleid = 'local-coursecalendar-shared-topic-title-' . $row . '-' . $col;
            echo html_writer::start_div('mb-2');
            echo html_writer::tag('label', get_string('topictitlelabel', 'local_coursecalendar'), ['for' => $topictitleid]);
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'id' => $topictitleid,
                'name' => 'topictitle',
                'class' => 'form-control',
                'required' => 'required',
                'value' => s((string)$selectedtopic->title),
            ]);
            echo html_writer::end_div();

            $topictypeid = 'local-coursecalendar-shared-topic-type-' . $row . '-' . $col;
            echo html_writer::start_div('mb-2');
            echo html_writer::tag('label', get_string('topictypelabel', 'local_coursecalendar'), ['for' => $topictypeid]);
            echo html_writer::start_tag('select', ['id' => $topictypeid, 'name' => 'topictype', 'class' => 'custom-select']);
            foreach (local_coursecalendar_get_topic_types() as $topictype) {
                $attrs = ['value' => $topictype];
                if ($selectedtopic->type === $topictype) {
                    $attrs['selected'] = 'selected';
                }
                echo html_writer::tag('option', $topictype, $attrs);
            }
            echo html_writer::end_tag('select');
            echo html_writer::end_div();

            $topiccontentid = 'local-coursecalendar-shared-topic-content-' . $row . '-' . $col;
            echo html_writer::start_div('mb-2');
            echo html_writer::tag('label', get_string('topiccontentlabel', 'local_coursecalendar'), ['for' => $topiccontentid]);
            echo html_writer::tag('textarea', s((string)$selectedtopic->contenthtml), [
                'id' => $topiccontentid,
                'name' => 'topiccontenthtml',
                'rows' => 8,
                'class' => 'form-control',
            ]);
            $htmleditor->use_editor($topiccontentid, $htmleditoroptions);
            echo html_writer::end_div();

            echo html_writer::tag('button', get_string('savesharedtopicsubmit', 'local_coursecalendar'), [
                'type' => 'submit',
                'name' => 'action',
                'value' => 'updatetopicfromcell',
                'class' => 'btn btn-sm btn-warning',
            ]);
            echo html_writer::end_tag('form');
        }
        echo html_writer::end_tag('details');
        echo html_writer::end_tag($tag);
    }
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('table');

$PAGE->requires->js_call_amd('local_coursecalendar/builder', 'init', [$courseid, $calendarid]);
$tourid = local_coursecalendar_get_tour_id_by_name('local_coursecalendar_builder');
$PAGE->requires->js_call_amd('local_coursecalendar/showtour', 'init', [
    $tourid,
    '#local-coursecalendar-showtour',
]);

echo $OUTPUT->footer();
