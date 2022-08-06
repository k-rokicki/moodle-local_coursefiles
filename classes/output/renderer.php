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
 * Output rendering for the plugin.
 *
 * @package     local_coursefiles
 * @copyright   2022 Kacper Rokicki <k.k.rokicki@gmail.com>
 * @copyright   based on work by 2022 Martin Gauk (@innoCampus, TU Berlin)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_coursefiles\output;

use coding_exception;
use dml_exception;
use moodle_exception;
use moodle_url;
use local_coursefiles\course_file;
use local_coursefiles\course_files;
use plugin_renderer_base;
use stdClass;

/**
 * Implements the plugin renderer
 *
 * @copyright 2022 Martin Gauk (@innoCampus, TU Berlin)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Render overview page.
     *
     * @param moodle_url $url
     * @param moodle_url $componenturl
     * @param moodle_url $filetypeurl
     * @param course_files $coursefiles
     * @return string
     * @throws dml_exception|coding_exception|moodle_exception
     */
    public function overview_page(
        moodle_url $url,
        moodle_url $componenturl,
        moodle_url $filetypeurl,
        course_files $coursefiles)
    : string {
        $templatedata = new stdClass();
        $templatedata->component_selection_html = $this->get_component_selection(
            $componenturl,
            $coursefiles->get_components(),
            $coursefiles->get_filter_component()
        );
        $templatedata->file_type_selection_html = $this->get_file_type_selection(
            $filetypeurl,
            $coursefiles->get_filter_file_type()
        );
        $templatedata->url = $url;
        $templatedata->sesskey = sesskey();
        $templatedata->files = array_map(function ($file) {
            $file->filecomponent = course_files::get_component_translation($file->filecomponent);
            return $file;
        }, $coursefiles->get_file_list());
        $templatedata->files_exist = count($templatedata->files) > 0;
        return $this->render_from_template('local_coursefiles/view', $templatedata);
    }

    /**
     * Builds the file component select drop-down menu HTML snippet.
     *
     * @param moodle_url $url
     * @param array $allcomponents
     * @param string $currentcomponent
     * @return string
     */
    public function get_component_selection(
        moodle_url $url,
        array $allcomponents,
        string $currentcomponent)
    : string {
        return $this->output->single_select(
            $url,
            'component',
            $allcomponents,
            $currentcomponent,
            null,
            'componentselector'
        );
    }

    /**
     * Builds the file type select drop-down menu HTML snippet.
     *
     * @param moodle_url $url
     * @param string $currenttype
     * @return string
     * @throws coding_exception
     */
    public function get_file_type_selection(moodle_url $url, string $currenttype) : string {
        return $this->output->single_select(
            $url,
            'filetype',
            course_files::get_file_types(),
            $currenttype,
            null,
            'filetypeselector'
        );
    }
}
