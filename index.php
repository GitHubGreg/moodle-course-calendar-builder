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
 * Landing page for the course calendar plugin.
 *
 * @package    local_coursecalendar
 * @copyright  2026 Greg Mulcair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);

if (has_capability('local/coursecalendar:managecalendar', $context)) {
    redirect(new moodle_url('/local/coursecalendar/manage.php', ['id' => $courseid]));
}

require_capability('local/coursecalendar:viewcalendar', $context);
redirect(new moodle_url('/local/coursecalendar/student.php', ['id' => $courseid]));
