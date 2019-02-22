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
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Render a link
 *
 * @param string $url a URL link
 * @param string $label the a tag label
 * @param string $redirect The final URL if a redirect was served
 * @return html output
 */
function tool_crawler_link($url, $label, $redirect = '') {
    $html = html_writer::link(new moodle_url('url.php', array('url' => $url)), $label) .
            ' ' .
            html_writer::link($url, '↗', array('target' => 'link')) .
            '<br><small>' . htmlspecialchars($url) . '</small>';

    if ($redirect) {
        $linkhtmlsnippet = html_writer::link($redirect, htmlspecialchars($redirect, ENT_NOQUOTES | ENT_HTML5));
        $html .= "<br>" . get_string('redirect', 'tool_crawler', array('redirectlink' => $linkhtmlsnippet));
    }

    return $html;
}

/**
 * Get a html code chunk
 *
 * @param integer $row row
 * @return html chunk
 */
function tool_crawler_http_code($row) {
    $msg = isset($row->httpmsg) ? $row->httpmsg : '?';
    $code = $row->httpcode;
    $cc = substr($code, 0, 1);
    $code = "$msg<br><small class='link-$cc"."xx'>$code</small>";
    return $code;
}

/**
 * Formats a number according to the current user’s locale.
 *
 * @param float $number Numeric value to format.
 * @param int $decimals Number of decimals after the decimal separator. Defaults to 0.
 * @return string String with number formatted as per user’s locale.
 */
function tool_crawler_numberformat(float $number, int $decimals = 0) {
    $decsep = get_string('decsep', 'langconfig');
    $thousandssep = get_string('thousandssep', 'langconfig');

    return number_format($number, $decimals, $decsep, $thousandssep);
}
