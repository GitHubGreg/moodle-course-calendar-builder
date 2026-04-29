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

defined('MOODLE_INTERNAL') || die();

/**
 * Add local_coursecalendar links to course navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_coursecalendar_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (has_capability('local/coursecalendar:managecalendar', $context)) {
        $manageurl = new moodle_url('/local/coursecalendar/manage.php', ['id' => $course->id]);
        $navigation->add(
            get_string('managecoursecalendar', 'local_coursecalendar'),
            $manageurl,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_coursecalendar_manage'
        );
    }

    if (has_capability('local/coursecalendar:viewcalendar', $context)) {
        $studenturl = new moodle_url('/local/coursecalendar/student.php', ['id' => $course->id]);
        $navigation->add(
            get_string('viewcoursecalendar', 'local_coursecalendar'),
            $studenturl,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_coursecalendar_student'
        );
    }
}

