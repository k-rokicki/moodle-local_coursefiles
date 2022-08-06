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

namespace local_coursefiles;

use coding_exception;
use lang_string;
use function get_string;

/**
 * Class mimetypes
 * @package    local_coursefiles
 * @copyright  2017 Martin Gauk (@innoCampus, TU Berlin)
 * @author     Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mimetypes {
    /**
     * Mapping of file types to possible mime types.
     */
    static protected array $mimetypes = array(
        'document' => array('application/epub+zip', 'application/msword', 'application/pdf',
            'application/postscript', 'application/vnd.ms-%', 'application/vnd.oasis.opendocument%',
            'application/vnd.openxmlformats-officedocument%', 'application/vnd.sun.xml%',
            'application/x-digidoc', 'application/xhtml+xml', 'application/x-javascript',
            'application/x-latex', 'application/xml', 'application/x-ms%', 'application/x-tex%',
            'document%', 'spreadsheet', 'text/%'),
        'image' => array('image/%'),
        'audio' => array('audio/%'),
        'video' => array('video/%'),
        'archive' => array('application/zip', 'application/x-tar', 'application/g-zip',
            'application/x-rar-compressed', 'application/x-7z-compressed', 'application/vnd.moodle.backup'),
        'hvp' => array('application/zip.h5p'),
    );

    /**
     * mimetypes constructor.
     */
    public function __construct() {
        self::check_config_mimetypes();
    }

    /**
     * Try to get the name of the file type in the user's lang
     *
     * @param string $mimetype
     * @return lang_string|string
     * @throws coding_exception
     */
    public static function get_file_type_translation(string $mimetype) {
        foreach (self::$mimetypes as $name => $types) {
            foreach ($types as $mime) {
                if ($mime === $mimetype ||
                    (substr($mime, -1) === '%' && strncmp($mime, $mimetype, strlen($mime) - 1) === 0)) {
                    return get_string('filetype:' . $name, 'local_coursefiles');
                }
            }
        }

        return $mimetype;
    }

    /**
     * Getter for mime types
     * @return array|string[][]
     */
    public static function get_mime_types(): array {
        return self::$mimetypes;
    }

    /**
     * Check if the predefined list of mimetypes should be overridden.
     */
    public static function check_config_mimetypes() {
        global $CFG;

        if (isset($CFG->filemimetypes)) {
            self::$mimetypes = $CFG->filemimetypes;
        }
    }
}
