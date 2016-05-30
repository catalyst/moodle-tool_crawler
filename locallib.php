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
 * Helper lib
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function local_linkchecker_robot_link($url, $label) {
    return html_writer::link(new moodle_url('url.php', array('url' => $url)), $label) .
            ' ' .
            html_writer::link($url, '↗', array('target' => 'link')) .
            '<br><small>' . $url . '</small>';
}

/**
 * Get a html code chunk
 *
 * @param integer $row row
 * @return html chunk
 */
function local_linkchecker_robot_http_code($row) {
    $msg = isset($row->httpmsg) ? $row->httpmsg : '?';
    $code = $row->httpcode;
    $cc = substr($code, 0, 1);
    $code = "$msg<br><small class='link-$cc"."xx'>$code</small>";
    return $code;
}

