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

namespace local_coursefiles\components;

use dml_exception;
use local_coursefiles\course_file;
use moodle_url;

/**
 * Class mod_page
 * @package local_coursefiles
 * @author Jeremy FitzPatrick
 * @copyright 2022 Te Wānanga o Aotearoa
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_page extends course_file {
    /**
     * Try to get the download url for a file.
     *
     * @return null|moodle_url
     */
    protected function get_file_download_url() : ?moodle_url {
        if ($this->file->filearea === 'content') {
            return $this->get_standard_file_download_url();
        }
        return parent::get_file_download_url();
    }

    /**
     * Checks if embedded files have been used
     *
     * @return bool|null
     * @throws dml_exception
     */
    protected function is_file_used() : ?bool {
        // File areas = intro, content.
        global $DB;
        if ($this->file->filearea === 'content') {
            $sql = "SELECT m.*
                      FROM {page} m
                      JOIN {course_modules} cm ON cm.instance = m.id
                      JOIN {context} ctx ON ctx.instanceid = cm.id
                     WHERE ctx.id = ?";
            $page = $DB->get_record_sql($sql, [$this->file->contextid]);
            return $this->is_embedded_file_used($page, 'content', $this->file->filename);
        }
        return parent::is_file_used();
    }
}
