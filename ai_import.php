<?php
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

$pageurl = new moodle_url('/local/coursecalendar/ai_import.php', ['id' => $courseid, 'calendarid' => $calendarid]);
$rulesurl = new moodle_url('/local/coursecalendar/rules.php', ['id' => $courseid, 'calendarid' => $calendarid]);
$apikey = get_config('local_coursecalendar', 'gemini_api_key');

if ($action === 'extract' && data_submitted()) {
    require_sesskey();
    if (empty($apikey)) {
        redirect($pageurl, get_string('aiimportnoapikey', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $source = optional_param('source', 'text', PARAM_ALPHA);
    $extractedjson = null;

    if ($source === 'pdf') {
        if (empty($_FILES['inputpdf']) || ($_FILES['inputpdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            redirect($pageurl, get_string('aiimportnopdf', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $tmppath = $_FILES['inputpdf']['tmp_name'];
        $maxbytes = 20 * 1024 * 1024;
        if (($_FILES['inputpdf']['size'] ?? 0) > $maxbytes) {
            redirect($pageurl, get_string('aiimportpdftoolarge', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $pdfdata = @file_get_contents($tmppath);
        if ($pdfdata === false || $pdfdata === '' || strncmp($pdfdata, '%PDF-', 5) !== 0) {
            redirect($pageurl, get_string('aiimportpdfinvalid', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $extractedjson = local_coursecalendar_gemini_extract_dates_from_pdf($apikey, $pdfdata);
    } else {
        $inputtext = required_param('inputtext', PARAM_RAW);
        if (trim($inputtext) === '') {
            redirect($pageurl, get_string('aiimportnotext', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $extractedjson = local_coursecalendar_gemini_extract_dates($apikey, $inputtext);
    }

    if ($extractedjson === null) {
        redirect($pageurl, get_string('aiimporterror', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($action === 'applyrules' && data_submitted()) {
    require_sesskey();
    $rulesjson = required_param('rulesjson', PARAM_RAW);
    $rules = json_decode($rulesjson, true);
    $created = 0;
    if (is_array($rules)) {
        foreach ($rules as $rule) {
            $ruletype = strtoupper(trim($rule['type'] ?? 'OTHER'));
            $validtypes = local_coursecalendar_get_rule_types();
            if (!in_array($ruletype, $validtypes, true)) {
                $ruletype = 'OTHER';
            }
            $ruledate = strtotime($rule['date'] ?? '');
            if (!$ruledate) {
                continue;
            }
            $label = trim($rule['label'] ?? '');
            $description = trim($rule['description'] ?? '');
            $fromday = isset($rule['fromday']) ? strtoupper(trim($rule['fromday'])) : null;
            $today = isset($rule['today']) ? strtoupper(trim($rule['today'])) : null;
            try {
                local_coursecalendar_create_rule(
                    (int)$calendar->id,
                    $ruletype,
                    $ruledate,
                    $label,
                    $description,
                    $fromday ?: null,
                    $today ?: null,
                    (int)$USER->id
                );
                $created++;
            } catch (Exception $e) {
                continue;
            }
        }
    }
    redirect($rulesurl, get_string('aiimportapplied', 'local_coursecalendar', $created), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('aiimportpagetitle', 'local_coursecalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/coursecalendar/styles.css'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('aiimportpagetitle', 'local_coursecalendar'));
echo html_writer::link($rulesurl, get_string('manageruleslink', 'local_coursecalendar'), ['class' => 'btn btn-secondary mb-3']);

echo html_writer::div(get_string('intro_aiimport', 'local_coursecalendar'), 'local-coursecalendar-intro alert alert-info');

if (empty($apikey)) {
    echo $OUTPUT->notification(get_string('aiimportnoapikey', 'local_coursecalendar'), 'notifywarning');
}

if (isset($extractedjson)) {
    echo html_writer::tag('h4',
        get_string('aiimportresultlabel', 'local_coursecalendar')
        . ' ' . $OUTPUT->help_icon('aiimportresultlabel', 'local_coursecalendar'),
        ['class' => 'mt-3']
    );
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-card']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'applyrules']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('textarea', s($extractedjson), [
        'name' => 'rulesjson',
        'class' => 'form-control mb-2',
        'rows' => 12,
    ]);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('aiimportapplysubmit', 'local_coursecalendar')]);
    echo html_writer::end_tag('form');
} else {
    echo html_writer::tag('h4',
        get_string('aiimportuploadlabel', 'local_coursecalendar')
        . ' ' . $OUTPUT->help_icon('aiimportuploadlabel', 'local_coursecalendar'),
        ['class' => 'mt-3']
    );

    $submitdisabled = empty($apikey);

    echo html_writer::start_tag('ul', [
        'class' => 'nav nav-tabs mb-0',
        'role' => 'tablist',
        'id' => 'aiimport-tabs',
    ]);
    echo html_writer::tag('li',
        html_writer::tag('a', get_string('aiimporttabtext', 'local_coursecalendar'), [
            'class' => 'nav-link active',
            'id' => 'aiimport-tab-text',
            'data-toggle' => 'tab',
            'data-bs-toggle' => 'tab',
            'href' => '#aiimport-pane-text',
            'role' => 'tab',
            'aria-controls' => 'aiimport-pane-text',
            'aria-selected' => 'true',
        ]),
        ['class' => 'nav-item', 'role' => 'presentation']
    );
    echo html_writer::tag('li',
        html_writer::tag('a', get_string('aiimporttabpdf', 'local_coursecalendar'), [
            'class' => 'nav-link',
            'id' => 'aiimport-tab-pdf',
            'data-toggle' => 'tab',
            'data-bs-toggle' => 'tab',
            'href' => '#aiimport-pane-pdf',
            'role' => 'tab',
            'aria-controls' => 'aiimport-pane-pdf',
            'aria-selected' => 'false',
        ]),
        ['class' => 'nav-item', 'role' => 'presentation']
    );
    echo html_writer::end_tag('ul');

    echo html_writer::start_tag('div', [
        'class' => 'tab-content local-coursecalendar-card local-coursecalendar-aiimport-tabs',
    ]);

    // Tab 1: paste text.
    echo html_writer::start_tag('div', [
        'class' => 'tab-pane fade show active',
        'id' => 'aiimport-pane-text',
        'role' => 'tabpanel',
        'aria-labelledby' => 'aiimport-tab-text',
    ]);
    echo html_writer::start_tag('form', ['method' => 'post']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'extract']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source', 'value' => 'text']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('textarea', '', [
        'name' => 'inputtext',
        'class' => 'form-control mb-2',
        'rows' => 10,
        'placeholder' => get_string('aiimporttextplaceholder', 'local_coursecalendar'),
    ]);
    $textsubmitattrs = [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('aiimportsubmit', 'local_coursecalendar'),
    ];
    if ($submitdisabled) {
        $textsubmitattrs['disabled'] = 'disabled';
    }
    echo html_writer::empty_tag('input', $textsubmitattrs);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div');

    // Tab 2: upload PDF.
    echo html_writer::start_tag('div', [
        'class' => 'tab-pane fade',
        'id' => 'aiimport-pane-pdf',
        'role' => 'tabpanel',
        'aria-labelledby' => 'aiimport-tab-pdf',
    ]);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'enctype' => 'multipart/form-data',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'extract']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source', 'value' => 'pdf']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('p',
        get_string('aiimportpdfhelp', 'local_coursecalendar'),
        ['class' => 'text-muted mb-2']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'file',
        'name' => 'inputpdf',
        'accept' => 'application/pdf,.pdf',
        'class' => 'form-control mb-2',
    ]);
    $pdfsubmitattrs = [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('aiimportpdfsubmit', 'local_coursecalendar'),
    ];
    if ($submitdisabled) {
        $pdfsubmitattrs['disabled'] = 'disabled';
    }
    echo html_writer::empty_tag('input', $pdfsubmitattrs);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div');

    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();
