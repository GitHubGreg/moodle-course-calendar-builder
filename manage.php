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
 * Builder management page.
 *
 * @package    local_coursecalendar
 * @copyright  2026 Greg Mulcair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/coursecalendar:managecalendar', $context);

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$selectedblueprintid = optional_param('blueprintctx', 0, PARAM_INT);
$topicfilter = core_text::strtoupper(optional_param('topicfilter', 'ALL', PARAM_ALPHANUMEXT));
if (!in_array($topicfilter, array_merge(['ALL'], local_coursecalendar_get_topic_types()), true)) {
    $topicfilter = 'ALL';
}

if ($action !== '' && data_submitted()) {
    require_sesskey();
    $redirectparams = ['id' => $courseid];
    if ($selectedblueprintid > 0) {
        $redirectparams['blueprintctx'] = $selectedblueprintid;
    }
    if ($topicfilter !== 'ALL') {
        $redirectparams['topicfilter'] = $topicfilter;
    }
    $redirecturl = new moodle_url('/local/coursecalendar/manage.php', $redirectparams);

    switch ($action) {
        case 'createblueprint':
            $name = trim(optional_param('name', '', PARAM_TEXT));
            $shortcode = core_text::strtoupper(trim(optional_param('shortcode', '', PARAM_ALPHANUMEXT)));
            $description = trim(optional_param('description', '', PARAM_TEXT));

            if ($name === '') {
                redirect(
                    $redirecturl,
                    get_string('errorblueprintnamerequired', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            if ($DB->record_exists('local_coursecalendar_blueprints', ['owneruserid' => $USER->id, 'name' => $name])) {
                redirect(
                    $redirecturl,
                    get_string('errorblueprintduplicate', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            $now = time();
            $record = (object)[
                'owneruserid' => $USER->id,
                'shortcode' => $shortcode,
                'name' => $name,
                'description' => $description,
                'isarchived' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => $USER->id,
            ];
            $newblueprintid = (int)$DB->insert_record('local_coursecalendar_blueprints', $record);
            $redirecturl->param('blueprintctx', $newblueprintid);
            redirect(
                $redirecturl,
                get_string('blueprintcreated', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'updateblueprint':
            $blueprintid = required_param('blueprintid', PARAM_INT);
            $blueprint = local_coursecalendar_require_owned_blueprint($blueprintid, (int)$USER->id);

            $name = trim(optional_param('name', '', PARAM_TEXT));
            $shortcode = core_text::strtoupper(trim(optional_param('shortcode', '', PARAM_ALPHANUMEXT)));
            $description = trim(optional_param('description', '', PARAM_TEXT));
            if ($name === '') {
                redirect(
                    $redirecturl,
                    get_string('errorblueprintnamerequired', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            if (
                $DB->record_exists_select(
                    'local_coursecalendar_blueprints',
                    'owneruserid = :owneruserid AND name = :name AND id <> :id',
                    ['owneruserid' => $USER->id, 'name' => $name, 'id' => $blueprint->id]
                )
            ) {
                redirect(
                    $redirecturl,
                    get_string('errorblueprintduplicate', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            $blueprint->name = $name;
            $blueprint->shortcode = $shortcode;
            $blueprint->description = $description;
            $blueprint->timemodified = time();
            $blueprint->usermodified = $USER->id;
            $DB->update_record('local_coursecalendar_blueprints', $blueprint);
            $redirecturl->param('blueprintctx', (int)$blueprint->id);
            redirect(
                $redirecturl,
                get_string('blueprintupdated', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'togglearchive':
            $blueprintid = required_param('blueprintid', PARAM_INT);
            $blueprint = local_coursecalendar_require_owned_blueprint($blueprintid, (int)$USER->id);
            $blueprint->isarchived = $blueprint->isarchived ? 0 : 1;
            $blueprint->timemodified = time();
            $blueprint->usermodified = $USER->id;
            $DB->update_record('local_coursecalendar_blueprints', $blueprint);
            $messagekey = $blueprint->isarchived ? 'blueprintarchived' : 'blueprintunarchived';
            $redirecturl->param('blueprintctx', (int)$blueprint->id);
            redirect(
                $redirecturl,
                get_string($messagekey, 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'linkblueprint':
            $blueprintid = required_param('blueprintid', PARAM_INT);
            $blueprint = local_coursecalendar_require_owned_blueprint($blueprintid, (int)$USER->id);
            if ((int)$blueprint->isarchived === 1) {
                redirect(
                    $redirecturl,
                    get_string('errorarchivedblueprintlink', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            $linknotes = trim(optional_param('linknotes', '', PARAM_TEXT));
            local_coursecalendar_upsert_course_blueprint_link(
                $courseid,
                (int)$blueprint->id,
                'MANUAL',
                null,
                $linknotes,
                (int)$USER->id
            );
            $redirecturl->param('blueprintctx', (int)$blueprint->id);
            redirect(
                $redirecturl,
                get_string('courselinkupdated', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'autolinkcourse':
            $suggestion = local_coursecalendar_get_autolink_suggestion($course, (int)$USER->id);
            if (!$suggestion || !empty($suggestion['ambiguous'])) {
                redirect(
                    $redirecturl,
                    get_string('noautosuggestion', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            $best = $suggestion['best'];
            local_coursecalendar_upsert_course_blueprint_link(
                $courseid,
                (int)$best['blueprint']->id,
                'AUTO',
                (int)$best['confidence'],
                get_string('autolinknotes', 'local_coursecalendar'),
                (int)$USER->id
            );
            $redirecturl->param('blueprintctx', (int)$best['blueprint']->id);
            redirect(
                $redirecturl,
                get_string('courseautolinked', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'unlinkblueprint':
            $DB->delete_records('local_coursecalendar_course_blueprint_link', ['courseid' => $courseid]);
            redirect(
                $redirecturl,
                get_string('courselinkremoved', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'createcalendar':
            $blueprintid = required_param('blueprintid', PARAM_INT);
            $blueprint = local_coursecalendar_require_owned_blueprint($blueprintid, (int)$USER->id);
            $year = required_param('year', PARAM_INT);
            $semester = local_coursecalendar_normalise_semester(required_param('semester', PARAM_ALPHANUMEXT));
            $title = trim(optional_param('title', '', PARAM_TEXT));

            if ($year < 2000 || $year > 2200) {
                redirect(
                    $redirecturl,
                    get_string('errorinvalidyear', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            $dupconds = ['courseid' => $courseid, 'year' => $year, 'semester' => $semester];
            if ($DB->record_exists('local_coursecalendar_semester_calendars', $dupconds)) {
                redirect(
                    $redirecturl,
                    get_string('errorcalendarduplicate', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            $now = time();
            $record = (object)[
                'courseid' => $courseid,
                'blueprintid' => (int)$blueprint->id,
                'year' => $year,
                'semester' => $semester,
                'title' => $title,
                'isactive' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => (int)$USER->id,
            ];
            $DB->set_field('local_coursecalendar_semester_calendars', 'isactive', 0, ['courseid' => $courseid]);
            $DB->insert_record('local_coursecalendar_semester_calendars', $record);
            redirect(
                $redirecturl,
                get_string('calendarcreated', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'updatecalendar':
            $calendarid = required_param('calendarid', PARAM_INT);
            $calendar = local_coursecalendar_require_course_calendar($calendarid, $courseid);
            local_coursecalendar_require_owned_blueprint((int)$calendar->blueprintid, (int)$USER->id);
            $calendar->title = trim(optional_param('title', '', PARAM_TEXT));
            $calendar->timemodified = time();
            $calendar->usermodified = (int)$USER->id;
            $DB->update_record('local_coursecalendar_semester_calendars', $calendar);
            redirect(
                $redirecturl,
                get_string('calendarupdated', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'togglecalendaractive':
            $calendarid = required_param('calendarid', PARAM_INT);
            $calendar = local_coursecalendar_require_course_calendar($calendarid, $courseid);
            local_coursecalendar_require_owned_blueprint((int)$calendar->blueprintid, (int)$USER->id);
            $activating = (int)$calendar->isactive !== 1;
            if ($activating) {
                $DB->set_field('local_coursecalendar_semester_calendars', 'isactive', 0, ['courseid' => $courseid]);
            }
            $calendar->isactive = $activating ? 1 : 0;
            $calendar->timemodified = time();
            $calendar->usermodified = (int)$USER->id;
            $DB->update_record('local_coursecalendar_semester_calendars', $calendar);
            $messagekey = $activating ? 'calendaractivated' : 'calendardeactivated';
            redirect(
                $redirecturl,
                get_string($messagekey, 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'deletecalendar':
            $calendarid = required_param('calendarid', PARAM_INT);
            $calendar = local_coursecalendar_require_course_calendar($calendarid, $courseid);
            local_coursecalendar_require_owned_blueprint((int)$calendar->blueprintid, (int)$USER->id);
            $DB->delete_records('local_coursecalendar_rule_apply_runs', ['calendarid' => $calendar->id]);
            $DB->delete_records('local_coursecalendar_calendar_blocks', ['calendarid' => $calendar->id]);
            $DB->delete_records('local_coursecalendar_timeline_exception_rules', ['calendarid' => $calendar->id]);
            $DB->delete_records('local_coursecalendar_semester_calendars', ['id' => $calendar->id]);
            redirect(
                $redirecturl,
                get_string('calendardeleted', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'createtopic':
            $blueprintid = required_param('blueprintid', PARAM_INT);
            $blueprint = local_coursecalendar_require_owned_blueprint($blueprintid, (int)$USER->id);
            $title = trim(required_param('title', PARAM_TEXT));
            $type = local_coursecalendar_normalise_topic_type(required_param('type', PARAM_ALPHANUMEXT));
            $contenthtml = trim(optional_param('contenthtml', '', PARAM_RAW));

            if ($title === '') {
                $redirecturl->param('blueprintctx', (int)$blueprint->id);
                redirect(
                    $redirecturl,
                    get_string('errortopictitlerequired', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            $sortorder = (int)$DB->count_records('local_coursecalendar_blueprint_topics', ['blueprintid' => $blueprint->id]) + 1;
            $now = time();
            $record = (object)[
                'blueprintid' => $blueprint->id,
                'title' => $title,
                'type' => $type,
                'contenthtml' => $contenthtml,
                'sortorder' => $sortorder,
                'isactive' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => $USER->id,
            ];
            $DB->insert_record('local_coursecalendar_blueprint_topics', $record);
            $redirecturl->param('blueprintctx', (int)$blueprint->id);
            redirect(
                $redirecturl,
                get_string('topiccreated', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'updatetopic':
            $topicid = required_param('topicid', PARAM_INT);
            $topic = local_coursecalendar_require_owned_topic($topicid, (int)$USER->id);
            $topic->title = trim(required_param('title', PARAM_TEXT));
            $topic->type = local_coursecalendar_normalise_topic_type(required_param('type', PARAM_ALPHANUMEXT));
            $topic->contenthtml = trim(optional_param('contenthtml', '', PARAM_RAW));
            if ($topic->title === '') {
                $redirecturl->param('blueprintctx', (int)$topic->blueprintid);
                redirect(
                    $redirecturl,
                    get_string('errortopictitlerequired', 'local_coursecalendar'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            $topic->timemodified = time();
            $topic->usermodified = $USER->id;
            $DB->update_record('local_coursecalendar_blueprint_topics', $topic);
            $redirecturl->param('blueprintctx', (int)$topic->blueprintid);
            redirect(
                $redirecturl,
                get_string('topicupdated', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'toggletopicactive':
            $topicid = required_param('topicid', PARAM_INT);
            $topic = local_coursecalendar_require_owned_topic($topicid, (int)$USER->id);
            $topic->isactive = $topic->isactive ? 0 : 1;
            $topic->timemodified = time();
            $topic->usermodified = $USER->id;
            $DB->update_record('local_coursecalendar_blueprint_topics', $topic);
            $redirecturl->param('blueprintctx', (int)$topic->blueprintid);
            $messagekey = $topic->isactive ? 'topicactivated' : 'topicdeactivated';
            redirect(
                $redirecturl,
                get_string($messagekey, 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'movetopicup':
        case 'movetopicdown':
            $topicid = required_param('topicid', PARAM_INT);
            $topic = local_coursecalendar_require_owned_topic($topicid, (int)$USER->id);
            $direction = ($action === 'movetopicup') ? -1 : 1;
            local_coursecalendar_move_topic($topic, $direction);
            $redirecturl->param('blueprintctx', (int)$topic->blueprintid);
            redirect(
                $redirecturl,
                get_string('topicreordered', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'deletetopic':
            $topicid = required_param('topicid', PARAM_INT);
            $topic = local_coursecalendar_require_owned_topic($topicid, (int)$USER->id);
            $usagerows = local_coursecalendar_get_topic_usage_rows((int)$topic->id);
            if (!empty($usagerows)) {
                $examples = [];
                foreach (array_slice(array_values($usagerows), 0, 3) as $row) {
                    $examples[] = '#' . (int)$row->id . ' (' . s((string)$row->semester) . ' ' . (int)$row->year . ')';
                }
                $detail = implode(', ', $examples);
                $redirecturl->param('blueprintctx', (int)$topic->blueprintid);
                redirect(
                    $redirecturl,
                    get_string('errortopicinuse', 'local_coursecalendar', (object)[
                        'count' => count($usagerows),
                        'calendars' => $detail,
                    ]),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            $DB->delete_records('local_coursecalendar_blueprint_topics', ['id' => $topic->id]);
            local_coursecalendar_normalise_topic_sortorder((int)$topic->blueprintid);
            $redirecturl->param('blueprintctx', (int)$topic->blueprintid);
            redirect(
                $redirecturl,
                get_string('topicdeleted', 'local_coursecalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;
    }
}

$url = new moodle_url('/local/coursecalendar/manage.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('managepageheading', 'local_coursecalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/coursecalendar/styles.css'));

$allblueprints = local_coursecalendar_get_teacher_blueprints((int)$USER->id, true);
$activeblueprints = array_filter($allblueprints, static function (stdClass $record): bool {
    return (int)$record->isarchived === 0;
});

$linkrecord = local_coursecalendar_get_course_link_record($courseid);
$linkedblueprint = null;
if ($linkrecord) {
    $linkedblueprint = $DB->get_record('local_coursecalendar_blueprints', ['id' => $linkrecord->blueprintid], '*', IGNORE_MISSING);
    if ($linkedblueprint && (int)$linkedblueprint->owneruserid !== (int)$USER->id) {
        $linkedblueprint = null;
    }
}

$suggestion = local_coursecalendar_get_autolink_suggestion($course, (int)$USER->id);
$coursecalendars = local_coursecalendar_get_course_calendars($courseid);

if ($selectedblueprintid <= 0) {
    if ($linkedblueprint) {
        $selectedblueprintid = (int)$linkedblueprint->id;
    } else if (!empty($allblueprints)) {
        $firstblueprint = reset($allblueprints);
        $selectedblueprintid = (int)$firstblueprint->id;
    }
}

$selectedblueprint = null;
if ($selectedblueprintid > 0) {
    $selectedblueprint = $DB->get_record('local_coursecalendar_blueprints', ['id' => $selectedblueprintid], '*', IGNORE_MISSING);
    if ($selectedblueprint && (int)$selectedblueprint->owneruserid !== (int)$USER->id) {
        $selectedblueprint = null;
    }
}

$topics = [];
if ($selectedblueprint) {
    $topics = array_values(local_coursecalendar_get_blueprint_topics((int)$selectedblueprint->id, true));
    if ($topicfilter !== 'ALL') {
        $topics = array_values(array_filter($topics, static function (stdClass $topic) use ($topicfilter): bool {
            return $topic->type === $topicfilter;
        }));
    }
}

$suggestedblueprintid = 0;
if (!$linkedblueprint && !empty($suggestion) && empty($suggestion['ambiguous']) && !empty($suggestion['best'])) {
    $suggestedblueprintid = (int)$suggestion['best']['blueprint']->id;
}

$hasblueprints = !empty($allblueprints);
$hasactiveblueprints = !empty($activeblueprints);

$recommendedcalendar = null;
$recommendedreasonkey = '';
if (!empty($coursecalendars)) {
    $coursemarkers = core_text::strtoupper(
        implode(' ', [
            (string)$course->shortname,
            (string)$course->fullname,
            (string)$course->idnumber,
        ])
    );
    $now = time();
    $currentyear = (int)date('Y', $now);
    $courseconfigsemester = null;
    $courseconfigyear = null;
    if (!empty($course->startdate)) {
        $coursestartmonth = (int)date('n', (int)$course->startdate);
        $courseconfigyear = (int)date('Y', (int)$course->startdate);
        if ($coursestartmonth >= 8) {
            $courseconfigsemester = 'FALL';
        } else if ($coursestartmonth >= 5) {
            $courseconfigsemester = 'SUMMER';
        } else {
            $courseconfigsemester = 'WINTER';
        }
    }
    $scores = [];
    foreach ($coursecalendars as $calendar) {
        $calendarid = (int)$calendar->id;
        $semesteryear = (int)$calendar->year;
        $semester = core_text::strtoupper((string)$calendar->semester);
        $semesterprefix = substr($semester, 0, 1);
        $semesterpattern = '/' . preg_quote($semester, '/') . '\s*' . $semesteryear
            . '|' . $semesteryear . '\s*' . preg_quote($semester, '/')
            . '|' . preg_quote($semesterprefix, '/') . '\s*' . $semesteryear
            . '|' . $semesteryear . '\s*' . preg_quote($semesterprefix, '/') . '/';

        $rules = local_coursecalendar_get_calendar_rules($calendarid, true);
        $startdate = null;
        $enddate = null;
        foreach ($rules as $rule) {
            if ($rule->ruletype === 'SEMESTER_START') {
                $startdate = (int)$rule->ruledate;
            } else if ($rule->ruletype === 'SEMESTER_END') {
                $enddate = (int)$rule->ruledate;
            }
        }

        $score = 0;
        $reasonkey = 'calendarrecommend_reason_newest';
        if ((int)$calendar->isactive === 1) {
            $score += 1000000;
            $reasonkey = 'calendarrecommend_reason_active';
        }
        if (preg_match($semesterpattern, $coursemarkers) === 1) {
            $score += 750000;
            $reasonkey = 'calendarrecommend_reason_coursematch';
        }
        if ($courseconfigsemester === $semester && $courseconfigyear === $semesteryear) {
            $score += 850000;
            $reasonkey = 'calendarrecommend_reason_courseconfig';
        }
        if ($startdate && $enddate && $now >= $startdate && $now <= $enddate) {
            $score += 500000;
            $reasonkey = 'calendarrecommend_reason_currentdate';
        } else if ($startdate && $startdate > $now) {
            $score += max(0, 250000 - (int)(($startdate - $now) / DAYSECS));
            $reasonkey = 'calendarrecommend_reason_upcoming';
        }
        if ($semesteryear === $currentyear) {
            $score += 25000;
        }
        $score += min((int)$calendar->timemodified, 2147483647) / 100000;
        $score += ($semesteryear * 10);
        $scores[$calendarid] = ['score' => $score, 'reasonkey' => $reasonkey];
    }

    uasort($coursecalendars, static function (stdClass $left, stdClass $right) use ($scores): int {
        $leftscore = $scores[(int)$left->id]['score'] ?? 0;
        $rightscore = $scores[(int)$right->id]['score'] ?? 0;
        if ($leftscore === $rightscore) {
            return (int)$right->id <=> (int)$left->id;
        }
        return ($rightscore <=> $leftscore);
    });
    $recommendedcalendar = reset($coursecalendars) ?: null;
    if ($recommendedcalendar) {
        $recommendedreasonkey = $scores[(int)$recommendedcalendar->id]['reasonkey'] ?? 'calendarrecommend_reason_newest';
    }
}

$linkedtopiccount = null;
if ($linkedblueprint) {
    $linkedtopics = local_coursecalendar_get_blueprint_topics((int)$linkedblueprint->id, true);
    $linkedtopiccount = count(array_filter($linkedtopics, static function (stdClass $topic): bool {
        return (int)$topic->isactive === 1;
    }));
}

editors_head_setup();
$topiceditor = editors_get_preferred_editor(FORMAT_HTML);
$topiceditoroptions = [
    'context' => $context,
    'autosave' => false,
    'enable_filemanagement' => false,
];

$rendercreateblueprintform = static function (bool $open = false) use ($courseid): void {
    $detailsattrs = [
        'class' => 'local-coursecalendar-card local-coursecalendar-create-blueprint',
        'id' => 'local-coursecalendar-createblueprint',
    ];
    if ($open) {
        $detailsattrs['open'] = 'open';
    }

    echo html_writer::start_tag('details', $detailsattrs);
    echo html_writer::tag(
        'summary',
        get_string('createblueprintbutton', 'local_coursecalendar'),
        ['class' => 'local-coursecalendar-disclosure-summary local-coursecalendar-disclosure-summary--primary']
    );
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'id' => 'local-coursecalendar-createblueprint-form',
        'class' => 'local-coursecalendar-disclosure-body',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createblueprint']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::start_div('mb-2');
    echo html_writer::tag(
        'label',
        get_string('blueprintnamelabel', 'local_coursecalendar'),
        ['for' => 'local-coursecalendar-name-new']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'id' => 'local-coursecalendar-name-new',
        'name' => 'name',
        'class' => 'form-control',
        'required' => 'required',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-2');
    echo html_writer::tag(
        'label',
        get_string('blueprintshortcodelabel', 'local_coursecalendar'),
        ['for' => 'local-coursecalendar-shortcode-new']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'id' => 'local-coursecalendar-shortcode-new',
        'name' => 'shortcode',
        'maxlength' => 32,
        'class' => 'form-control',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-2');
    echo html_writer::tag(
        'label',
        get_string('blueprintdescriptionlabel', 'local_coursecalendar'),
        ['for' => 'local-coursecalendar-description-new']
    );
    echo html_writer::tag('textarea', '', [
        'id' => 'local-coursecalendar-description-new',
        'name' => 'description',
        'rows' => 3,
        'class' => 'form-control',
    ]);
    echo html_writer::end_div();

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('createblueprintsubmit', 'local_coursecalendar'),
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('details');
};

echo $OUTPUT->header();
echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-pageheader']);
echo $OUTPUT->heading(get_string('managepageheading', 'local_coursecalendar'));
echo html_writer::tag('button', get_string('showtourbtn', 'local_coursecalendar'), [
    'type' => 'button',
    'id' => 'local-coursecalendar-showtour',
    'class' => 'btn btn-sm btn-outline-info local-coursecalendar-showtour',
    'data-tour-name' => 'local_coursecalendar_setup',
]);
echo html_writer::end_tag('div');

$nextsteptitle = '';
$nextstepbody = '';
$nextstepaction = '';
$nextstepbutton = '';
$nextstepcomplete = false;
if (!$hasblueprints) {
    $nextsteptitle = get_string('setupnext_createblueprint_title', 'local_coursecalendar');
    $nextstepbody = get_string('setupnext_createblueprint_body', 'local_coursecalendar');
    $nextstepaction = '#local-coursecalendar-createblueprint';
    $nextstepbutton = get_string('setupnext_createblueprint_action', 'local_coursecalendar');
} else if (!$hasactiveblueprints) {
    $nextsteptitle = get_string('setupnext_restoreblueprint_title', 'local_coursecalendar');
    $nextstepbody = get_string('setupnext_restoreblueprint_body', 'local_coursecalendar');
    $nextstepaction = '#local-coursecalendar-section-blueprints';
    $nextstepbutton = get_string('setupnext_restoreblueprint_action', 'local_coursecalendar');
} else if (!$linkedblueprint) {
    $nextsteptitle = get_string('setupnext_linkcourse_title', 'local_coursecalendar');
    $nextstepbody = get_string('setupnext_linkcourse_body', 'local_coursecalendar');
    $nextstepaction = '#local-coursecalendar-section-linkcourse';
    $nextstepbutton = get_string('setupnext_linkcourse_action', 'local_coursecalendar');
} else if ($linkedtopiccount === 0) {
    $nextsteptitle = get_string('setupnext_addtopics_title', 'local_coursecalendar');
    $nextstepbody = get_string('setupnext_addtopics_body', 'local_coursecalendar', format_string($linkedblueprint->name));
    $nextstepaction = '#local-coursecalendar-section-topics';
    $nextstepbutton = get_string('setupnext_addtopics_action', 'local_coursecalendar');
} else if (empty($coursecalendars)) {
    $nextsteptitle = get_string('setupnext_createcalendar_title', 'local_coursecalendar');
    $nextstepbody = get_string('setupnext_createcalendar_body', 'local_coursecalendar');
    $nextstepaction = '#local-coursecalendar-createcalendar';
    $nextstepbutton = get_string('setupnext_createcalendar_action', 'local_coursecalendar');
} else {
    $nextsteptitle = get_string('setupnext_opencalendar_title', 'local_coursecalendar');
    $nextstepbody = get_string('setupnext_opencalendar_body', 'local_coursecalendar');
    $nextstepaction = new moodle_url('/local/coursecalendar/calendar.php', [
        'id' => $courseid,
        'calendarid' => (int)$recommendedcalendar->id,
    ]);
    $nextstepbutton = get_string('setupnext_opencalendar_action', 'local_coursecalendar');
    $nextstepcomplete = true;
}

echo html_writer::start_div($nextstepcomplete
    ? 'local-coursecalendar-nextstep-card local-coursecalendar-nextstep-card--complete'
    : 'local-coursecalendar-nextstep-card');
echo html_writer::div(get_string('setupnext_label', 'local_coursecalendar'), 'local-coursecalendar-nextstep-label');
echo html_writer::tag('h3', $nextsteptitle, ['class' => 'local-coursecalendar-nextstep-title']);
echo html_writer::tag('p', $nextstepbody, ['class' => 'local-coursecalendar-nextstep-body']);
if ($nextstepcomplete && $recommendedcalendar && $recommendedreasonkey !== '') {
    echo html_writer::div(get_string($recommendedreasonkey, 'local_coursecalendar'), 'local-coursecalendar-recommendation-reason');
}
echo html_writer::link($nextstepaction, $nextstepbutton, [
    'class' => 'btn btn-primary local-coursecalendar-nextstep-action',
]);
echo html_writer::end_div();

if (!$hasblueprints) {
    echo $OUTPUT->heading(
        get_string('section_blueprintlibrary', 'local_coursecalendar')
        . ' ' . $OUTPUT->help_icon('section_blueprintlibrary', 'local_coursecalendar'),
        3,
        '',
        'local-coursecalendar-section-blueprints'
    );
    $rendercreateblueprintform(true);
    echo $OUTPUT->footer();
    return;
}

echo $OUTPUT->heading(
    get_string('section_linkcourse', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_linkcourse', 'local_coursecalendar'),
    3,
    '',
    'local-coursecalendar-section-linkcourse'
);
if ($linkedblueprint) {
    $linkmode = strtoupper((string)($linkrecord->linkmode ?? ''));
    echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-linked-blueprint-row']);
    echo html_writer::start_div('local-coursecalendar-blueprint-summary-main');
    echo html_writer::tag('span', format_string($linkedblueprint->name), ['class' => 'local-coursecalendar-blueprint-name']);
    if (!empty($linkedblueprint->shortcode)) {
        echo html_writer::tag(
            'span',
            format_string($linkedblueprint->shortcode),
            ['class' => 'local-coursecalendar-blueprint-shortcode']
        );
    }
    if ($linkedtopiccount !== null) {
        echo html_writer::tag(
            'span',
            get_string('blueprinttopiccount', 'local_coursecalendar', $linkedtopiccount),
            ['class' => 'local-coursecalendar-blueprint-shortcode']
        );
    }
    echo html_writer::tag(
        'span',
        get_string('courseblueprintlinkedbadge', 'local_coursecalendar'),
        ['class' => 'local-coursecalendar-badge local-coursecalendar-badge--active']
    );
    if ($linkmode === 'AUTO' && $linkrecord->linkconfidence !== null) {
        echo html_writer::tag(
            'span',
            get_string('courseblueprintautobadge', 'local_coursecalendar', (int)$linkrecord->linkconfidence),
            ['class' => 'local-coursecalendar-blueprint-shortcode']
        );
    }
    echo html_writer::end_div();

    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-row-action-form']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unlinkblueprint']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-outline-secondary btn-sm',
        'value' => get_string('unlinksubmit', 'local_coursecalendar'),
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div');
} else {
    echo $OUTPUT->notification(get_string('courselinknone', 'local_coursecalendar'), 'notifywarning');
}

if (!empty($activeblueprints) && !$linkedblueprint) {
    echo html_writer::start_tag(
        'form',
        ['method' => 'post', 'class' => 'local-coursecalendar-card', 'id' => 'local-coursecalendar-manuallink-form']
    );
    echo html_writer::tag('h4', get_string('manuallinkheading', 'local_coursecalendar')
        . ' ' . $OUTPUT->help_icon('manuallinkheading', 'local_coursecalendar'));
    if ($suggestedblueprintid > 0) {
        echo html_writer::tag('p', get_string('choseblueprintautohint', 'local_coursecalendar'), [
            'class' => 'local-coursecalendar-form-hint text-muted',
        ]);
    }
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'linkblueprint']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::start_div('mb-2');
    echo html_writer::tag(
        'label',
        get_string('blueprintlabel', 'local_coursecalendar'),
        ['for' => 'local-coursecalendar-blueprintid']
    );
    echo html_writer::start_tag(
        'select',
        ['id' => 'local-coursecalendar-blueprintid', 'name' => 'blueprintid', 'class' => 'custom-select']
    );
    foreach ($activeblueprints as $blueprint) {
        $attrs = ['value' => $blueprint->id];
        if ($linkedblueprint && (int)$linkedblueprint->id === (int)$blueprint->id) {
            $attrs['selected'] = 'selected';
        } else if (!$linkedblueprint && $suggestedblueprintid === (int)$blueprint->id) {
            $attrs['selected'] = 'selected';
        }
        echo html_writer::tag('option', format_string($blueprint->name), $attrs);
    }
    echo html_writer::end_tag('select');
    echo html_writer::end_div();

    echo html_writer::empty_tag(
        'input',
        ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('manuallinksubmit', 'local_coursecalendar')]
    );
    echo html_writer::end_tag('form');
}

echo $OUTPUT->heading(
    get_string('section_calendars', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_calendars', 'local_coursecalendar'),
    3,
    '',
    'local-coursecalendar-section-calendars'
);
if (!$linkedblueprint) {
    echo $OUTPUT->notification(get_string('calendarneedslink', 'local_coursecalendar'), 'notifywarning');
} else {
    $currentyear = (int)date('Y');
    $createcalendarhtml = '';
    if ($linkedtopiccount === 0) {
        echo $OUTPUT->notification(get_string('calendarneedstopics', 'local_coursecalendar'), 'notifyinfo');
    } else {
        ob_start();
        echo html_writer::start_tag('details', [
            'class' => 'local-coursecalendar-card local-coursecalendar-create-blueprint',
            'id' => 'local-coursecalendar-createcalendar',
        ]);
        echo html_writer::tag(
            'summary',
            get_string('createcalendarbutton', 'local_coursecalendar'),
            ['class' => 'local-coursecalendar-disclosure-summary local-coursecalendar-disclosure-summary--primary']
        );
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'id' => 'local-coursecalendar-createcalendar-form',
            'class' => 'local-coursecalendar-disclosure-body',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createcalendar']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blueprintid', 'value' => (int)$linkedblueprint->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blueprintctx', 'value' => $selectedblueprintid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicfilter', 'value' => $topicfilter]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        echo html_writer::start_div('mb-2');
        echo html_writer::tag(
            'label',
            get_string('calendaryearlabel', 'local_coursecalendar'),
            ['for' => 'local-coursecalendar-year-new']
        );
        echo html_writer::empty_tag('input', [
            'type' => 'number',
            'id' => 'local-coursecalendar-year-new',
            'name' => 'year',
            'class' => 'form-control',
            'min' => 2000,
            'max' => 2200,
            'value' => $currentyear,
            'required' => 'required',
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('mb-2');
        echo html_writer::tag(
            'label',
            get_string('calendarsemesterlabel', 'local_coursecalendar'),
            ['for' => 'local-coursecalendar-semester-new']
        );
        echo html_writer::start_tag(
            'select',
            ['id' => 'local-coursecalendar-semester-new', 'name' => 'semester', 'class' => 'custom-select']
        );
        foreach (local_coursecalendar_get_semesters() as $semesteroption) {
            echo html_writer::tag('option', $semesteroption, ['value' => $semesteroption]);
        }
        echo html_writer::end_tag('select');
        echo html_writer::end_div();

        echo html_writer::start_div('mb-2');
        echo html_writer::tag(
            'label',
            get_string('calendartitlelabel', 'local_coursecalendar'),
            ['for' => 'local-coursecalendar-title-new']
        );
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'id' => 'local-coursecalendar-title-new',
            'name' => 'title',
            'class' => 'form-control',
            'maxlength' => 255,
            'placeholder' => get_string('calendartitleplaceholder', 'local_coursecalendar'),
        ]);
        echo html_writer::end_div();

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'value' => get_string('createcalendarsubmit', 'local_coursecalendar'),
        ]);
        echo html_writer::end_tag('form');
        echo html_writer::end_tag('details');
        $createcalendarhtml = ob_get_clean();
    }

    if (empty($coursecalendars)) {
        echo $OUTPUT->notification(get_string('nocalendars', 'local_coursecalendar'), 'notifyinfo');
    } else {
        echo html_writer::start_tag('ul', ['class' => 'local-coursecalendar-blueprint-list']);
        foreach ($coursecalendars as $calendar) {
            $isactive = ((int)$calendar->isactive === 1);
            $badgekey = $isactive ? 'calendarbadgeactive' : 'calendarbadgeinactive';
            $badgeclass = $isactive ? 'local-coursecalendar-badge--active' : 'local-coursecalendar-badge--archived';
            $isrecommended = $recommendedcalendar && (int)$recommendedcalendar->id === (int)$calendar->id;
            $heading = s((string)$calendar->semester) . ' ' . (int)$calendar->year;
            if (!empty($calendar->title)) {
                $heading .= ' - ' . format_string($calendar->title);
            }

            echo html_writer::start_tag('li', ['class' => 'local-coursecalendar-blueprint-item']);
            echo html_writer::start_tag('details', ['class' => 'local-coursecalendar-blueprint-details']);

            $builderurl = new moodle_url(
                '/local/coursecalendar/calendar.php',
                ['id' => $courseid, 'calendarid' => (int)$calendar->id]
            );
            echo html_writer::start_tag('summary', ['class' => 'local-coursecalendar-blueprint-summary']);
            echo html_writer::start_div('local-coursecalendar-blueprint-summary-main');
            echo html_writer::tag('span', $heading, ['class' => 'local-coursecalendar-blueprint-name']);
            echo html_writer::tag(
                'span',
                get_string($badgekey, 'local_coursecalendar'),
                ['class' => 'local-coursecalendar-badge ' . $badgeclass]
            );
            if ($isrecommended) {
                echo html_writer::tag(
                    'span',
                    get_string('calendarrecommendedbadge', 'local_coursecalendar'),
                    ['class' => 'local-coursecalendar-badge local-coursecalendar-badge--recommended']
                );
            }
            echo html_writer::end_div();
            echo html_writer::link($builderurl, get_string('opencalendarbuilderprominent', 'local_coursecalendar'), [
                'class' => 'btn btn-primary local-coursecalendar-open-builder',
                'onclick' => 'event.stopPropagation();',
            ]);
            echo html_writer::tag('span', get_string('editcalendarbutton', 'local_coursecalendar'), [
                'class' => 'btn btn-outline-secondary btn-sm local-coursecalendar-edit-indicator',
                'aria-hidden' => 'true',
            ]);
            echo html_writer::end_tag('summary');

            echo html_writer::start_div('local-coursecalendar-disclosure-body');

            echo html_writer::start_tag('form', ['method' => 'post']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => (int)$calendar->id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'updatecalendar']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blueprintctx', 'value' => $selectedblueprintid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicfilter', 'value' => $topicfilter]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

            echo html_writer::start_div('mb-2');
            echo html_writer::tag('label', get_string('calendartitlelabel', 'local_coursecalendar'));
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'title',
                'class' => 'form-control',
                'maxlength' => 255,
                'value' => s((string)$calendar->title),
            ]);
            echo html_writer::end_div();
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => 'btn btn-secondary',
                'value' => get_string('savecalendarsubmit', 'local_coursecalendar'),
            ]);
            echo html_writer::end_tag('form');

            echo html_writer::start_div('local-coursecalendar-inline-controls');
            $calendaractions = [
                'togglecalendaractive' => 'togglecalendarsubmit',
                'deletecalendar' => 'deletecalendarsubmit',
            ];
            foreach ($calendaractions as $calendaraction => $labelkey) {
                echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-inline-form']);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'calendarid', 'value' => (int)$calendar->id]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $calendaraction]);
                echo html_writer::empty_tag(
                    'input',
                    ['type' => 'hidden', 'name' => 'blueprintctx', 'value' => $selectedblueprintid]
                );
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicfilter', 'value' => $topicfilter]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                $buttonclass = ($calendaraction === 'deletecalendar') ? 'btn btn-outline-danger' : 'btn btn-outline-secondary';
                echo html_writer::empty_tag(
                    'input',
                    ['type' => 'submit', 'class' => $buttonclass, 'value' => get_string($labelkey, 'local_coursecalendar')]
                );
                echo html_writer::end_tag('form');
            }
            echo html_writer::end_div();

            echo html_writer::end_div();
            echo html_writer::end_tag('details');
            echo html_writer::end_tag('li');
        }
        echo html_writer::end_tag('ul');
    }
    echo $createcalendarhtml;
}

echo $OUTPUT->heading(
    get_string('section_blueprintlibrary', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_blueprintlibrary', 'local_coursecalendar'),
    3,
    '',
    'local-coursecalendar-section-blueprints'
);

if (empty($allblueprints)) {
    echo $OUTPUT->notification(get_string('noblueprints', 'local_coursecalendar'), 'notifyinfo');
} else {
    echo html_writer::start_tag('ul', ['class' => 'local-coursecalendar-blueprint-list']);
    foreach ($allblueprints as $blueprint) {
        $isarchived = (int)$blueprint->isarchived === 1;
        $statuskey = $isarchived ? 'blueprintstatusarchived' : 'blueprintstatusactive';
        $statusclass = $isarchived ? 'local-coursecalendar-badge--archived' : 'local-coursecalendar-badge--active';

        echo html_writer::start_tag('li', ['class' => 'local-coursecalendar-blueprint-item']);
        echo html_writer::start_tag('details', ['class' => 'local-coursecalendar-blueprint-details']);

        echo html_writer::start_tag('summary', ['class' => 'local-coursecalendar-blueprint-summary']);
        echo html_writer::start_div('local-coursecalendar-blueprint-summary-main');
        echo html_writer::tag('span', format_string($blueprint->name), ['class' => 'local-coursecalendar-blueprint-name']);
        if (!empty($blueprint->shortcode)) {
            echo html_writer::tag(
                'span',
                format_string($blueprint->shortcode),
                ['class' => 'local-coursecalendar-blueprint-shortcode']
            );
        }
        $blueprinttopiccount = $DB->count_records('local_coursecalendar_blueprint_topics', [
            'blueprintid' => (int)$blueprint->id,
            'isactive' => 1,
        ]);
        echo html_writer::tag(
            'span',
            get_string('blueprinttopiccount', 'local_coursecalendar', $blueprinttopiccount),
            ['class' => 'local-coursecalendar-blueprint-shortcode']
        );
        echo html_writer::tag(
            'span',
            get_string($statuskey, 'local_coursecalendar'),
            ['class' => 'local-coursecalendar-badge ' . $statusclass]
        );
        echo html_writer::end_div();
        echo html_writer::tag('span', get_string('editblueprintbutton', 'local_coursecalendar'), [
            'class' => 'btn btn-outline-secondary btn-sm local-coursecalendar-edit-indicator',
            'aria-hidden' => 'true',
        ]);
        echo html_writer::end_tag('summary');

        echo html_writer::start_div('local-coursecalendar-disclosure-body');

        echo html_writer::start_tag('form', ['method' => 'post']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blueprintid', 'value' => $blueprint->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('blueprintnamelabel', 'local_coursecalendar'));
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'name',
            'class' => 'form-control',
            'required' => 'required',
            'value' => s((string)$blueprint->name),
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('blueprintshortcodelabel', 'local_coursecalendar'));
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'shortcode',
            'maxlength' => 32,
            'class' => 'form-control',
            'value' => s((string)$blueprint->shortcode),
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('blueprintdescriptionlabel', 'local_coursecalendar'));
        echo html_writer::tag('textarea', s((string)$blueprint->description), [
            'name' => 'description',
            'rows' => 2,
            'class' => 'form-control',
        ]);
        echo html_writer::end_div();

        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'updateblueprint']);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-secondary mr-2',
            'value' => get_string('saveblueprintsubmit', 'local_coursecalendar'),
        ]);
        echo html_writer::end_tag('form');

        echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-inline-form']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blueprintid', 'value' => $blueprint->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'togglearchive']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $togglelabel = $isarchived
            ? get_string('unarchiveblueprintsubmit', 'local_coursecalendar')
            : get_string('archiveblueprintsubmit', 'local_coursecalendar');
        echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-outline-secondary', 'value' => $togglelabel]);
        echo html_writer::end_tag('form');

        echo html_writer::end_div();
        echo html_writer::end_tag('details');
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}
$rendercreateblueprintform(false);

if (!$linkedblueprint) {
    echo $OUTPUT->footer();
    return;
}

echo $OUTPUT->heading(
    get_string('section_topics', 'local_coursecalendar')
    . ' ' . $OUTPUT->help_icon('section_topics', 'local_coursecalendar'),
    3,
    '',
    'local-coursecalendar-section-topics'
);
if (!$selectedblueprint) {
    echo $OUTPUT->notification(get_string('notopicswithoutblueprint', 'local_coursecalendar'), 'notifyinfo');
    echo $OUTPUT->footer();
    return;
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/coursecalendar/manage.php', [], 'local-coursecalendar-section-topics'))->out(false),
    'class' => 'local-coursecalendar-card local-coursecalendar-inline-controls',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::start_div('mb-2');
echo html_writer::tag(
    'label',
    get_string('topicblueprintcontextlabel', 'local_coursecalendar'),
    ['for' => 'local-coursecalendar-blueprintctx']
);
echo html_writer::start_tag(
    'select',
    ['id' => 'local-coursecalendar-blueprintctx', 'name' => 'blueprintctx', 'class' => 'custom-select']
);
foreach ($allblueprints as $blueprint) {
    $attrs = ['value' => $blueprint->id];
    if ((int)$blueprint->id === (int)$selectedblueprint->id) {
        $attrs['selected'] = 'selected';
    }
    $label = format_string($blueprint->name);
    if ((int)$blueprint->isarchived === 1) {
        $label .= ' [' . get_string('archivedshort', 'local_coursecalendar') . ']';
    }
    echo html_writer::tag('option', $label, $attrs);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag(
    'label',
    get_string('topicfilterlabel', 'local_coursecalendar'),
    ['for' => 'local-coursecalendar-topicfilter']
);
echo html_writer::start_tag(
    'select',
    ['id' => 'local-coursecalendar-topicfilter', 'name' => 'topicfilter', 'class' => 'custom-select']
);
$filteroptions = array_merge(['ALL'], local_coursecalendar_get_topic_types());
foreach ($filteroptions as $filteroption) {
    $attrs = ['value' => $filteroption];
    if ($topicfilter === $filteroption) {
        $attrs['selected'] = 'selected';
    }
    $label = ($filteroption === 'ALL') ? get_string('topicfilterall', 'local_coursecalendar') : $filteroption;
    echo html_writer::tag('option', $label, $attrs);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();
echo html_writer::empty_tag(
    'input',
    ['type' => 'submit', 'class' => 'btn btn-secondary', 'value' => get_string('applyfilter', 'local_coursecalendar')]
);
echo html_writer::end_tag('form');

$importurl = new moodle_url('/local/coursecalendar/import_topics.php', [
    'id' => $courseid,
    'blueprintid' => (int)$selectedblueprint->id,
]);
echo html_writer::start_tag('div', ['class' => 'local-coursecalendar-page-actions mb-2']);
echo html_writer::link(
    $importurl,
    get_string('importtopicslink', 'local_coursecalendar'),
    ['class' => 'btn btn-sm btn-outline-primary mr-2']
);
echo html_writer::end_tag('div');

$createtopichtml = '';
ob_start();
echo html_writer::start_tag('details', [
    'class' => 'local-coursecalendar-card local-coursecalendar-create-blueprint',
    'id' => 'local-coursecalendar-createtopic',
]);
echo html_writer::tag(
    'summary',
    get_string('createtopicbutton', 'local_coursecalendar'),
    ['class' => 'local-coursecalendar-disclosure-summary local-coursecalendar-disclosure-summary--primary']
);
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-disclosure-body']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createtopic']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blueprintid', 'value' => (int)$selectedblueprint->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blueprintctx', 'value' => (int)$selectedblueprint->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicfilter', 'value' => $topicfilter]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('mb-2');
echo html_writer::tag(
    'label',
    get_string('topictitlelabel', 'local_coursecalendar'),
    ['for' => 'local-coursecalendar-topictitle-new']
);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'local-coursecalendar-topictitle-new',
    'name' => 'title',
    'class' => 'form-control',
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag(
    'label',
    get_string('topictypelabel', 'local_coursecalendar'),
    ['for' => 'local-coursecalendar-topictype-new']
);
echo html_writer::start_tag('select', ['id' => 'local-coursecalendar-topictype-new', 'name' => 'type', 'class' => 'custom-select']);
foreach (local_coursecalendar_get_topic_types() as $topictype) {
    echo html_writer::tag('option', $topictype, ['value' => $topictype]);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag(
    'label',
    get_string('topiccontentlabel', 'local_coursecalendar'),
    ['for' => 'local-coursecalendar-topiccontent-new']
);
echo html_writer::tag('textarea', '', [
    'id' => 'local-coursecalendar-topiccontent-new',
    'name' => 'contenthtml',
    'rows' => 8,
    'class' => 'form-control',
]);
$topiceditor->use_editor('local-coursecalendar-topiccontent-new', $topiceditoroptions);
echo html_writer::end_div();
echo html_writer::empty_tag(
    'input',
    ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('createtopicsubmit', 'local_coursecalendar')]
);
echo html_writer::end_tag('form');
echo html_writer::end_tag('details');
$createtopichtml = ob_get_clean();

if (empty($topics)) {
    echo $OUTPUT->notification(get_string('notopicsfound', 'local_coursecalendar'), 'notifyinfo');
} else {
    echo html_writer::start_tag('ul', [
        'class' => 'local-coursecalendar-blueprint-list local-coursecalendar-topic-list',
        'id' => 'local-coursecalendar-topiclist',
    ]);
    foreach ($topics as $topic) {
        $isactive = ((int)$topic->isactive === 1);
        $badgekey = $isactive ? 'topicstatusactive' : 'topicstatusinactive';
        $badgeclass = $isactive ? 'local-coursecalendar-badge--active' : 'local-coursecalendar-badge--archived';

        echo html_writer::start_tag('li', [
            'class' => 'local-coursecalendar-blueprint-item local-coursecalendar-topic-item',
            'data-topicid' => (int)$topic->id,
        ]);
        echo html_writer::start_tag('details', ['class' => 'local-coursecalendar-blueprint-details']);

        echo html_writer::start_tag('summary', ['class' => 'local-coursecalendar-blueprint-summary']);
        echo html_writer::tag('span', '⋮⋮', [
            'class' => 'local-coursecalendar-drag-handle',
            'aria-hidden' => 'true',
            'title' => get_string('topicdraghandle', 'local_coursecalendar'),
        ]);
        echo html_writer::start_div('local-coursecalendar-blueprint-summary-main');
        echo html_writer::tag('span', (int)$topic->sortorder, ['class' => 'local-coursecalendar-blueprint-shortcode']);
        echo html_writer::tag('span', format_string($topic->title), ['class' => 'local-coursecalendar-blueprint-name']);
        echo html_writer::tag(
            'span',
            s($topic->type),
            ['class' => 'local-coursecalendar-type-badge local-coursecalendar-type-' . strtolower($topic->type)]
        );
        echo html_writer::tag(
            'span',
            get_string($badgekey, 'local_coursecalendar'),
            ['class' => 'local-coursecalendar-badge ' . $badgeclass]
        );
        echo html_writer::end_div();
        echo html_writer::tag('span', get_string('edittopicbutton', 'local_coursecalendar'), [
            'class' => 'btn btn-outline-secondary btn-sm local-coursecalendar-edit-indicator',
            'aria-hidden' => 'true',
        ]);
        echo html_writer::end_tag('summary');

        echo html_writer::start_div('local-coursecalendar-disclosure-body');

        echo html_writer::start_tag('form', ['method' => 'post']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicid', 'value' => (int)$topic->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'updatetopic']);
        echo html_writer::empty_tag(
            'input',
            ['type' => 'hidden', 'name' => 'blueprintctx', 'value' => (int)$selectedblueprint->id]
        );
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicfilter', 'value' => $topicfilter]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('topictitlelabel', 'local_coursecalendar'));
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'title',
            'class' => 'form-control',
            'required' => 'required',
            'value' => s((string)$topic->title),
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('topictypelabel', 'local_coursecalendar'));
        echo html_writer::start_tag('select', ['name' => 'type', 'class' => 'custom-select']);
        foreach (local_coursecalendar_get_topic_types() as $topictype) {
            $attrs = ['value' => $topictype];
            if ($topic->type === $topictype) {
                $attrs['selected'] = 'selected';
            }
            echo html_writer::tag('option', $topictype, $attrs);
        }
        echo html_writer::end_tag('select');
        echo html_writer::end_div();

        $topiccontentid = 'local-coursecalendar-topiccontent-' . (int)$topic->id;
        echo html_writer::start_div('mb-2');
        echo html_writer::tag('label', get_string('topiccontentlabel', 'local_coursecalendar'), ['for' => $topiccontentid]);
        echo html_writer::tag('textarea', s((string)$topic->contenthtml), [
            'id' => $topiccontentid,
            'name' => 'contenthtml',
            'rows' => 8,
            'class' => 'form-control',
        ]);
        $topiceditor->use_editor($topiccontentid, $topiceditoroptions);
        echo html_writer::end_div();

        echo html_writer::empty_tag(
            'input',
            ['type' => 'submit', 'class' => 'btn btn-secondary', 'value' => get_string('savetopicsubmit', 'local_coursecalendar')]
        );
        echo html_writer::end_tag('form');

        echo html_writer::start_div('local-coursecalendar-inline-controls');
        foreach (['toggletopicactive' => 'toggletopicsubmit', 'deletetopic' => 'deletetopicsubmit'] as $topicaction => $labelkey) {
            echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'local-coursecalendar-inline-form']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicid', 'value' => (int)$topic->id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $topicaction]);
            echo html_writer::empty_tag(
                'input',
                ['type' => 'hidden', 'name' => 'blueprintctx', 'value' => (int)$selectedblueprint->id]
            );
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicfilter', 'value' => $topicfilter]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $buttonclass = ($topicaction === 'deletetopic') ? 'btn btn-outline-danger' : 'btn btn-outline-secondary';
            echo html_writer::empty_tag(
                'input',
                ['type' => 'submit', 'class' => $buttonclass, 'value' => get_string($labelkey, 'local_coursecalendar')]
            );
            echo html_writer::end_tag('form');
        }
        echo html_writer::end_div();

        echo html_writer::end_div();
        echo html_writer::end_tag('details');
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}
echo $createtopichtml;

$tourid = local_coursecalendar_get_tour_id_by_name('local_coursecalendar_setup');
$PAGE->requires->js_call_amd('local_coursecalendar/showtour', 'init', [
    $tourid,
    '#local-coursecalendar-showtour',
]);

if ($selectedblueprint && !empty($topics)) {
    $PAGE->requires->js_call_amd('local_coursecalendar/topicreorder', 'init', [
        (int)$courseid,
        (int)$selectedblueprint->id,
        '#local-coursecalendar-topiclist',
    ]);
    $PAGE->requires->strings_for_js(['topicreordersaved'], 'local_coursecalendar');
}

echo $OUTPUT->footer();
