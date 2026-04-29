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
 * Helper library.
 *
 * @package    local_coursecalendar
 * @copyright  2026 Greg Mulcair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return all blueprints owned by a teacher.
 *
 * @param int $userid
 * @param bool $includearchived
 * @return array
 */
function local_coursecalendar_get_teacher_blueprints(int $userid, bool $includearchived = true): array {
    global $DB;

    $conditions = ['owneruserid' => $userid];
    if (!$includearchived) {
        $conditions['isarchived'] = 0;
    }

    return $DB->get_records(
        'local_coursecalendar_blueprints',
        $conditions,
        'isarchived ASC, name ASC, id ASC'
    );
}

/**
 * Return blueprint link record for a course.
 *
 * @param int $courseid
 * @return stdClass|null
 */
function local_coursecalendar_get_course_link_record(int $courseid): ?stdClass {
    global $DB;

    $record = $DB->get_record('local_coursecalendar_course_blueprint_link', ['courseid' => $courseid], '*', IGNORE_MISSING);
    return $record ?: null;
}

/**
 * Ensure a blueprint belongs to this user.
 *
 * @param int $blueprintid
 * @param int $userid
 * @return stdClass
 */
function local_coursecalendar_require_owned_blueprint(int $blueprintid, int $userid): stdClass {
    global $DB;

    $blueprint = $DB->get_record('local_coursecalendar_blueprints', ['id' => $blueprintid], '*', MUST_EXIST);
    if ((int)$blueprint->owneruserid !== $userid) {
        throw new moodle_exception('invalidblueprintownership', 'local_coursecalendar');
    }

    return $blueprint;
}

/**
 * Insert/update the one-blueprint-per-course link.
 *
 * @param int $courseid
 * @param int $blueprintid
 * @param string $mode
 * @param int|null $confidence
 * @param string $notes
 * @param int $userid
 * @return void
 */
function local_coursecalendar_upsert_course_blueprint_link(
    int $courseid,
    int $blueprintid,
    string $mode,
    ?int $confidence,
    string $notes,
    int $userid
): void {
    global $DB;

    $now = time();
    $existing = local_coursecalendar_get_course_link_record($courseid);
    if ($existing) {
        $existing->blueprintid = $blueprintid;
        $existing->linkmode = $mode;
        $existing->linkconfidence = $confidence;
        $existing->linknotes = $notes;
        $existing->timemodified = $now;
        $existing->usermodified = $userid;
        $DB->update_record('local_coursecalendar_course_blueprint_link', $existing);
        return;
    }

    $record = new stdClass();
    $record->courseid = $courseid;
    $record->blueprintid = $blueprintid;
    $record->linkmode = $mode;
    $record->linkconfidence = $confidence;
    $record->linknotes = $notes;
    $record->timecreated = $now;
    $record->timemodified = $now;
    $record->usermodified = $userid;
    $DB->insert_record('local_coursecalendar_course_blueprint_link', $record);
}

/**
 * Return a best-effort auto-link suggestion for this course.
 *
 * @param stdClass $course
 * @param int $userid
 * @return array|null
 */
function local_coursecalendar_get_autolink_suggestion(stdClass $course, int $userid): ?array {
    $blueprints = local_coursecalendar_get_teacher_blueprints($userid, false);
    if (empty($blueprints)) {
        return null;
    }

    $coursematchtext = local_coursecalendar_get_course_match_text($course);
    $scored = [];
    foreach ($blueprints as $blueprint) {
        $score = local_coursecalendar_score_blueprint_match($blueprint, $coursematchtext);
        if ($score <= 0) {
            continue;
        }

        $scored[] = [
            'blueprint' => $blueprint,
            'confidence' => $score,
        ];
    }

    if (empty($scored)) {
        return null;
    }

    usort($scored, static function (array $a, array $b): int {
        return $b['confidence'] <=> $a['confidence'];
    });

    $best = $scored[0];
    if ($best['confidence'] < 40) {
        return null;
    }

    $ambiguous = false;
    if (count($scored) > 1) {
        $gap = $best['confidence'] - $scored[1]['confidence'];
        $ambiguous = $gap < 15;
    }

    return [
        'best' => $best,
        'ambiguous' => $ambiguous,
        'candidates' => array_slice($scored, 0, 3),
    ];
}

/**
 * Build comparison text from course metadata.
 *
 * @param stdClass $course
 * @return string
 */
function local_coursecalendar_get_course_match_text(stdClass $course): string {
    $parts = [
        (string)$course->shortname,
        (string)$course->idnumber,
        (string)$course->fullname,
    ];

    if (!empty($course->category)) {
        $category = core_course_category::get((int)$course->category, IGNORE_MISSING);
        if ($category) {
            $parts[] = $category->get_nested_name(false);
        }
    }

    return core_text::strtoupper(trim(implode(' ', $parts)));
}

/**
 * Score how likely a blueprint matches course metadata.
 *
 * @param stdClass $blueprint
 * @param string $coursematchtext
 * @return int
 */
function local_coursecalendar_score_blueprint_match(stdClass $blueprint, string $coursematchtext): int {
    $score = 0;

    $shortcode = trim((string)$blueprint->shortcode);
    if ($shortcode !== '') {
        $token = preg_quote(core_text::strtoupper($shortcode), '/');
        if (preg_match('/\b' . $token . '\b/u', $coursematchtext)) {
            $score += 70;
        } else if (str_contains($coursematchtext, core_text::strtoupper($shortcode))) {
            $score += 50;
        }
    }

    $name = trim((string)$blueprint->name);
    if ($name !== '' && str_contains($coursematchtext, core_text::strtoupper($name))) {
        $score += 40;
    }

    return min($score, 100);
}

/**
 * Valid topic types for blueprint topics.
 *
 * @return string[]
 */
function local_coursecalendar_get_topic_types(): array {
    return ['LECTURE', 'LAB', 'ELESSON', 'TEST', 'HOMEWORK'];
}

/**
 * Validate and normalize topic type.
 *
 * @param string $type
 * @return string
 */
function local_coursecalendar_normalise_topic_type(string $type): string {
    $type = core_text::strtoupper(trim($type));
    if (!in_array($type, local_coursecalendar_get_topic_types(), true)) {
        throw new moodle_exception('invalidtopictype', 'local_coursecalendar');
    }
    return $type;
}

/**
 * Get ordered topics for a blueprint.
 *
 * @param int $blueprintid
 * @param bool $includeinactive
 * @return array
 */
function local_coursecalendar_get_blueprint_topics(int $blueprintid, bool $includeinactive = true): array {
    global $DB;

    $conditions = ['blueprintid' => $blueprintid];
    if (!$includeinactive) {
        $conditions['isactive'] = 1;
    }

    return $DB->get_records(
        'local_coursecalendar_blueprint_topics',
        $conditions,
        'sortorder ASC, id ASC'
    );
}

/**
 * Ensure topic belongs to a blueprint owned by the user.
 *
 * @param int $topicid
 * @param int $userid
 * @return stdClass
 */
function local_coursecalendar_require_owned_topic(int $topicid, int $userid): stdClass {
    global $DB;

    $topic = $DB->get_record('local_coursecalendar_blueprint_topics', ['id' => $topicid], '*', MUST_EXIST);
    local_coursecalendar_require_owned_blueprint((int)$topic->blueprintid, $userid);
    return $topic;
}

/**
 * Keep sortorder contiguous for a blueprint.
 *
 * @param int $blueprintid
 * @return void
 */
function local_coursecalendar_normalise_topic_sortorder(int $blueprintid): void {
    global $DB;

    $topics = local_coursecalendar_get_blueprint_topics($blueprintid, true);
    $changed = [];
    $sort = 1;
    foreach ($topics as $topic) {
        if ((int)$topic->sortorder !== $sort) {
            $changed[] = [
                'topic' => $topic,
                'sortorder' => $sort,
            ];
        }
        $sort++;
    }

    if (empty($changed)) {
        return;
    }

    foreach ($changed as $index => $item) {
        $item['topic']->sortorder = 100000 + $index;
        $DB->update_record('local_coursecalendar_blueprint_topics', $item['topic']);
    }

    foreach ($changed as $item) {
        $item['topic']->sortorder = $item['sortorder'];
        $DB->update_record('local_coursecalendar_blueprint_topics', $item['topic']);
    }
}

/**
 * Move topic by one step within blueprint ordering.
 *
 * @param stdClass $topic
 * @param int $direction -1 for up, +1 for down
 * @return bool
 */
function local_coursecalendar_move_topic(stdClass $topic, int $direction): bool {
    global $DB;

    $topics = array_values(local_coursecalendar_get_blueprint_topics((int)$topic->blueprintid, true));
    $index = null;
    foreach ($topics as $i => $item) {
        if ((int)$item->id === (int)$topic->id) {
            $index = $i;
            break;
        }
    }

    if ($index === null) {
        return false;
    }

    $targetindex = $index + $direction;
    if ($targetindex < 0 || $targetindex >= count($topics)) {
        return false;
    }

    $tmp = $topics[$index];
    $topics[$index] = $topics[$targetindex];
    $topics[$targetindex] = $tmp;

    $changed = [];
    $sort = 1;
    foreach ($topics as $item) {
        if ((int)$item->sortorder !== $sort) {
            $changed[] = [
                'topic' => $item,
                'sortorder' => $sort,
            ];
        }
        $sort++;
    }

    if (empty($changed)) {
        return true;
    }

    foreach ($changed as $i => $item) {
        $item['topic']->sortorder = 100000 + $i;
        $DB->update_record('local_coursecalendar_blueprint_topics', $item['topic']);
    }

    foreach ($changed as $item) {
        $item['topic']->sortorder = $item['sortorder'];
        $DB->update_record('local_coursecalendar_blueprint_topics', $item['topic']);
    }

    return true;
}

/**
 * Return calendar usage rows for a topic.
 *
 * @param int $topicid
 * @return array
 */
function local_coursecalendar_get_topic_usage_rows(int $topicid): array {
    global $DB;

    $sql = "SELECT sc.id, sc.courseid, sc.year, sc.semester, sc.title
              FROM {local_coursecalendar_calendar_blocks} cb
              JOIN {local_coursecalendar_semester_calendars} sc
                ON sc.id = cb.calendarid
             WHERE cb.topicid = :topicid
          GROUP BY sc.id, sc.courseid, sc.year, sc.semester, sc.title
          ORDER BY sc.year DESC, sc.semester ASC, sc.id ASC";

    return $DB->get_records_sql($sql, ['topicid' => $topicid]);
}

/**
 * Supported semester values.
 *
 * @return string[]
 */
function local_coursecalendar_get_semesters(): array {
    return ['FALL', 'WINTER', 'SUMMER'];
}

/**
 * Validate and normalize semester value.
 *
 * @param string $semester
 * @return string
 */
function local_coursecalendar_normalise_semester(string $semester): string {
    $semester = core_text::strtoupper(trim($semester));
    if (!in_array($semester, local_coursecalendar_get_semesters(), true)) {
        throw new moodle_exception('invalidsemester', 'local_coursecalendar');
    }
    return $semester;
}

/**
 * Return all semester calendars for a course.
 *
 * @param int $courseid
 * @return array
 */
function local_coursecalendar_get_course_calendars(int $courseid): array {
    global $DB;

    return $DB->get_records(
        'local_coursecalendar_semester_calendars',
        ['courseid' => $courseid],
        'year DESC, semester ASC, id DESC'
    );
}

/**
 * Require a semester calendar for this course.
 *
 * @param int $calendarid
 * @param int $courseid
 * @return stdClass
 */
function local_coursecalendar_require_course_calendar(int $calendarid, int $courseid): stdClass {
    global $DB;

    $calendar = $DB->get_record('local_coursecalendar_semester_calendars', ['id' => $calendarid], '*', MUST_EXIST);
    if ((int)$calendar->courseid !== $courseid) {
        throw new moodle_exception('invalidcalendarcontext', 'local_coursecalendar');
    }

    return $calendar;
}

/**
 * Get calendar blocks indexed by row/col.
 *
 * @param int $calendarid
 * @return array
 */
function local_coursecalendar_get_blocks_map(int $calendarid): array {
    global $DB;

    $records = $DB->get_records(
        'local_coursecalendar_calendar_blocks',
        ['calendarid' => $calendarid],
        'rownum ASC, colnum ASC, id ASC'
    );
    $map = [];
    foreach ($records as $record) {
        $row = (int)$record->rownum;
        $col = (int)$record->colnum;
        if (!isset($map[$row])) {
            $map[$row] = [];
        }
        $map[$row][$col] = $record;
    }
    return $map;
}

/**
 * Upsert a calendar block.
 *
 * @param int $calendarid
 * @param int $rownum
 * @param int $colnum
 * @param string $blocktype
 * @param string $contenthtml
 * @param int $userid
 * @param string|null $headerday
 * @param string|null $headermode
 * @param int|null $topicid
 * @param string|null $cellheading
 * @param int $highlighted
 * @param int $verticallycentred
 * @return void
 */
function local_coursecalendar_upsert_block(
    int $calendarid,
    int $rownum,
    int $colnum,
    string $blocktype,
    string $contenthtml,
    int $userid,
    ?string $headerday = null,
    ?string $headermode = null,
    ?int $topicid = null,
    ?string $cellheading = null,
    int $highlighted = 0,
    int $verticallycentred = 0
): void {
    global $DB;

    $now = time();
    $record = $DB->get_record('local_coursecalendar_calendar_blocks', [
        'calendarid' => $calendarid,
        'rownum' => $rownum,
        'colnum' => $colnum,
    ], '*', IGNORE_MISSING);

    if ($record) {
        $record->blocktype = $blocktype;
        $record->contenthtml = $contenthtml;
        $record->headerday = $headerday;
        $record->headermode = $headermode;
        $record->topicid = $topicid;
        $record->cellheading = $cellheading;
        $record->highlighted = $highlighted;
        $record->verticallycentred = $verticallycentred;
        $record->timemodified = $now;
        $record->usermodified = $userid;
        $DB->update_record('local_coursecalendar_calendar_blocks', $record);
        return;
    }

    $insert = (object)[
        'calendarid' => $calendarid,
        'rownum' => $rownum,
        'colnum' => $colnum,
        'blocktype' => $blocktype,
        'topicid' => $topicid,
        'contenthtml' => $contenthtml,
        'cellheading' => $cellheading,
        'headerday' => $headerday,
        'headermode' => $headermode,
        'highlighted' => $highlighted,
        'verticallycentred' => $verticallycentred,
        'generatedbyrule' => 0,
        'generatedruleid' => null,
        'timecreated' => $now,
        'timemodified' => $now,
        'usermodified' => $userid,
    ];
    $DB->insert_record('local_coursecalendar_calendar_blocks', $insert);
}

/**
 * Ensure base header row exists for a calendar.
 *
 * @param int $calendarid
 * @param int $userid
 * @return void
 */
function local_coursecalendar_ensure_base_grid(int $calendarid, int $userid): void {
    global $DB;

    $defaults = [
        0 => ['content' => 'Week # / Week of', 'day' => null, 'mode' => null],
        1 => ['content' => 'Day A', 'day' => 'Monday', 'mode' => 'Lecture'],
        2 => ['content' => 'Day B', 'day' => 'Wednesday', 'mode' => 'Lecture'],
        3 => ['content' => 'Day C', 'day' => 'Friday', 'mode' => 'Lecture'],
        4 => ['content' => 'Assignments and Problem Sets', 'day' => null, 'mode' => null],
    ];

    foreach ($defaults as $col => $config) {
        $existing = $DB->get_record('local_coursecalendar_calendar_blocks', [
            'calendarid' => $calendarid,
            'rownum' => 0,
            'colnum' => $col,
        ], 'id', IGNORE_MISSING);
        if ($existing) {
            continue;
        }
        local_coursecalendar_upsert_block(
            $calendarid,
            0,
            $col,
            'HEADER',
            $config['content'],
            $userid,
            $config['day'],
            $config['mode']
        );
    }
}

/**
 * Append a new week row with a default week label.
 *
 * @param int $calendarid
 * @param int $userid
 * @return int
 */
function local_coursecalendar_add_week_row(int $calendarid, int $userid): int {
    global $DB;

    $maxrow = (int)$DB->get_field_sql(
        'SELECT COALESCE(MAX(rownum), 0) FROM {local_coursecalendar_calendar_blocks} WHERE calendarid = :calendarid',
        ['calendarid' => $calendarid]
    );
    $newrow = max(1, $maxrow + 1);
    local_coursecalendar_upsert_block($calendarid, $newrow, 0, 'TEXT', 'Week ' . $newrow, $userid);
    return $newrow;
}

/**
 * Remove last week row and all its blocks.
 *
 * @param int $calendarid
 * @return bool
 */
function local_coursecalendar_remove_last_week_row(int $calendarid): bool {
    global $DB;

    $maxrow = (int)$DB->get_field_sql(
        'SELECT COALESCE(MAX(rownum), 0) FROM {local_coursecalendar_calendar_blocks} WHERE calendarid = :calendarid',
        ['calendarid' => $calendarid]
    );

    if ($maxrow <= 0) {
        return false;
    }

    $DB->delete_records('local_coursecalendar_calendar_blocks', [
        'calendarid' => $calendarid,
        'rownum' => $maxrow,
    ]);
    return true;
}

// Timeline exception rules.

/**
 * Return the allowed rule type identifiers.
 *
 * @return string[]
 */
function local_coursecalendar_get_rule_types(): array {
    return ['SEMESTER_START', 'SEMESTER_END', 'NO_CLASS', 'DAY_SWAP', 'OTHER'];
}

/**
 * Fetch rules attached to a calendar.
 *
 * @param int $calendarid Calendar record ID.
 * @param bool $activeonly If true, only return rules with isactive=1.
 * @return array Ordered rule records.
 */
function local_coursecalendar_get_calendar_rules(int $calendarid, bool $activeonly = false): array {
    global $DB;
    $conditions = ['calendarid' => $calendarid];
    if ($activeonly) {
        $conditions['isactive'] = 1;
    }
    return $DB->get_records(
        'local_coursecalendar_timeline_exception_rules',
        $conditions,
        'ruledate ASC, sortorder ASC, id ASC'
    );
}

/**
 * Create a new timeline exception rule.
 *
 * @param int $calendarid Calendar the rule belongs to.
 * @param string $ruletype One of the values returned by {@see local_coursecalendar_get_rule_types()}.
 * @param int $ruledate Epoch timestamp for the rule date.
 * @param string $label Short label shown in the UI.
 * @param string $description Longer description.
 * @param string|null $fromday Day-swap source day (optional).
 * @param string|null $today Day-swap target day (optional).
 * @param int $userid User creating the rule.
 * @return int ID of the inserted rule.
 */
function local_coursecalendar_create_rule(
    int $calendarid,
    string $ruletype,
    int $ruledate,
    string $label,
    string $description,
    ?string $fromday,
    ?string $today,
    int $userid
): int {
    global $DB;
    $ruletype = core_text::strtoupper(trim($ruletype));
    if (!in_array($ruletype, local_coursecalendar_get_rule_types(), true)) {
        throw new moodle_exception('invalidruletype', 'local_coursecalendar');
    }
    $now = time();
    $record = (object)[
        'calendarid' => $calendarid,
        'ruletype' => $ruletype,
        'ruledate' => $ruledate,
        'label' => trim($label),
        'description' => trim($description),
        'fromday' => $fromday ? trim($fromday) : null,
        'today' => $today ? trim($today) : null,
        'isactive' => 1,
        'sortorder' => 0,
        'timecreated' => $now,
        'timemodified' => $now,
        'usermodified' => $userid,
    ];
    return $DB->insert_record('local_coursecalendar_timeline_exception_rules', $record);
}

/**
 * Update an existing timeline exception rule.
 *
 * @param int $ruleid Rule ID to update.
 * @param int $ruledate New epoch timestamp for the rule date.
 * @param string $label New label.
 * @param string $description New description.
 * @param string|null $fromday Day-swap source day (optional).
 * @param string|null $today Day-swap target day (optional).
 * @param int $userid User making the change.
 * @return void
 */
function local_coursecalendar_update_rule(
    int $ruleid,
    int $ruledate,
    string $label,
    string $description,
    ?string $fromday,
    ?string $today,
    int $userid
): void {
    global $DB;
    $record = $DB->get_record('local_coursecalendar_timeline_exception_rules', ['id' => $ruleid], '*', MUST_EXIST);
    $record->ruledate = $ruledate;
    $record->label = trim($label);
    $record->description = trim($description);
    $record->fromday = $fromday ? trim($fromday) : null;
    $record->today = $today ? trim($today) : null;
    $record->timemodified = time();
    $record->usermodified = $userid;
    $DB->update_record('local_coursecalendar_timeline_exception_rules', $record);
}

/**
 * Delete a timeline exception rule.
 *
 * @param int $ruleid Rule ID to delete.
 * @return void
 */
function local_coursecalendar_delete_rule(int $ruleid): void {
    global $DB;
    $DB->delete_records('local_coursecalendar_timeline_exception_rules', ['id' => $ruleid]);
}

/**
 * Toggle a rule between active/inactive and return the new state.
 *
 * @param int $ruleid Rule ID to toggle.
 * @param int $userid User performing the toggle.
 * @return bool New active state.
 */
function local_coursecalendar_toggle_rule(int $ruleid, int $userid): bool {
    global $DB;
    $record = $DB->get_record('local_coursecalendar_timeline_exception_rules', ['id' => $ruleid], '*', MUST_EXIST);
    $record->isactive = (int)$record->isactive === 1 ? 0 : 1;
    $record->timemodified = time();
    $record->usermodified = $userid;
    $DB->update_record('local_coursecalendar_timeline_exception_rules', $record);
    return (int)$record->isactive === 1;
}

/**
 * Get the Monday of the week containing the given timestamp.
 *
 * @param int $timestamp Unix timestamp.
 * @return int Unix timestamp of that week's Monday (midnight local time).
 */
function local_coursecalendar_get_week_monday(int $timestamp): int {
    // PHP date('N'): 1 = Monday ... 7 = Sunday.
    $dow = (int)date('N', $timestamp);
    return strtotime('-' . ($dow - 1) . ' days', strtotime(date('Y-m-d', $timestamp)));
}

/**
 * Apply rules to a calendar -- the core rules engine.
 *
 * @param int $calendarid
 * @param int $userid
 * @return array Summary of the apply run.
 */
function local_coursecalendar_apply_rules(int $calendarid, int $userid): array {
    global $DB;

    $rules = local_coursecalendar_get_calendar_rules($calendarid, true);

    $startdate = null;
    $enddate = null;
    $noclassrules = [];
    $dayswaprules = [];
    $otherrules = [];

    foreach ($rules as $rule) {
        switch ($rule->ruletype) {
            case 'SEMESTER_START':
                $startdate = (int)$rule->ruledate;
                break;
            case 'SEMESTER_END':
                $enddate = (int)$rule->ruledate;
                break;
            case 'NO_CLASS':
                $noclassrules[] = $rule;
                break;
            case 'DAY_SWAP':
                $dayswaprules[] = $rule;
                break;
            case 'OTHER':
                $otherrules[] = $rule;
                break;
        }
    }

    if (!$startdate || !$enddate) {
        throw new moodle_exception('errorrulesmissingstartend', 'local_coursecalendar');
    }
    if ($enddate <= $startdate) {
        throw new moodle_exception('errorrulesendbeforestart', 'local_coursecalendar');
    }

    // Compute run hash for idempotency check.
    $ruleshash = local_coursecalendar_compute_rules_hash($rules);

    // Step 1: Delete all blocks where generatedbyrule = 1.
    $deleted = $DB->count_records('local_coursecalendar_calendar_blocks', [
        'calendarid' => $calendarid,
        'generatedbyrule' => 1,
    ]);
    $DB->delete_records('local_coursecalendar_calendar_blocks', [
        'calendarid' => $calendarid,
        'generatedbyrule' => 1,
    ]);

    // Step 2: Generate week rows.
    $startmonday = local_coursecalendar_get_week_monday($startdate);
    $endmonday = local_coursecalendar_get_week_monday($enddate);

    $weekmondays = [];
    $current = $startmonday;
    while ($current <= $endmonday) {
        $weekmondays[] = $current;
        $current = strtotime('+7 days', $current);
    }
    $totalweeks = count($weekmondays);

    // Ensure header row exists.
    local_coursecalendar_ensure_base_grid($calendarid, $userid);

    // Build a lookup of week monday -> row number, and annotations per row.
    $mondaytorow = [];
    $rowannotations = [];
    $now = time();
    $inserted = 0;

    for ($i = 0; $i < $totalweeks; $i++) {
        $rownum = $i + 1;
        $monday = $weekmondays[$i];
        $mondaytorow[$monday] = $rownum;
        $rowannotations[$rownum] = [];

        $monthday = date('M j', $monday);
        $label = 'Week ' . $rownum . '<br/>' . $monthday;

        if ($i === 0) {
            $startdatefmt = date('M j', $startdate);
            $label .= '<div class="local-coursecalendar-week-note">Classes begin ' . $startdatefmt . '</div>';
        }
        if ($i === $totalweeks - 1) {
            $enddatefmt = date('M j', $enddate);
            $label .= '<div class="local-coursecalendar-week-note">Last day of classes is ' . $enddatefmt . '</div>';
        }

        // Check if a non-rule-generated block already exists at col 0.
        $existing = $DB->get_record('local_coursecalendar_calendar_blocks', [
            'calendarid' => $calendarid,
            'rownum' => $rownum,
            'colnum' => 0,
        ], 'id, generatedbyrule', IGNORE_MISSING);

        if ($existing && (int)$existing->generatedbyrule === 0) {
            // Manually edited - skip.
            continue;
        }

        $block = (object)[
            'calendarid' => $calendarid,
            'rownum' => $rownum,
            'colnum' => 0,
            'blocktype' => 'TEXT',
            'contenthtml' => $label,
            'generatedbyrule' => 1,
            'generatedruleid' => null,
            'highlighted' => 0,
            'verticallycentred' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => $userid,
        ];
        if ($existing) {
            $block->id = $existing->id;
            $DB->update_record('local_coursecalendar_calendar_blocks', $block);
        } else {
            $DB->insert_record('local_coursecalendar_calendar_blocks', $block);
        }
        $inserted++;
    }

    // Remove excess rows beyond total weeks.
    $maxrow = (int)$DB->get_field_sql(
        'SELECT COALESCE(MAX(rownum), 0) FROM {local_coursecalendar_calendar_blocks} WHERE calendarid = :calendarid',
        ['calendarid' => $calendarid]
    );
    for ($r = $totalweeks + 1; $r <= $maxrow; $r++) {
        $DB->delete_records('local_coursecalendar_calendar_blocks', [
            'calendarid' => $calendarid,
            'rownum' => $r,
        ]);
    }

    // Step 3: get header day mappings for NO_CLASS column matching.
    // Weekday name => colnum.
    $headerdaymap = [];
    for ($c = 1; $c <= 3; $c++) {
        $header = $DB->get_record('local_coursecalendar_calendar_blocks', [
            'calendarid' => $calendarid,
            'rownum' => 0,
            'colnum' => $c,
        ], 'headerday', IGNORE_MISSING);
        if ($header && !empty($header->headerday)) {
            $headerdaymap[core_text::strtolower($header->headerday)] = $c;
        }
    }

    // Step 4: NO_CLASS markers.
    $noclassplaced = 0;
    foreach ($noclassrules as $rule) {
        $rulemonday = local_coursecalendar_get_week_monday((int)$rule->ruledate);
        if (!isset($mondaytorow[$rulemonday])) {
            continue;
        }
        $rownum = $mondaytorow[$rulemonday];
        $weekday = core_text::strtolower(date('l', (int)$rule->ruledate));
        if (!isset($headerdaymap[$weekday])) {
            continue;
        }
        $colnum = $headerdaymap[$weekday];

        // Only place if no manual block exists there.
        $existingcell = $DB->get_record('local_coursecalendar_calendar_blocks', [
            'calendarid' => $calendarid,
            'rownum' => $rownum,
            'colnum' => $colnum,
        ], 'id, generatedbyrule', IGNORE_MISSING);

        if ($existingcell && (int)$existingcell->generatedbyrule === 0) {
            continue;
        }

        $cellblock = (object)[
            'calendarid' => $calendarid,
            'rownum' => $rownum,
            'colnum' => $colnum,
            'blocktype' => 'TEXT',
            'contenthtml' => s($rule->label),
            'verticallycentred' => 1,
            'highlighted' => 0,
            'generatedbyrule' => 1,
            'generatedruleid' => (int)$rule->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => $userid,
        ];
        if ($existingcell) {
            $cellblock->id = $existingcell->id;
            $DB->update_record('local_coursecalendar_calendar_blocks', $cellblock);
        } else {
            $DB->insert_record('local_coursecalendar_calendar_blocks', $cellblock);
        }
        $noclassplaced++;
    }

    // Step 5: DAY_SWAP annotations on week labels.
    foreach ($dayswaprules as $rule) {
        $rulemonday = local_coursecalendar_get_week_monday((int)$rule->ruledate);
        if (!isset($mondaytorow[$rulemonday])) {
            continue;
        }
        $rownum = $mondaytorow[$rulemonday];
        $note = '<div class="local-coursecalendar-week-note">Note: ' . s($rule->fromday) .
                ' is a ' . s($rule->today) . ' schedule this week</div>';
        local_coursecalendar_append_week_label_note($calendarid, $rownum, $note, (int)$rule->id, $userid);
    }

    // Step 6: OTHER annotations on week labels.
    foreach ($otherrules as $rule) {
        $rulemonday = local_coursecalendar_get_week_monday((int)$rule->ruledate);
        if (!isset($mondaytorow[$rulemonday])) {
            continue;
        }
        $rownum = $mondaytorow[$rulemonday];
        $note = '<div class="local-coursecalendar-week-note">' . s($rule->label) . '</div>';
        local_coursecalendar_append_week_label_note($calendarid, $rownum, $note, (int)$rule->id, $userid);
    }

    // Step 7: Record apply run.
    $summary = [
        'deleted_rule_blocks' => $deleted,
        'week_labels_generated' => $inserted,
        'total_weeks' => $totalweeks,
        'noclass_placed' => $noclassplaced,
        'dayswap_rules' => count($dayswaprules),
        'other_rules' => count($otherrules),
    ];
    $DB->insert_record('local_coursecalendar_rule_apply_runs', (object)[
        'calendarid' => $calendarid,
        'appliedbyuserid' => $userid,
        'runhash' => $ruleshash,
        'summaryjson' => json_encode($summary),
        'timecreated' => $now,
    ]);

    return $summary;
}

/**
 * Append an annotation note to an existing week label block.
 *
 * @param int $calendarid
 * @param int $rownum
 * @param string $note
 * @param int $ruleid
 * @param int $userid
 * @return void
 */
function local_coursecalendar_append_week_label_note(
    int $calendarid,
    int $rownum,
    string $note,
    int $ruleid,
    int $userid
): void {
    global $DB;
    $block = $DB->get_record('local_coursecalendar_calendar_blocks', [
        'calendarid' => $calendarid,
        'rownum' => $rownum,
        'colnum' => 0,
    ], '*', IGNORE_MISSING);

    if (!$block) {
        return;
    }

    // Only append to rule-generated blocks.
    if ((int)$block->generatedbyrule === 0) {
        return;
    }

    $block->contenthtml .= $note;
    $block->timemodified = time();
    $block->usermodified = $userid;
    $DB->update_record('local_coursecalendar_calendar_blocks', $block);
}

/**
 * Compute a deterministic hash of active rules for idempotency.
 *
 * @param array $rules
 * @return string SHA-256 hex digest.
 */
function local_coursecalendar_compute_rules_hash(array $rules): string {
    $data = [];
    foreach ($rules as $rule) {
        $data[] = [
            'id' => (int)$rule->id,
            'type' => $rule->ruletype,
            'date' => (int)$rule->ruledate,
            'label' => $rule->label,
            'fromday' => $rule->fromday,
            'today' => $rule->today,
        ];
    }
    usort($data, function ($a, $b) {
        return $a['id'] <=> $b['id'];
    });
    return hash('sha256', json_encode($data));
}

// Topic placement automation and coverage tools.

/**
 * Auto-populate a calendar grid with topics from the linked blueprint.
 *
 * @param int $calendarid
 * @param int $blueprintid
 * @param int $userid
 * @return array Summary with placed counts.
 */
function local_coursecalendar_auto_populate(int $calendarid, int $blueprintid, int $userid): array {
    global $DB;

    $blocksmap = local_coursecalendar_get_blocks_map($calendarid);
    $maxrow = 0;
    foreach (array_keys($blocksmap) as $r) {
        $maxrow = max($maxrow, (int)$r);
    }
    if ($maxrow < 1) {
        return ['lectures' => 0, 'labs' => 0, 'homework' => 0, 'error' => 'noweekrows'];
    }

    // Determine column modes from header row.
    $lecturecols = [];
    $labcols = [];
    for ($c = 1; $c <= 3; $c++) {
        $header = $blocksmap[0][$c] ?? null;
        if ($header && core_text::strtolower((string)$header->headermode) === 'lecture') {
            $lecturecols[] = $c;
        } else if ($header && core_text::strtolower((string)$header->headermode) === 'lab') {
            $labcols[] = $c;
        }
    }

    $now = time();
    $lecturesplaced = 0;
    $labsplaced = 0;
    $homeworkplaced = 0;

    // Step 1: Place LECTURE, ELESSON, TEST into Lecture-mode columns.
    $lecturetopics = $DB->get_records_select(
        'local_coursecalendar_blueprint_topics',
        "blueprintid = :bpid AND type IN ('LECTURE', 'ELESSON', 'TEST') AND isactive = 1",
        ['bpid' => $blueprintid],
        'sortorder ASC'
    );

    // Map topic id to the row/col position it was placed at.
    $placedpositions = [];
    $topicqueue = array_values($lecturetopics);
    $tqi = 0;

    for ($row = 1; $row <= $maxrow && $tqi < count($topicqueue); $row++) {
        foreach ($lecturecols as $col) {
            if ($tqi >= count($topicqueue)) {
                break;
            }
            if (isset($blocksmap[$row][$col])) {
                continue;
            }
            $topic = $topicqueue[$tqi];
            $cellheading = '';
            $highlighted = 0;
            $vcentred = 0;
            if ($topic->type === 'ELESSON') {
                $notice = '<span class="local-coursecalendar-elesson-notice">eLesson</span>';
                $sub = '<span class="local-coursecalendar-elesson-sub">'
                    . 'Do not come to class. Do eLesson before next lecture.</span>';
                $cellheading = '<div>' . $notice . $sub . '</div>';
            } else if ($topic->type === 'TEST') {
                $highlighted = 1;
                $vcentred = 1;
            }
            local_coursecalendar_upsert_block(
                $calendarid,
                $row,
                $col,
                'TOPIC',
                '',
                $userid,
                null,
                null,
                (int)$topic->id,
                $cellheading,
                $highlighted,
                $vcentred
            );
            $placedpositions[(int)$topic->id] = ['row' => $row, 'col' => $col];
            $blocksmap[$row][$col] = true;
            $lecturesplaced++;
            $tqi++;
        }
    }

    // Step 2: Place LAB topics after their prerequisite lecture row.
    $labtopics = $DB->get_records_select(
        'local_coursecalendar_blueprint_topics',
        "blueprintid = :bpid AND type = 'LAB' AND isactive = 1",
        ['bpid' => $blueprintid],
        'sortorder ASC'
    );
    $allsorted = $DB->get_records(
        'local_coursecalendar_blueprint_topics',
        ['blueprintid' => $blueprintid, 'isactive' => 1],
        'sortorder ASC'
    );
    $sortedids = array_keys($allsorted);

    foreach ($labtopics as $lab) {
        $prereqrow = 1;
        $labpos = array_search((int)$lab->id, $sortedids);
        if ($labpos !== false) {
            for ($pi = $labpos - 1; $pi >= 0; $pi--) {
                $prereqid = $sortedids[$pi];
                $prereqtopic = $allsorted[$prereqid] ?? null;
                if ($prereqtopic && in_array($prereqtopic->type, ['LECTURE', 'ELESSON', 'TEST'], true)) {
                    if (isset($placedpositions[(int)$prereqid])) {
                        $prereqrow = $placedpositions[(int)$prereqid]['row'] + 1;
                    }
                    break;
                }
            }
        }

        $placed = false;
        for ($row = $prereqrow; $row <= $maxrow; $row++) {
            foreach ($labcols as $col) {
                if (isset($blocksmap[$row][$col])) {
                    continue;
                }
                local_coursecalendar_upsert_block(
                    $calendarid,
                    $row,
                    $col,
                    'TOPIC',
                    '',
                    $userid,
                    null,
                    null,
                    (int)$lab->id
                );
                $blocksmap[$row][$col] = true;
                $labsplaced++;
                $placed = true;
                break 2;
            }
        }
    }

    // Step 3: Place HOMEWORK topics into column 4.
    $homeworktopics = $DB->get_records_select(
        'local_coursecalendar_blueprint_topics',
        "blueprintid = :bpid AND type = 'HOMEWORK' AND isactive = 1",
        ['bpid' => $blueprintid],
        'sortorder ASC'
    );
    $hwqueue = array_values($homeworktopics);
    $hwi = 0;
    for ($row = 1; $row <= $maxrow && $hwi < count($hwqueue); $row++) {
        if (isset($blocksmap[$row][4])) {
            continue;
        }
        $hw = $hwqueue[$hwi];
        local_coursecalendar_upsert_block(
            $calendarid,
            $row,
            4,
            'TOPIC',
            '',
            $userid,
            null,
            null,
            (int)$hw->id
        );
        $blocksmap[$row][4] = true;
        $homeworkplaced++;
        $hwi++;
    }

    return ['lectures' => $lecturesplaced, 'labs' => $labsplaced, 'homework' => $homeworkplaced];
}

/**
 * Fill empty Lab-mode cells with "Problem Session" TEXT blocks.
 *
 * @param int $calendarid
 * @param int $userid
 * @return int Number of cells filled.
 */
function local_coursecalendar_fill_problem_sessions(int $calendarid, int $userid): int {
    global $DB;

    $blocksmap = local_coursecalendar_get_blocks_map($calendarid);
    $maxrow = 0;
    foreach (array_keys($blocksmap) as $r) {
        $maxrow = max($maxrow, (int)$r);
    }

    $labcols = [];
    for ($c = 1; $c <= 3; $c++) {
        $header = $blocksmap[0][$c] ?? null;
        if ($header && core_text::strtolower((string)$header->headermode) === 'lab') {
            $labcols[] = $c;
        }
    }

    $filled = 0;
    for ($row = 1; $row <= $maxrow; $row++) {
        foreach ($labcols as $col) {
            if (isset($blocksmap[$row][$col])) {
                continue;
            }
            local_coursecalendar_upsert_block(
                $calendarid,
                $row,
                $col,
                'TEXT',
                'Problem Session',
                $userid,
                null,
                null,
                null,
                '',
                0,
                1
            );
            $filled++;
        }
    }
    return $filled;
}

/**
 * Run coverage check on a calendar. Returns found/missing/empty arrays.
 *
 * @param int $calendarid
 * @param int $blueprintid
 * @return array
 */
function local_coursecalendar_coverage_check(int $calendarid, int $blueprintid): array {
    global $DB;

    $blocksmap = local_coursecalendar_get_blocks_map($calendarid);
    $maxrow = 0;
    foreach (array_keys($blocksmap) as $r) {
        $maxrow = max($maxrow, (int)$r);
    }

    $activetopics = $DB->get_records('local_coursecalendar_blueprint_topics', [
        'blueprintid' => $blueprintid,
        'isactive' => 1,
    ], 'sortorder ASC');

    // Build header info.
    $headerinfo = [];
    for ($c = 0; $c <= 4; $c++) {
        $h = $blocksmap[0][$c] ?? null;
        $headerinfo[$c] = [
            'day' => $h ? (string)$h->headerday : '',
            'mode' => $h ? (string)$h->headermode : '',
        ];
    }

    // Find placed topic IDs.
    $placedtopicids = [];
    $found = [];
    for ($row = 1; $row <= $maxrow; $row++) {
        for ($col = 0; $col <= 4; $col++) {
            $cell = $blocksmap[$row][$col] ?? null;
            if ($cell && (string)$cell->blocktype === 'TOPIC' && !empty($cell->topicid)) {
                $tid = (int)$cell->topicid;
                $placedtopicids[$tid] = true;
                $topic = $activetopics[$tid] ?? null;
                $found[] = [
                    'topicid' => $tid,
                    'title' => $topic ? $topic->title : '(unknown)',
                    'type' => $topic ? $topic->type : '',
                    'row' => $row,
                    'col' => $col,
                    'headerday' => $headerinfo[$col]['day'] ?? '',
                    'headermode' => $headerinfo[$col]['mode'] ?? '',
                ];
            }
        }
    }

    $missing = [];
    foreach ($activetopics as $topic) {
        if (!isset($placedtopicids[(int)$topic->id])) {
            $missing[] = [
                'topicid' => (int)$topic->id,
                'title' => $topic->title,
                'type' => $topic->type,
            ];
        }
    }

    $emptyslots = [];
    for ($row = 1; $row <= $maxrow; $row++) {
        for ($col = 1; $col <= 3; $col++) {
            if (!isset($blocksmap[$row][$col])) {
                $emptyslots[] = [
                    'row' => $row,
                    'col' => $col,
                    'headerday' => $headerinfo[$col]['day'] ?? '',
                    'headermode' => $headerinfo[$col]['mode'] ?? '',
                ];
            }
        }
    }

    return ['found' => $found, 'missing' => $missing, 'empty' => $emptyslots];
}

/**
 * Delete all non-header blocks (rownum > 0).
 *
 * @param int $calendarid
 * @return int Number of deleted blocks.
 */
function local_coursecalendar_delete_non_header_blocks(int $calendarid): int {
    global $DB;
    $count = $DB->count_records_select(
        'local_coursecalendar_calendar_blocks',
        'calendarid = :cid AND rownum > 0',
        ['cid' => $calendarid]
    );
    $DB->delete_records_select(
        'local_coursecalendar_calendar_blocks',
        'calendarid = :cid AND rownum > 0',
        ['cid' => $calendarid]
    );
    return $count;
}

/**
 * Delete TOPIC blocks and "Problem Session" TEXT blocks, preserving week labels and other text.
 *
 * @param int $calendarid
 * @return int Number of deleted blocks.
 */
function local_coursecalendar_delete_non_header_non_text_blocks(int $calendarid): int {
    global $DB;

    $blocks = $DB->get_records_select(
        'local_coursecalendar_calendar_blocks',
        'calendarid = :cid AND rownum > 0',
        ['cid' => $calendarid]
    );

    $deleted = 0;
    foreach ($blocks as $block) {
        $shoulddelete = false;
        if ((string)$block->blocktype === 'TOPIC') {
            $shoulddelete = true;
        } else if ((string)$block->blocktype === 'TEXT' && trim((string)$block->contenthtml) === 'Problem Session') {
            $shoulddelete = true;
        }
        if ($shoulddelete) {
            $DB->delete_records('local_coursecalendar_calendar_blocks', ['id' => $block->id]);
            $deleted++;
        }
    }
    return $deleted;
}

// Course info and date-to-cell helpers.

/**
 * Get or create course_info record.
 *
 * @param int $courseid
 * @return stdClass|null
 */
function local_coursecalendar_get_course_info(int $courseid): ?stdClass {
    global $DB;
    return $DB->get_record('local_coursecalendar_course_info', ['courseid' => $courseid], '*', IGNORE_MISSING) ?: null;
}

/**
 * Create or update the course info record (intro + links panels).
 *
 * @param int $courseid Course to save info for.
 * @param string $introhtml Intro panel HTML.
 * @param string $linkshtml Links panel HTML.
 * @param int $userid User performing the save.
 * @return void
 */
function local_coursecalendar_save_course_info(int $courseid, string $introhtml, string $linkshtml, int $userid): void {
    global $DB;
    $now = time();
    $existing = $DB->get_record('local_coursecalendar_course_info', ['courseid' => $courseid], '*', IGNORE_MISSING);
    if ($existing) {
        $existing->introhtml = $introhtml;
        $existing->linkshtml = $linkshtml;
        $existing->timemodified = $now;
        $existing->usermodified = $userid;
        $DB->update_record('local_coursecalendar_course_info', $existing);
    } else {
        $DB->insert_record('local_coursecalendar_course_info', (object)[
            'courseid' => $courseid,
            'introhtml' => $introhtml,
            'linkshtml' => $linkshtml,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => $userid,
        ]);
    }
}

/**
 * Map a date to a grid cell position using the week-label structure and header days.
 *
 * @param array $blocksmap Block grid keyed [row][col] of block records.
 * @param int $maxrow Highest row number present in the grid.
 * @param int $timestamp Unix timestamp of the date to locate.
 * @return array|null ['row' => int, 'col' => int] or null if not found.
 */
function local_coursecalendar_date_to_cell(array $blocksmap, int $maxrow, int $timestamp): ?array {
    $daymap = ['monday' => 0, 'tuesday' => 1, 'wednesday' => 2, 'thursday' => 3, 'friday' => 4, 'saturday' => 5, 'sunday' => 6];

    // Build header day offsets for cols 1-3.
    $coloffsets = [];
    for ($c = 1; $c <= 3; $c++) {
        $h = $blocksmap[0][$c] ?? null;
        if ($h && !empty($h->headerday)) {
            $dayname = core_text::strtolower((string)$h->headerday);
            if (isset($daymap[$dayname])) {
                $coloffsets[$c] = $daymap[$dayname];
            }
        }
    }

    // Parse week mondays from col-0 labels.
    $rowmondays = [];
    for ($row = 1; $row <= $maxrow; $row++) {
        $cell = $blocksmap[$row][0] ?? null;
        if (!$cell) {
            continue;
        }
        $content = strip_tags((string)$cell->contenthtml);
        if (preg_match('/(\w{3})\s+(\d{1,2})/', $content, $m)) {
            $parsed = strtotime($m[1] . ' ' . $m[2] . ' ' . date('Y', $timestamp));
            if ($parsed) {
                $rowmondays[$row] = local_coursecalendar_get_week_monday($parsed);
            }
        }
    }

    $targetdate = strtotime(date('Y-m-d', $timestamp));
    $targetmonday = local_coursecalendar_get_week_monday($targetdate);

    foreach ($rowmondays as $row => $monday) {
        if ($monday === $targetmonday) {
            foreach ($coloffsets as $col => $offset) {
                $celldate = strtotime('+' . $offset . ' days', $monday);
                if ($celldate === $targetdate) {
                    return ['row' => $row, 'col' => $col];
                }
            }
            return ['row' => $row, 'col' => null];
        }
    }

    // Find nearest row.
    $nearestrow = null;
    $nearestdiff = PHP_INT_MAX;
    foreach ($rowmondays as $row => $monday) {
        $diff = abs($monday - $targetmonday);
        if ($diff < $nearestdiff) {
            $nearestdiff = $diff;
            $nearestrow = $row;
        }
    }

    if ($nearestrow !== null) {
        return ['row' => $nearestrow, 'col' => null, 'nearest' => true];
    }

    return null;
}

// Migration helpers.

/**
 * Parse pasted HTML table into blueprint topics.
 *
 * @param string $html Raw HTML table content.
 * @param string $layout Column layout: 'LLL', 'LLB', 'LBL', 'BLL'
 * @param int $blueprintid
 * @param int $userid
 * @return array ['created' => int, 'skipped' => int]
 */
function local_coursecalendar_seed_topics_from_html(string $html, string $layout, int $blueprintid, int $userid): array {
    $skippatterns = [
        '/^\s*problem\s+session/i',
        '/^\s*college\s+closed/i',
        '/^\s*no\s+class/i',
        '/^\s*thanksgiving/i',
        '/^\s*spring\s+break/i',
        '/^\s*reading\s+week/i',
    ];

    $colmodes = [];
    $chars = str_split(strtoupper($layout));
    foreach ($chars as $i => $ch) {
        $colmodes[$i + 1] = ($ch === 'L') ? 'Lecture' : 'Lab';
    }

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $rows = $dom->getElementsByTagName('tr');

    $created = 0;
    $skipped = 0;
    $sortorder = (int)local_coursecalendar_next_topic_sortorder($blueprintid);
    $now = time();

    for ($ri = 0; $ri < $rows->length; $ri++) {
        $row = $rows->item($ri);
        $cells = $row->getElementsByTagName('td');
        if ($cells->length === 0) {
            $cells = $row->getElementsByTagName('th');
        }

        for ($ci = 0; $ci < $cells->length; $ci++) {
            $cell = $cells->item($ci);
            $innerhtml = '';
            foreach ($cell->childNodes as $child) {
                $innerhtml .= $dom->saveHTML($child);
            }
            $text = trim(strip_tags($innerhtml));
            if ($text === '') {
                continue;
            }

            foreach ($skippatterns as $pat) {
                if (preg_match($pat, $text)) {
                    $skipped++;
                    continue 2;
                }
            }

            $colindex = $ci + 1;
            $type = local_coursecalendar_detect_topic_type($text, $colmodes[$colindex] ?? 'Lecture', $colindex, count($chars));

            $title = local_coursecalendar_extract_topic_title($text, $type);
            if ($title === '') {
                $title = mb_substr($text, 0, 80);
            }

            $DB = $GLOBALS['DB'];
            $DB->insert_record('local_coursecalendar_blueprint_topics', (object)[
                'blueprintid' => $blueprintid,
                'title' => $title,
                'type' => $type,
                'contenthtml' => trim($innerhtml),
                'sortorder' => $sortorder,
                'isactive' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => $userid,
            ]);
            $sortorder++;
            $created++;
        }
    }

    return ['created' => $created, 'skipped' => $skipped];
}

/**
 * Return the next available sort order for a topic in the given blueprint.
 *
 * @param int $blueprintid Blueprint ID.
 * @return int Next sortorder to use.
 */
function local_coursecalendar_next_topic_sortorder(int $blueprintid): int {
    global $DB;
    $max = (int)$DB->get_field_sql(
        'SELECT COALESCE(MAX(sortorder), -1) FROM {local_coursecalendar_blueprint_topics} WHERE blueprintid = :bpid',
        ['bpid' => $blueprintid]
    );
    return $max + 1;
}

/**
 * Detect a topic type (LECTURE, LAB, TEST, ELESSON, HOMEWORK) from pasted cell text.
 *
 * @param string $text Cell text content.
 * @param string $colmode Column mode ("Lecture" or "Lab").
 * @param int $colindex 1-based column index.
 * @param int $totalcols Total number of primary columns (excluding homework column).
 * @return string Detected topic type code.
 */
function local_coursecalendar_detect_topic_type(string $text, string $colmode, int $colindex, int $totalcols): string {
    $lower = core_text::strtolower($text);
    if (preg_match('/^test|^exam|^midterm|^final\s+exam/i', $text)) {
        return 'TEST';
    }
    if (preg_match('/^lab\b/i', $text)) {
        return 'LAB';
    }
    if (preg_match('/elesson|e-lesson/i', $text)) {
        return 'ELESSON';
    }
    if ($colindex === $totalcols + 1 || preg_match('/homework|assignment|problem\s+set|hw\s*\d/i', $text)) {
        return 'HOMEWORK';
    }
    if (core_text::strtolower($colmode) === 'lab') {
        return 'LAB';
    }
    return 'LECTURE';
}

/**
 * Extract a clean topic title from pasted cell text.
 *
 * @param string $text Cell text content.
 * @param string $type Detected topic type (as returned by {@see local_coursecalendar_detect_topic_type()}).
 * @return string Short title suitable for storing on the topic record.
 */
function local_coursecalendar_extract_topic_title(string $text, string $type): string {
    if ($type === 'TEST' || $type === 'LAB') {
        return mb_substr($text, 0, 120);
    }
    // Try to extract title from "Class slides:" or first line.
    if (preg_match('/class\s+slides?\s*:?\s*(.+?)(?:\n|$)/i', $text, $m)) {
        return trim($m[1]);
    }
    $lines = preg_split('/[\r\n]+/', $text, 2);
    return trim($lines[0]);
}

/**
 * Bulk update eLesson links in topic content.
 *
 * @param string $html HTML containing eLesson links.
 * @param int $blueprintid
 * @return array ['updated' => int, 'notfound' => int]
 */
function local_coursecalendar_bulk_update_elesson_links(string $html, int $blueprintid): array {
    global $DB;

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $links = $dom->getElementsByTagName('a');

    $linklist = [];
    for ($i = 0; $i < $links->length; $i++) {
        $a = $links->item($i);
        $href = $a->getAttribute('href');
        $text = trim($a->textContent);
        if ($href && $text) {
            $linklist[] = ['href' => $href, 'text' => $text];
        }
    }

    $elessons = $DB->get_records_select(
        'local_coursecalendar_blueprint_topics',
        "blueprintid = :bpid AND type = 'ELESSON'",
        ['bpid' => $blueprintid],
        'sortorder ASC'
    );

    $updated = 0;
    $notfound = 0;

    foreach ($linklist as $link) {
        $matched = false;
        foreach ($elessons as $topic) {
            $contenttext = strip_tags((string)$topic->contenthtml);
            $firstbullet = '';
            if (preg_match('/(?:^|\n)\s*(?:[-•*]|\d+[.\)])\s*(.+?)(?:\n|$)/', $contenttext, $m)) {
                $firstbullet = trim($m[1]);
            } else {
                $lines = preg_split('/[\r\n]+/', $contenttext, 2);
                $firstbullet = trim($lines[0] ?? '');
            }

            if (
                $firstbullet !== '' && (
                stripos($link['text'], $firstbullet) !== false ||
                stripos($firstbullet, $link['text']) !== false
                )
            ) {
                $newcontent = preg_replace(
                    '/<a\b[^>]*>.*?' . preg_quote(htmlspecialchars($link['text']), '/') . '.*?<\/a>/i',
                    '<a href="' . s($link['href']) . '">' . s($link['text']) . '</a>',
                    (string)$topic->contenthtml,
                    1,
                    $count
                );
                if ($count === 0) {
                    $newcontent = (string)$topic->contenthtml;
                    if (strpos($newcontent, $link['text']) !== false) {
                        $newcontent = str_replace(
                            $link['text'],
                            '<a href="' . s($link['href']) . '">' . s($link['text']) . '</a>',
                            $newcontent
                        );
                        $count = 1;
                    }
                }
                if ($count > 0) {
                    $topic->contenthtml = $newcontent;
                    $topic->timemodified = time();
                    $DB->update_record('local_coursecalendar_blueprint_topics', $topic);
                    $updated++;
                    $matched = true;
                    break;
                }
            }
        }
        if (!$matched) {
            $notfound++;
        }
    }

    return ['updated' => $updated, 'notfound' => $notfound];
}

// AI-assisted semester date extraction.

/**
 * Build the Gemini extraction prompt.
 *
 * The prompt is shared between the text-paste and PDF-upload code paths so that
 * Gemini produces consistent JSON regardless of input format.
 */
function local_coursecalendar_gemini_extraction_prompt(): string {
    return "You are an academic calendar parser. Extract all important dates from the following input "
        . "and return them as a JSON array. Each element should be an object with these fields:\n"
        . "- \"type\": one of SEMESTER_START, SEMESTER_END, NO_CLASS, DAY_SWAP, OTHER\n"
        . "- \"date\": in YYYY-MM-DD format\n"
        . "- \"label\": short description (e.g., \"Labor Day\", \"Spring Break\")\n"
        . "- \"description\": optional longer description\n"
        . "- \"fromday\": for DAY_SWAP only, the day being swapped FROM (e.g., \"MONDAY\")\n"
        . "- \"today\": for DAY_SWAP only, the day being swapped TO\n\n"
        . "Rules for type detection:\n"
        . "- First day of classes or semester begins = SEMESTER_START\n"
        . "- Last day of classes or semester ends = SEMESTER_END\n"
        . "- College closed, holiday, no classes, break = NO_CLASS\n"
        . "- Follow X schedule, classes meet as Y = DAY_SWAP\n"
        . "- Everything else = OTHER\n\n"
        . "Return ONLY the JSON array, no markdown formatting.";
}

/**
 * Low-level Gemini API caller.
 *
 * Accepts an array of Gemini `parts` (text/inline_data) and returns the extracted
 * JSON array as a pretty-printed string, or null on failure.
 *
 * @param string $apikey Gemini API key.
 * @param array  $parts  Array of Gemini content parts.
 * @return string|null
 */
function local_coursecalendar_gemini_call(string $apikey, array $parts): ?string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key='
        . urlencode($apikey);

    $payload = json_encode([
        'contents' => [
            ['parts' => $parts],
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 4096,
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text) {
        return null;
    }

    $text = trim($text);
    // Gemini often wraps JSON in markdown code fences; strip them if present.
    $fence = str_repeat(chr(96), 3);
    if (strpos($text, $fence) !== false) {
        if (preg_match('/' . $fence . '(?:json)?\s*(.*?)\s*' . $fence . '/s', $text, $m)) {
            $text = $m[1];
        }
    }

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        return null;
    }

    return json_encode($decoded, JSON_PRETTY_PRINT);
}

/**
 * Call Google Gemini API to extract academic calendar dates from text.
 *
 * @param string $apikey
 * @param string $inputtext
 * @return string|null JSON string of extracted events, or null on failure.
 */
function local_coursecalendar_gemini_extract_dates(string $apikey, string $inputtext): ?string {
    $prompt = local_coursecalendar_gemini_extraction_prompt()
        . "\n\nText to parse:\n" . $inputtext;

    return local_coursecalendar_gemini_call($apikey, [['text' => $prompt]]);
}

/**
 * Call Google Gemini API to extract academic calendar dates from a PDF document.
 *
 * The PDF is sent inline (base64) to Gemini, so callers should keep files reasonably
 * sized (a few MB is fine; Gemini accepts up to ~20MB inline).
 *
 * @param string $apikey  Gemini API key.
 * @param string $pdfdata Raw binary contents of the PDF file.
 * @return string|null JSON string of extracted events, or null on failure.
 */
function local_coursecalendar_gemini_extract_dates_from_pdf(string $apikey, string $pdfdata): ?string {
    if ($pdfdata === '') {
        return null;
    }

    $parts = [
        [
            'inline_data' => [
                'mime_type' => 'application/pdf',
                'data'      => base64_encode($pdfdata),
            ],
        ],
        ['text' => local_coursecalendar_gemini_extraction_prompt()],
    ];

    return local_coursecalendar_gemini_call($apikey, $parts);
}

/**
 * Delete all topics for a blueprint (with optional force flag to bypass calendar reference check).
 *
 * @param int $blueprintid
 * @param bool $force
 * @return int Number of deleted topics.
 */
function local_coursecalendar_delete_all_topics(int $blueprintid, bool $force = false): int {
    global $DB;
    if (!$force) {
        $referenced = $DB->count_records_select(
            'local_coursecalendar_calendar_blocks',
            "topicid IN (SELECT id FROM {local_coursecalendar_blueprint_topics} WHERE blueprintid = :bpid) AND blocktype = 'TOPIC'",
            ['bpid' => $blueprintid]
        );
        if ($referenced > 0) {
            return -1;
        }
    }
    $count = $DB->count_records('local_coursecalendar_blueprint_topics', ['blueprintid' => $blueprintid]);
    $DB->delete_records('local_coursecalendar_blueprint_topics', ['blueprintid' => $blueprintid]);
    return $count;
}

/**
 * Look up a tour's numeric ID by its configured name.
 *
 * Returns null if tool_usertours is not installed or the tour does not exist.
 *
 * @param string $name The tour name (matches tool_usertours_tours.name).
 * @return int|null
 */
function local_coursecalendar_get_tour_id_by_name(string $name): ?int {
    global $DB;
    if (!class_exists('\\tool_usertours\\manager')) {
        return null;
    }
    $id = $DB->get_field('tool_usertours_tours', 'id', ['name' => $name], IGNORE_MISSING);
    return $id ? (int)$id : null;
}

/**
 * List of user tours shipped with this plugin.
 *
 * Map of JSON filename (in local/coursecalendar/tours/) to a version integer.
 * Bump the version when the JSON changes so the seeder re-imports it.
 *
 * @return array<string, int>
 */
function local_coursecalendar_shipped_tours(): array {
    return [
        'teacher_setup_tour.json'   => 4,
        'teacher_builder_tour.json' => 2,
        'teacher_rules_tour.json'   => 2,
    ];
}

/**
 * Install or refresh the plugin's shipped Moodle user tours.
 *
 * No-op if tool_usertours is not installed. Idempotent: it imports any
 * shipped tour whose version is newer than what's recorded in the DB,
 * removing the stale copy first. Uses the same configdata keys that
 * Moodle core uses for its own shipped tours so admins see the "shipped"
 * badge and warning on tours they might edit.
 *
 * Safe to call from both db/install.php and db/upgrade.php.
 */
function local_coursecalendar_install_user_tours(): void {
    global $DB, $CFG;

    if (!class_exists('\\tool_usertours\\manager')) {
        return;
    }

    $shipped = local_coursecalendar_shipped_tours();
    $tourdir = $CFG->dirroot . '/local/coursecalendar/tours/';

    $existingrecords = $DB->get_recordset('tool_usertours_tours');
    foreach ($existingrecords as $record) {
        $tour = \tool_usertours\tour::load_from_record($record);
        $filename = $tour->get_config('local_coursecalendar_filename');
        if (empty($filename) || !isset($shipped[$filename])) {
            continue;
        }
        $installedversion = (int)$tour->get_config('local_coursecalendar_version');
        if ($installedversion < $shipped[$filename]) {
            $tour->remove();
        } else {
            unset($shipped[$filename]);
        }
    }
    $existingrecords->close();

    if (class_exists('\\tool_usertours\\helper')) {
        \tool_usertours\helper::reset_tour_sortorder();
    }

    foreach ($shipped as $filename => $version) {
        $filepath = $tourdir . $filename;
        if (!is_readable($filepath)) {
            continue;
        }
        $tourjson = file_get_contents($filepath);
        if ($tourjson === false) {
            continue;
        }
        try {
            $tour = \tool_usertours\manager::import_tour_from_json($tourjson);
        } catch (\Throwable $e) {
            debugging('local_coursecalendar: failed to import user tour ' . $filename . ': ' . $e->getMessage());
            continue;
        }

        $tour->set_config('local_coursecalendar_filename', $filename);
        $tour->set_config('local_coursecalendar_version', $version);
        $tour->set_config(\tool_usertours\manager::CONFIG_SHIPPED_TOUR, true);
        $tour->set_config(\tool_usertours\manager::CONFIG_SHIPPED_FILENAME, $filename);
        $tour->set_config(\tool_usertours\manager::CONFIG_SHIPPED_VERSION, $version);
        $tour->persist();

        if (defined('BEHAT_SITE_RUNNING') || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $tour->set_enabled(\tool_usertours\tour::DISABLED);
            $tour->persist();
        }
    }
}
