<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$courseid = required_param('id', PARAM_INT);
$calendarid = required_param('calendarid', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/coursecalendar:viewcalendar', $context);

$calendar = local_coursecalendar_require_course_calendar($calendarid, $courseid);
$blueprintid = (int)$calendar->blueprintid;
$alltopics = local_coursecalendar_get_blueprint_topics($blueprintid, true);

$blocksmap = local_coursecalendar_get_blocks_map((int)$calendar->id);
$maxrow = 0;
foreach (array_keys($blocksmap) as $rownum) {
    $maxrow = max($maxrow, (int)$rownum);
}

$pageurl = new moodle_url('/local/coursecalendar/view.php', ['id' => $courseid, 'calendarid' => $calendarid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('studentviewheading', 'local_coursecalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/coursecalendar/styles.css'));

// Compute today cell.
$now = new DateTime('now', new DateTimeZone('America/Toronto'));
$todaytimestamp = $now->getTimestamp();
$todaycell = local_coursecalendar_date_to_cell($blocksmap, $maxrow, $todaytimestamp);
$todayrow = $todaycell ? ($todaycell['row'] ?? null) : null;
$todaycol = $todaycell ? ($todaycell['col'] ?? null) : null;
$nearestonly = $todaycell && !empty($todaycell['nearest']);

echo $OUTPUT->header();

$calendarlabel = s($calendar->semester) . ' ' . (int)$calendar->year;
if (!empty($calendar->title)) {
    $calendarlabel .= ' &ndash; ' . format_string($calendar->title);
}
echo $OUTPUT->heading(get_string('studentviewheading', 'local_coursecalendar'));
echo html_writer::div($calendarlabel, 'local-coursecalendar-shell mb-3');

// Course info section.
$courseinfo = local_coursecalendar_get_course_info($courseid);
if ($courseinfo) {
    $introleft = trim((string)$courseinfo->introhtml);
    $introright = trim((string)$courseinfo->linkshtml);
    if ($introleft !== '' || $introright !== '') {
        echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-course-intro local-coursecalendar-course-intro-grid']);
        if ($introleft !== '') {
            echo html_writer::start_div('local-coursecalendar-course-intro-panel');
            echo format_text($introleft, FORMAT_HTML);
            echo html_writer::end_div();
        }
        if ($introright !== '') {
            echo html_writer::start_div('local-coursecalendar-course-intro-panel');
            $righthtml = format_text($introright, FORMAT_HTML);
            $righthtml = preg_replace('/<a\b/', '<a target="_blank"', $righthtml);
            echo $righthtml;
            echo html_writer::end_div();
        }
        echo html_writer::end_tag('div');
    }
}

if ($maxrow === 0 && empty($blocksmap)) {
    echo $OUTPUT->notification(get_string('previewempty', 'local_coursecalendar'), 'notifyinfo');
    echo $OUTPUT->footer();
    die;
}

echo html_writer::start_tag('table', ['class' => 'table table-bordered local-coursecalendar-grid local-coursecalendar-preview']);
for ($row = 0; $row <= $maxrow; $row++) {
    $rowclasses = [];
    if ($row === $todayrow && ($nearestonly || $todaycol === null)) {
        $rowclasses[] = 'local-coursecalendar-nearest-row';
    }
    echo html_writer::start_tag('tr', $rowclasses ? ['class' => implode(' ', $rowclasses), 'id' => 'cc-row-' . $row] : ['id' => 'cc-row-' . $row]);
    for ($col = 0; $col <= 4; $col++) {
        $cell = $blocksmap[$row][$col] ?? null;
        $content = $cell ? (string)$cell->contenthtml : '';
        $blocktype = $cell ? (string)$cell->blocktype : '';
        $cellheading = $cell ? (string)$cell->cellheading : '';
        $highlighted = $cell && (int)$cell->highlighted === 1;
        $verticallycentred = $cell && (int)$cell->verticallycentred === 1;
        $selectedtopicid = ($cell && !empty($cell->topicid)) ? (int)$cell->topicid : 0;
        $selectedtopic = ($selectedtopicid > 0 && isset($alltopics[$selectedtopicid])) ? $alltopics[$selectedtopicid] : null;

        $tag = ($row === 0) ? 'th' : 'td';
        $cellclasses = ['local-coursecalendar-grid-cell'];
        if ($highlighted) {
            $cellclasses[] = 'local-coursecalendar-highlighted';
        }
        if ($verticallycentred) {
            $cellclasses[] = 'local-coursecalendar-vcentred';
        }
        if ($row === 0) {
            $cellclasses[] = 'local-coursecalendar-preview-header';
        }
        if ($row === $todayrow && $col === $todaycol && !$nearestonly) {
            $cellclasses[] = 'local-coursecalendar-today-cell';
        }
        echo html_writer::start_tag($tag, ['class' => implode(' ', $cellclasses)]);

        if ($cellheading !== '') {
            echo html_writer::tag('div', format_text($cellheading, FORMAT_HTML), ['class' => 'local-coursecalendar-cellheading']);
        }

        if ($blocktype === 'TOPIC' && $selectedtopic) {
            $typebadge = html_writer::tag('span', s($selectedtopic->type), [
                'class' => 'local-coursecalendar-type-badge local-coursecalendar-type-' . strtolower($selectedtopic->type),
            ]);
            echo html_writer::tag('div', $typebadge . ' ' . format_string($selectedtopic->title), ['class' => 'local-coursecalendar-topic-display']);
            if (!empty($selectedtopic->contenthtml)) {
                $topichtml = format_text($selectedtopic->contenthtml, FORMAT_HTML);
                $topichtml = preg_replace('/<a\b/', '<a target="_blank"', $topichtml);
                echo html_writer::tag('div', $topichtml, ['class' => 'local-coursecalendar-topic-preview']);
            }
        } else if ($row === 0) {
            echo html_writer::tag('div', format_text($content, FORMAT_HTML), ['class' => 'local-coursecalendar-readonly-cell']);
            if ($cell && !empty($cell->headerday)) {
                echo html_writer::tag('div', s($cell->headerday) . ($cell->headermode ? ' &middot; ' . s($cell->headermode) : ''), [
                    'class' => 'local-coursecalendar-header-meta',
                ]);
            }
        } else if ($content !== '') {
            $texthtml = format_text($content, FORMAT_HTML);
            $texthtml = preg_replace('/<a\b/', '<a target="_blank"', $texthtml);
            echo html_writer::tag('div', $texthtml, ['class' => 'local-coursecalendar-text-preview']);
        }

        echo html_writer::end_tag($tag);
    }
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('table');

// Auto-scroll to nearest row.
if ($todayrow) {
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("cc-row-' . $todayrow . '");if(el)el.scrollIntoView({behavior:"smooth",block:"center"});});</script>';
}

echo $OUTPUT->footer();
