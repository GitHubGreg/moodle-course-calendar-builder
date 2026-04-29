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
 * Upgrade hook for local_coursecalendar.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_coursecalendar_upgrade(int $oldversion): bool {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/local/coursecalendar/locallib.php');

    if ($oldversion < 2026022601) {
        // Initial savepoint; no schema changes yet.
        upgrade_plugin_savepoint(true, 2026022601, 'local', 'coursecalendar');
    }

    if ($oldversion < 2026022701) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_coursecalendar_course_info');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('introhtml', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('linkshtml', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('courseid_uq', XMLDB_KEY_UNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022701, 'local', 'coursecalendar');
    }

    if ($oldversion < 2026042401) {
        local_coursecalendar_install_user_tours();
        upgrade_plugin_savepoint(true, 2026042401, 'local', 'coursecalendar');
    }

    if ($oldversion < 2026042402) {
        local_coursecalendar_install_user_tours();
        upgrade_plugin_savepoint(true, 2026042402, 'local', 'coursecalendar');
    }

    if ($oldversion < 2026042403) {
        local_coursecalendar_install_user_tours();
        upgrade_plugin_savepoint(true, 2026042403, 'local', 'coursecalendar');
    }

    if ($oldversion < 2026042406) {
        local_coursecalendar_install_user_tours();
        upgrade_plugin_savepoint(true, 2026042406, 'local', 'coursecalendar');
    }

    return true;
}

