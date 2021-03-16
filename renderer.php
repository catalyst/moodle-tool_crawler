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
use tool_crawler\table\course_links;

/**
 * Render course links table
 *
 * @package tool_crawler
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_crawler_renderer extends plugin_renderer_base {

    /**
     * Render course links table.
     *
     * @param course_links $courselinks
     * @return false|string
     */
    public function render_course_links(course_links $courselinks) {
        ob_start();
        $courselinks->out($courselinks->pagesize, false);
        $o = ob_get_contents();
        ob_end_clean();
        return $o;
    }

}
