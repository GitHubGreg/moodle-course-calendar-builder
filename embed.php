<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Embeddable calendar view.
 *
 * @package    local_coursecalendar
 * @copyright  2026 Greg Mulcair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

$PAGE->set_url(new moodle_url('/local/coursecalendar/embed.php', ['id' => $courseid, 'calendarid' => $calendarid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('embedpagetitle', 'local_coursecalendar'));
$PAGE->requires->css(new moodle_url('/local/coursecalendar/styles.css'));

// Compute today cell.
$now = new DateTime('now', new DateTimeZone('America/Toronto'));
$todaytimestamp = $now->getTimestamp();
$todaycell = local_coursecalendar_date_to_cell($blocksmap, $maxrow, $todaytimestamp);
$todayrow = $todaycell ? ($todaycell['row'] ?? null) : null;
$todaycol = $todaycell ? ($todaycell['col'] ?? null) : null;
$nearestonly = $todaycell && !empty($todaycell['nearest']);

echo $OUTPUT->header();

echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-embed']);
echo html_writer::start_tag('table', ['class' => 'table table-bordered local-coursecalendar-grid local-coursecalendar-preview']);
for ($row = 0; $row <= $maxrow; $row++) {
    $rowclasses = [];
    if ($row === $todayrow && ($nearestonly || $todaycol === null)) {
        $rowclasses[] = 'local-coursecalendar-nearest-row';
    }
    echo html_writer::start_tag('tr', $rowclasses ? ['class' => implode(' ', $rowclasses)] : []);
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
            echo html_writer::tag(
                'div',
                $typebadge . ' ' . format_string($selectedtopic->title),
                ['class' => 'local-coursecalendar-topic-display']
            );
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
echo html_writer::end_tag('div');

if ($todayrow) {
    $scrollscript = <<<'JS'
<script>
document.addEventListener("DOMContentLoaded", function() {
    var selector = ".local-coursecalendar-today-cell,.local-coursecalendar-nearest-row";
    var target = document.querySelector(selector);
    if (target) {
        target.scrollIntoView({behavior: "smooth", block: "center"});
    }
});
</script>
JS;
    echo $scrollscript;
}

echo $OUTPUT->footer();
