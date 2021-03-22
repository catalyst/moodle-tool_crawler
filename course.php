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
 *
 * @package tool_crawler
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('./locallib.php');

defined('MOODLE_INTERNAL') || die();


$courseid = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course, false);
require_capability('tool/crawler:courseconfig', context_course::instance($courseid));

$title = get_string('pluginname', 'tool_crawler');
$heading = get_string('pluginname', 'tool_crawler');
$url = new moodle_url('/admin/tool/crawler/course.php', ['id' => $course->id]);

$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading($heading);

$queuecourse = \tool_crawler\helper::get_queue_course($courseid);

$mform = new tool_crawler\form\courselinkchecker($url, ['course' => $course, 'queuecourse' => $queuecourse]);
if ($mform->is_cancelled()) {
    redirect($courseurl);
} else if ($data = $mform->get_data()) {
    if (!empty($data->addcourse) || !empty($data->resetcourse)) {
        \tool_crawler\helper::queue_course($course->id);
    } else if (!empty($data->stopcourse)) {
        \tool_crawler\helper::dequeue_course($course->id);
    }
    redirect($url);
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    if (!empty($queuecourse)) {
        list($eta, $duration, $progress) = \tool_crawler\helper::calculate_progress($queuecourse->courseid);
        $html = '';
        if (!empty($progress)) {
            $br = "<br/>";
            $html .= "Progress: $progress $br";
            $html .= "Duration: $duration $br";
            $html .= "ETA: $eta $br";
            $runcrawlerurl = new moodle_url('/admin/tool/crawler/coursereport.php', ['courseid' => $course->id]);
            $html .= html_writer::link($runcrawlerurl, get_string('coursereport', 'tool_crawler'));
        } else {
            $html = get_string('onqueue', 'tool_crawler');
        }
        echo $OUTPUT->notification($html, 'info');
    }

    $mform->display();
    echo $OUTPUT->footer();
}
