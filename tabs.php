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
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$reports = array('queued', 'recent', 'broken', 'oversize');

$tabs = '<p>';
if (isset($courseid) && $courseid) {
    foreach ($reports as $rpt) {
        if ($rpt != 'queued') {
            $tabs .= ' | ';
        }
        if (isset($report) && $report == $rpt) {
            $tabs .= '<b>' . html_writer::link(new moodle_url('report.php', array('report' => $rpt, 'course' => $courseid )),
                get_string($rpt, 'local_linkchecker_robot')) . '</b>';
        } else {
            $tabs .= html_writer::link(new moodle_url('report.php', array('report' => $rpt, 'course' => $courseid )),
                get_string($rpt, 'local_linkchecker_robot'));
        }
    }
} else {
    $tabs .= html_writer::link("/admin/settings.php?section=local_linkchecker_robot",
        get_string('settings', 'local_linkchecker_robot'));
    $tabs .= ' | ';
    $tabs .= html_writer::link("index.php", get_string('status', 'local_linkchecker_robot'));
    foreach ($reports as $rpt) {
        $tabs .= ' | ';
        if (isset($report) && $report == $rpt) {
            $tabs .= '<b>' . html_writer::link(new moodle_url('report.php', array('report' => $rpt )),
                get_string($rpt, 'local_linkchecker_robot')) . '</b>';
        } else {
            $tabs .= html_writer::link(new moodle_url('report.php', array('report' => $rpt )),
                get_string($rpt, 'local_linkchecker_robot'));
        }
    }
}
$tabs .= '</p>';

