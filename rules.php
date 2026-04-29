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
 * Timeline exception rules management page.
 *
 * @package    local_coursecalendar
 * @copyright  2026 Greg Mulcair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

$pageurl = new moodle_url('/local/coursecalendar/rules.php', ['id' => $courseid, 'calendarid' => $calendarid]);
$builderurl = new moodle_url('/local/coursecalendar/calendar.php', ['id' => $courseid, 'calendarid' => $calendarid]);

$ruletypes = local_coursecalendar_get_rule_types();
$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

if ($action !== '' && data_submitted()) {
    require_sesskey();

    switch ($action) {
        case 'createrule':
            $ruletype = core_text::strtoupper(trim(required_param('ruletype', PARAM_ALPHANUMEXT)));
            $datestr = required_param('ruledate', PARAM_TEXT);
            $ruledate = strtotime($datestr);
            if (!$ruledate) {
                redirect(
                    $pageurl,
                    get_string('errorinvaliddate', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            $label = trim(optional_param('label', '', PARAM_TEXT));
            $description = trim(optional_param('description', '', PARAM_RAW));
            $fromday = trim(optional_param('fromday', '', PARAM_TEXT)) ?: null;
            $today = trim(optional_param('today', '', PARAM_TEXT)) ?: null;

            if (in_array($ruletype, ['SEMESTER_START', 'SEMESTER_END'], true)) {
                $existing = $DB->get_record('local_coursecalendar_timeline_exception_rules', [
                    'calendarid' => (int)$calendar->id,
                    'ruletype' => $ruletype,
                    'isactive' => 1,
                ], 'id', IGNORE_MISSING);
                if ($existing) {
                    redirect(
                        $pageurl,
                        get_string('errorrulestartendexists', 'local_coursecalendar'),
                        null,
                        \core\output\notification::NOTIFY_ERROR
                    );
                }
            }

            local_coursecalendar_create_rule(
                (int)$calendar->id,
                $ruletype,
                $ruledate,
                $label,
                $description,
                $fromday,
                $today,
                (int)$USER->id
            );
            redirect($pageurl, get_string('rulecreated', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'updaterule':
            $ruleid = required_param('ruleid', PARAM_INT);
            $datestr = required_param('ruledate', PARAM_TEXT);
            $ruledate = strtotime($datestr);
            if (!$ruledate) {
                redirect(
                    $pageurl,
                    get_string('errorinvaliddate', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            $label = trim(optional_param('label', '', PARAM_TEXT));
            $description = trim(optional_param('description', '', PARAM_RAW));
            $fromday = trim(optional_param('fromday', '', PARAM_TEXT)) ?: null;
            $today = trim(optional_param('today', '', PARAM_TEXT)) ?: null;

            local_coursecalendar_update_rule($ruleid, $ruledate, $label, $description, $fromday, $today, (int)$USER->id);
            redirect($pageurl, get_string('ruleupdated', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'deleterule':
            $ruleid = required_param('ruleid', PARAM_INT);
            local_coursecalendar_delete_rule($ruleid);
            redirect($pageurl, get_string('ruledeleted', 'local_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'togglerule':
            $ruleid = required_param('ruleid', PARAM_INT);
            $nowactive = local_coursecalendar_toggle_rule($ruleid, (int)$USER->id);
            $msg = $nowactive
                ? get_string('ruleactivated', 'local_coursecalendar')
                : get_string('ruledeactivated', 'local_coursecalendar');
            redirect($pageurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'applyrules':
            try {
                $summary = local_coursecalendar_apply_rules((int)$calendar->id, (int)$USER->id);
                $msg = get_string('rulesapplied', 'local_coursecalendar', $summary['total_weeks']);
                redirect($builderurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
            } catch (moodle_exception $e) {
                redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
            }
            break;
    }
}

$rules = local_coursecalendar_get_calendar_rules((int)$calendar->id);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('rulespagetitle', 'local_coursecalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/coursecalendar/styles.css'));

echo $OUTPUT->header();
echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-pageheader']);
echo $OUTPUT->heading(get_string('rulespagetitle', 'local_coursecalendar'));
echo html_writer::tag('button', get_string('showtourbtn', 'local_coursecalendar'), [
    'type' => 'button',
    'id' => 'local-coursecalendar-showtour',
    'class' => 'btn btn-sm btn-outline-info local-coursecalendar-showtour',
    'data-tour-name' => 'local_coursecalendar_rules',
]);
echo html_writer::end_tag('div');
echo html_writer::link($builderurl, get_string('backtobuilder', 'local_coursecalendar'), ['class' => 'btn btn-secondary mb-3']);

echo html_writer::div(get_string('intro_rules', 'local_coursecalendar'), 'local-coursecalendar-intro alert alert-info');

$calendarlabel = s($calendar->semester) . ' ' . (int)$calendar->year;
echo html_writer::div(get_string('buildercontextlabel', 'local_coursecalendar', $calendarlabel), 'local-coursecalendar-shell mb-3');

echo html_writer::tag(
    'h4',
    get_string('section_rulesapply', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_rulesapply', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-section-title']
);
echo html_writer::start_tag(
    'form',
    ['method' => 'post', 'class' => 'local-coursecalendar-inline-form mb-3', 'id' => 'local-coursecalendar-applyrules-form']
);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'applyrules']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag(
    'input',
    ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('applyrulesbtn', 'local_coursecalendar')]
);
echo html_writer::end_tag('form');

$aiimporturl = new moodle_url('/local/coursecalendar/ai_import.php', ['id' => $courseid, 'calendarid' => $calendarid]);
echo html_writer::link($aiimporturl, get_string('aiimportlink', 'local_coursecalendar'), [
    'class' => 'btn btn-sm btn-outline-primary mb-3',
    'id' => 'local-coursecalendar-aiimport-link',
]);

echo html_writer::tag(
    'h4',
    get_string('section_rulesexisting', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_rulesexisting', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-section-title', 'id' => 'local-coursecalendar-existingrules']
);

// Create-new-rule disclosure.
$createrulehtml = '';
ob_start();
echo html_writer::start_tag('details', [
    'class' => 'local-coursecalendar-create-blueprint',
    'id' => 'local-coursecalendar-createrule',
]);
echo html_writer::tag('summary', get_string('createrulebutton', 'local_coursecalendar'), [
    'class' => 'local-coursecalendar-disclosure-summary local-coursecalendar-disclosure-summary--primary',
]);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'class' => 'local-coursecalendar-disclosure-body',
    'id' => 'local-coursecalendar-createrule-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createrule']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::tag('label', get_string('ruletypelabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
echo html_writer::start_tag('select', ['name' => 'ruletype', 'class' => 'custom-select mb-2']);
foreach ($ruletypes as $rt) {
    $ruletypekey = 'ruletype_' . $rt;
    $ruletypelabel = get_string_manager()->string_exists($ruletypekey, 'local_coursecalendar')
        ? get_string($ruletypekey, 'local_coursecalendar')
        : $rt;
    echo html_writer::tag('option', $ruletypelabel, ['value' => $rt]);
}
echo html_writer::end_tag('select');

echo html_writer::tag('label', get_string('ruledatelabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'ruledate',
    'class' => 'form-control mb-2',
    'required' => 'required',
]);

echo html_writer::tag('label', get_string('rulelabellabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'label',
    'class' => 'form-control mb-2',
    'placeholder' => get_string('rulelabelplaceholder', 'local_coursecalendar'),
]);

echo html_writer::tag('label', get_string('ruledescriptionlabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
echo html_writer::tag('textarea', '', [
    'name' => 'description',
    'class' => 'form-control mb-2',
    'rows' => 2,
    'placeholder' => get_string('ruledescriptionplaceholder', 'local_coursecalendar'),
]);

echo html_writer::tag('div', get_string('dayswapfieldshelp', 'local_coursecalendar'), ['class' => 'text-muted small mb-2']);

echo html_writer::tag('label', get_string('fromdaylabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
echo html_writer::start_tag('select', ['name' => 'fromday', 'class' => 'custom-select mb-2']);
echo html_writer::tag('option', '—', ['value' => '']);
foreach ($weekdays as $wd) {
    echo html_writer::tag('option', $wd, ['value' => $wd]);
}
echo html_writer::end_tag('select');

echo html_writer::tag('label', get_string('todaylabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
echo html_writer::start_tag('select', ['name' => 'today', 'class' => 'custom-select mb-2']);
echo html_writer::tag('option', '—', ['value' => '']);
foreach ($weekdays as $wd) {
    echo html_writer::tag('option', $wd, ['value' => $wd]);
}
echo html_writer::end_tag('select');

echo html_writer::empty_tag(
    'input',
    ['type' => 'submit', 'class' => 'btn btn-primary mt-2', 'value' => get_string('createrulesubmit', 'local_coursecalendar')]
);
echo html_writer::end_tag('form');
echo html_writer::end_tag('details');
$createrulehtml = ob_get_clean();

if (empty($rules)) {
    echo $OUTPUT->notification(get_string('norules', 'local_coursecalendar'), 'notifyinfo');
} else {
    echo html_writer::start_tag('ul', ['class' => 'local-coursecalendar-blueprint-list']);
    foreach ($rules as $rule) {
        $isactive = ((int)$rule->isactive === 1);
        $badgekey = $isactive ? 'ruleactive' : 'ruleinactive';
        $badgeclass = $isactive ? 'local-coursecalendar-badge--active' : 'local-coursecalendar-badge--archived';

        $ruletypekey = 'ruletype_' . $rule->ruletype;
        $ruletypelabel = get_string_manager()->string_exists($ruletypekey, 'local_coursecalendar')
            ? get_string($ruletypekey, 'local_coursecalendar')
            : s($rule->ruletype);

        $labeltext = trim((string)$rule->label);
        if ($labeltext === '') {
            $labeltext = $ruletypelabel;
        }
        if ($rule->ruletype === 'DAY_SWAP' && (!empty($rule->fromday) || !empty($rule->today))) {
            $labeltext .= ' (' . s($rule->fromday) . ' → ' . s($rule->today) . ')';
        }

        echo html_writer::start_tag('li', ['class' => 'local-coursecalendar-blueprint-item']);
        echo html_writer::start_tag('details', ['class' => 'local-coursecalendar-blueprint-details']);

        echo html_writer::start_tag('summary', ['class' => 'local-coursecalendar-blueprint-summary']);
        echo html_writer::start_div('local-coursecalendar-blueprint-summary-main');
        echo html_writer::tag('span', date('Y-m-d', (int)$rule->ruledate), ['class' => 'local-coursecalendar-blueprint-shortcode']);
        echo html_writer::tag('span', format_string($labeltext), ['class' => 'local-coursecalendar-blueprint-name']);
        echo html_writer::tag(
            'span',
            $ruletypelabel,
            ['class' => 'local-coursecalendar-type-badge local-coursecalendar-type-' . strtolower($rule->ruletype)]
        );
        echo html_writer::tag(
            'span',
            get_string($badgekey, 'local_coursecalendar'),
            ['class' => 'local-coursecalendar-badge ' . $badgeclass]
        );
        echo html_writer::end_div();
        echo html_writer::tag('span', get_string('editrulebutton', 'local_coursecalendar'), [
            'class' => 'btn btn-outline-secondary btn-sm local-coursecalendar-edit-indicator',
            'aria-hidden' => 'true',
        ]);
        echo html_writer::end_tag('summary');

        echo html_writer::start_div('local-coursecalendar-disclosure-body');

        echo html_writer::start_tag('form', ['method' => 'post']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'updaterule']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ruleid', 'value' => (int)$rule->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('ruletypelabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
        echo html_writer::div($ruletypelabel, 'form-control-plaintext');
        echo html_writer::end_div();

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('ruledatelabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
        echo html_writer::empty_tag('input', [
            'type' => 'date',
            'name' => 'ruledate',
            'class' => 'form-control',
            'required' => 'required',
            'value' => date('Y-m-d', (int)$rule->ruledate),
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('rulelabellabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'label',
            'class' => 'form-control',
            'placeholder' => get_string('rulelabelplaceholder', 'local_coursecalendar'),
            'value' => s((string)$rule->label),
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('ruledescriptionlabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
        echo html_writer::tag('textarea', s((string)$rule->description), [
            'name' => 'description',
            'class' => 'form-control',
            'rows' => 2,
            'placeholder' => get_string('ruledescriptionplaceholder', 'local_coursecalendar'),
        ]);
        echo html_writer::end_div();

        if ($rule->ruletype === 'DAY_SWAP') {
            echo html_writer::tag(
                'div',
                get_string('dayswapfieldshelp', 'local_coursecalendar'),
                ['class' => 'text-muted small mb-2']
            );

            echo html_writer::start_div('mb-2');
            echo html_writer::tag('label', get_string('fromdaylabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
            echo html_writer::start_tag('select', ['name' => 'fromday', 'class' => 'custom-select']);
            echo html_writer::tag('option', '—', ['value' => '']);
            foreach ($weekdays as $wd) {
                $attrs = ['value' => $wd];
                if ((string)$rule->fromday === $wd) {
                    $attrs['selected'] = 'selected';
                }
                echo html_writer::tag('option', $wd, $attrs);
            }
            echo html_writer::end_tag('select');
            echo html_writer::end_div();

            echo html_writer::start_div('mb-2');
            echo html_writer::tag('label', get_string('todaylabel', 'local_coursecalendar'), ['class' => 'font-weight-bold']);
            echo html_writer::start_tag('select', ['name' => 'today', 'class' => 'custom-select']);
            echo html_writer::tag('option', '—', ['value' => '']);
            foreach ($weekdays as $wd) {
                $attrs = ['value' => $wd];
                if ((string)$rule->today === $wd) {
                    $attrs['selected'] = 'selected';
                }
                echo html_writer::tag('option', $wd, $attrs);
            }
            echo html_writer::end_tag('select');
            echo html_writer::end_div();
        }

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-secondary',
            'value' => get_string('saverulesubmit', 'local_coursecalendar'),
        ]);
        echo html_writer::end_tag('form');

        echo html_writer::start_div('local-coursecalendar-inline-controls');
        foreach (['togglerule' => 'togglerulesubmit', 'deleterule' => 'deleterulesubmit'] as $ruleaction => $labelkey) {
            echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-inline-form']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => $calendarid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $ruleaction]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ruleid', 'value' => (int)$rule->id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $buttonclass = ($ruleaction === 'deleterule') ? 'btn btn-outline-danger' : 'btn btn-outline-secondary';
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => $buttonclass,
                'value' => get_string($labelkey, 'local_coursecalendar'),
            ]);
            echo html_writer::end_tag('form');
        }
        echo html_writer::end_div();

        echo html_writer::end_div();
        echo html_writer::end_tag('details');
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}
echo $createrulehtml;

$tourid = local_coursecalendar_get_tour_id_by_name('local_coursecalendar_rules');
$PAGE->requires->js_call_amd('local_coursecalendar/showtour', 'init', [
    $tourid,
    '#local-coursecalendar-showtour',
]);

echo $OUTPUT->footer();
