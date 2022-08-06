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
 * API of local_coursefiles.
 *
 * @package    local_coursefiles
 * @copyright  2022 Kacper Rokicki <k.k.rokicki@gmail.com>
 * @copyright  based on work by 2016 Martin Gauk (@innoCampus, TU Berlin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds link to Course administration
 *
 * @param settings_navigation $nav
 * @param context $context
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_coursefiles_extend_settings_navigation(settings_navigation $nav, context $context) {
    global $DB;

    if ($context instanceof context_course) {
        $courseid = $context->get_course_context()->instanceid;
        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            throw new moodle_exception('invalidcourseid');
        }
        if (can_access_course($course)) {
            if ($course = $nav->get('courseadmin')) {
                $url = new moodle_url('/local/coursefiles/index.php', array('courseid' => $courseid));
                $course->add(
                    get_string('linkname', 'local_coursefiles'),
                    $url
                );
            }
        }
    }
}
