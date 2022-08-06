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
 * Internal API of local_coursefiles.
 *
 * @package    local_coursefiles
 * @copyright  2022 Kacper Rokicki <k.k.rokicki@gmail.com>
 * @copyright  based on work by 2017 Martin Gauk (@innoCampus, TU Berlin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursefiles;

use coding_exception;
use context;
use course_modinfo;
use dml_exception;
use lang_string;
use moodle_exception;
use zip_packer;
use function get_string;

/**
 * Class course_files
 * @package local_coursefiles
 */
class course_files {

    protected context $context;
    protected ?array $components = null;
    protected ?array $filelist = null;
    protected string $filtercomponent;
    protected string $filterfiletype;
    protected course_modinfo $coursemodinfo;
    protected int $courseid;

    /**
     * course_files constructor.
     * @param int $courseid
     * @param context $context
     * @param string $component
     * @param string $filetype
     * @throws moodle_exception
     */
    public function __construct(int $courseid, context $context, string $component, string $filetype) {
        $this->courseid = $courseid;
        $this->context = $context;
        $this->filtercomponent = $component;
        $this->filterfiletype = $filetype;
        $this->coursemodinfo = get_fast_modinfo($courseid);
    }

    /**
     * Get course id.
     *
     * @return int
     */
    public function get_course_id() : int {
        return $this->courseid;
    }

    /**
     * Get filter component name.
     *
     * @return string
     */
    public function get_filter_component() : string {
        return $this->filtercomponent;
    }

    /**
     * Get filter file type name.
     *
     * @return string
     */
    public function get_filter_file_type() : string {
        return $this->filterfiletype;
    }

    /**
     * Retrieve the files within a course/context available to user.
     *
     * @param bool $ignorefilters Whether filters should be ignored and all available files should be returned.
     * @return array
     * @throws dml_exception|coding_exception|moodle_exception
     */
    public function get_file_list(bool $ignorefilters = false): ?array {
        global $DB;

        if ($this->filelist !== null) {
            return $this->filelist;
        }

        $sqlwhere = '';
        $sqlwherecomponent = '';

        if ($ignorefilters != true) {
            if ($this->filtercomponent == 'all') {
                $sqlwhere .= 'AND f.component NOT LIKE :component';
                $sqlwherecomponent = 'assign%';
            } else {
                $availcomponents = $this->get_components();
                if (isset($availcomponents[$this->filtercomponent])) {
                    $sqlwhere .= 'AND f.component LIKE :component';
                    $sqlwherecomponent = $this->filtercomponent;
                }
            }

            if ($this->filterfiletype === 'other') {
                $sqlwhere .= ' AND ' . $this->get_sql_mimetype(array_keys(mimetypes::get_mime_types()), false);
            } else if (isset(mimetypes::get_mime_types()[$this->filterfiletype])) {
                $sqlwhere .= ' AND ' . $this->get_sql_mimetype($this->filterfiletype, true);
            }
        }

        $sql = 'FROM {files} f
                LEFT JOIN {context} c ON (c.id = f.contextid)
                WHERE f.filename NOT LIKE \'.\'
                    AND (c.path LIKE :path OR c.id = :cid) ' . $sqlwhere;

        $sqlselectfiles = 'SELECT f.*, c.contextlevel, c.instanceid' .
        ' ' . $sql . ' ORDER BY f.component, f.filename';

        $params = array(
            'path' => $this->context->path . '/%',
            'cid' => $this->context->id,
            'component' => $sqlwherecomponent,
        );

        $records = $DB->get_records_sql($sqlselectfiles, $params);

        $records = array_filter($records, function($file) {
            $cm = $this->coursemodinfo->cms[$file->instanceid];
            return $cm->available && $cm->uservisible;
        });

        $files = array();
        foreach ($records as $rec) {
            $file = course_file::create($rec);
            if ($file->fileused) {
                $files[] = $file;
            }
        }

        if ($ignorefilters != true) {
            $this->filelist = $files;
        }
        return $files;
    }

    /**
     * Creates an SQL snippet
     *
     * @param mixed $types
     * @param bool $in
     * @return string
     */
    protected function get_sql_mimetype($types, bool $in): string {
        if (is_array($types)) {
            $list = array();
            foreach ($types as $type) {
                $list = array_merge($list, mimetypes::get_mime_types()[$type]);
            }
        } else {
            $list = &mimetypes::get_mime_types()[$types];
        }

        if ($in) {
            $first = "(f.mimetype LIKE '";
            $glue = "' OR f.mimetype LIKE '";
        } else {
            $first = "(f.mimetype NOT LIKE '";
            $glue = "' AND f.mimetype NOT LIKE '";
        }

        return $first . implode($glue, $list) . "')";
    }

    /**
     * Get all available components with files.
     * @return array
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public function get_components(): ?array {
        if ($this->components !== null) {
            return $this->components;
        }

        $filelist = $this->get_file_list(true);

        foreach ($filelist as $file) {
            $this->components[$file->filecomponent] = self::get_component_translation($file->filecomponent);
        }

        asort($this->components, SORT_STRING | SORT_FLAG_CASE);
        $componentsall = array(
            'all' => get_string('allcomponents', 'local_coursefiles')
        );

        $this->components = $componentsall + $this->components;
        return $this->components;
    }

    /**
     * Check given files whether they are available to the current user.
     *
     * @param array $files records from the files table left join files_reference table
     * @return array files that are available
     * @throws dml_exception|coding_exception|moodle_exception
     */
    protected function check_files(array $files): array {
        $availablefileids = array_map(function ($file) {
            return $file->get_file()->id;
        }, $this->get_file_list(true));
        $checkedfiles = array();
        foreach ($files as $file) {
            if (in_array($file->id, $availablefileids)) {
                $checkedfiles[] = $file;
            }
        }
        return $checkedfiles;
    }

    /**
     * Download a zip file of the files with the given ids.
     *
     * This function does not return if the zip archive could be created.
     *
     * @param array $fileids file ids
     * @throws moodle_exception
     */
    public function download_files(array $fileids) {
        global $DB, $CFG;

        if (count($fileids) == 0) {
            throw new moodle_exception('nofileselected', 'local_coursefiles');
        }

        list($sqlin, $paramfids) = $DB->get_in_or_equal(array_keys($fileids), SQL_PARAMS_QM);
        $sql = 'SELECT f.*, r.repositoryid, r.reference, r.lastsync AS referencelastsync
                FROM {files} f
                LEFT JOIN {files_reference} r ON (f.referencefileid = r.id)
                WHERE f.id ' . $sqlin;
        $res = $DB->get_records_sql($sql, $paramfids);

        $checkedfiles = $this->check_files($res);
        $fs = get_file_storage();
        $filesforzipping = array();
        foreach ($checkedfiles as $file) {
            $fname = $this->get_unique_file_name($file->filename, $filesforzipping);
            $filesforzipping[$fname] = $fs->get_file_instance($file);
        }

        $filename = clean_filename($this->coursemodinfo->get_course()->fullname . '.zip');
        $tmpfile = tempnam($CFG->tempdir . '/', 'local_coursefiles');
        $zip = new zip_packer();
        if ($zip->archive_to_pathname($filesforzipping, $tmpfile)) {
            send_temp_file($tmpfile, $filename);
        }
    }

    /**
     * Generate a unique file name for storage.
     *
     * If a file does already exist with $filename in $existingfiles as key,
     * a number in parentheses is appended to the file name.
     *
     * @param string $filename
     * @param array $existingfiles
     * @return string unique file name
     */
    protected function get_unique_file_name(string $filename, array $existingfiles): string {
        $name = clean_filename($filename);

        $lastdot = strrpos($name, '.');
        if ($lastdot === false) {
            $filename = $name;
            $extension = '';
        } else {
            $filename = substr($name, 0, $lastdot);
            $extension = substr($name, $lastdot);
        }

        $i = 1;
        while (isset($existingfiles[$name])) {
            $name = $filename . '(' . $i++ . ')' . $extension;
        }

        return $name;
    }

    /**
     * Collate an array of available file types
     *
     * @return array
     * @throws coding_exception
     */
    public static function get_file_types(): array {
        $types = array('all' => get_string('filetype:all', 'local_coursefiles'));
        foreach (array_keys(mimetypes::get_mime_types()) as $type) {
            $types[$type] = get_string('filetype:' . $type, 'local_coursefiles');
        }
        $types['other'] = get_string('filetype:other', 'local_coursefiles');
        return $types;
    }

    /**
     * Try to get the name of the file component in the user's lang.
     *
     * @param string $name
     * @return lang_string|string
     * @throws coding_exception
     */
    public static function get_component_translation(string $name) {
        if (get_string_manager()->string_exists('pluginname', $name)) {
            return get_string('pluginname', $name);
        } else if (get_string_manager()->string_exists($name, '')) {
            return get_string($name);
        }
        return $name;
    }
}
