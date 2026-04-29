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

$functions = [
    'local_coursecalendar_save_builder_grid' => [
        'classname' => 'local_coursecalendar_external',
        'methodname' => 'save_builder_grid',
        'classpath' => 'local/coursecalendar/externallib.php',
        'description' => 'Batch-save calendar builder grid blocks.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_coursecalendar_swap_builder_cells' => [
        'classname' => 'local_coursecalendar_external',
        'methodname' => 'swap_builder_cells',
        'classpath' => 'local/coursecalendar/externallib.php',
        'description' => 'Swap or move two calendar builder cells.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_coursecalendar_reorder_blueprint_topics' => [
        'classname' => 'local_coursecalendar_external',
        'methodname' => 'reorder_blueprint_topics',
        'classpath' => 'local/coursecalendar/externallib.php',
        'description' => 'Persist a new sortorder for blueprint topics (drag-and-drop).',
        'type' => 'write',
        'ajax' => true,
    ],
];
