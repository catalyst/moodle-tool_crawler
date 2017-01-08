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
 * Quick access tabs
 *
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$reports = array('queued', 'recent', 'broken', 'oversize');

$tabs = '<p>';
if (isset($courseid) && $courseid) {
    foreach ($reports as $rpt) {
        if ($rpt != 'queued') {
            $tabs .= ' | ';
        }
        $wrap = (isset($report) && $report == $rpt) ? 'b' : 'span';
        $tabs .= html_writer::start_tag($wrap);
        $tabs .= html_writer::link(new moodle_url('/admin/tool/crawler/report.php',
                array('report' => $rpt, 'course' => $courseid )),
                get_string($rpt, 'tool_crawler'));
        $tabs .= html_writer::end_tag($wrap);
    }
} else {

    $section = optional_param('section', '', PARAM_RAW);
    $wrap = ($section == 'tool_crawler') ? 'b' : 'span';
    $tabs .= html_writer::start_tag($wrap);
    $tabs .= html_writer::link(new moodle_url("/admin/settings.php?section=tool_crawler"),
        get_string('settings', 'tool_crawler'));
    $tabs .= html_writer::end_tag($wrap);

    $tabs .= ' | ';

    $wrap = (isset($report) && $report == 'index') ? 'b' : 'span';
    $tabs .= html_writer::start_tag($wrap);
    $tabs .= html_writer::link(new moodle_url("/admin/tool/crawler/index.php"),
            get_string('status', 'tool_crawler'));
    $tabs .= html_writer::end_tag($wrap);

    foreach ($reports as $rpt) {
        $tabs .= ' | ';
        $wrap = (isset($report) && $report == $rpt) ? 'b' : 'span';
        $tabs .= html_writer::start_tag($wrap);
        $tabs .= html_writer::link(new moodle_url('/admin/tool/crawler/report.php', array('report' => $rpt )),
                get_string($rpt, 'tool_crawler'));
        $tabs .= html_writer::end_tag($wrap);
    }
}
$tabs .= '</p>';

