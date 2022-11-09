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
 * Report page at course level
 *
 * @package tool_crawler
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once('./locallib.php');
defined('MOODLE_INTERNAL') || die();

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$url = new moodle_url('/admin/tool/crawler/coursereport.php', ['courseid' => $courseid]);
$course = get_course($courseid);
require_login($course, false);
require_capability('tool/crawler:courseconfig', context_course::instance($courseid));

$title = get_string('pluginname', 'tool_crawler');
$heading = get_string('coursereport', 'tool_crawler');

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading($heading);

$table = new \tool_crawler\table\course_links('course_links', $url, $courseid, $page);
$output = $PAGE->get_renderer('tool_crawler');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('numberurlsfound', 'tool_crawler',
    array(
        'reports_number' => \tool_crawler\helper::count_broken_links($courseid),
        'report_type' => 'broken'
    )
));
echo get_string( 'broken_header', 'tool_crawler');
$runcrawlerurl = new moodle_url('/admin/tool/crawler/course.php', ['id' => $course->id]);
echo html_writer::link($runcrawlerurl, get_string('addcourse', 'tool_crawler'));
echo $output->render($table);
echo $OUTPUT->footer();
