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
 * URL detail page
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = required_param('url', PARAM_RAW);
$navurl = new moodle_url('/local/linkchecker_robot/url.php', array(
    'url' => $url
));
$PAGE->set_context($context);
$PAGE->set_url($navurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('urldetails', 'local_linkchecker_robot') );

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('urldetails', 'local_linkchecker_robot'));
echo '<p>' . get_string('urldetails_help', 'local_linkchecker_robot') . '</p>';

$urlrec = $DB->get_record('linkchecker_url', array('url' => $url));
echo '<h2>' . local_linkchecker_robot_link($url, $urlrec->title) . '</h2>';

echo '<h3>' . get_string('outgoingurls', 'local_linkchecker_robot') . '</h3>';

$data  = $DB->get_records_sql("
     SELECT concat(l.a, '-', l.b) AS id,
            l.text,
            t.url target,
            t.title,
            t.httpmsg,
            t.httpcode,
            t.filesize,
            t.lastcrawled,
            t.mimetype,
            t.external,
            t.courseid,
            c.shortname
       FROM {linkchecker_edge} l
       JOIN {linkchecker_url} f ON f.id = l.a
       JOIN {linkchecker_url} t ON t.id = l.b
  LEFT JOIN {course} c ON c.id = t.courseid
      WHERE f.url = ?
", array($url));

$table = new html_table();
$table->head = array(
    get_string('lastcrawledtime', 'local_linkchecker_robot'),
    get_string('linktext', 'local_linkchecker_robot'),
    get_string('response', 'local_linkchecker_robot'),
    get_string('size', 'local_linkchecker_robot'),
    get_string('url', 'local_linkchecker_robot'),
    get_string('mimetype', 'local_linkchecker_robot'),
);
$table->data = array();
foreach ($data as $row) {
    $text = trim($row->title);
    if (!$text || $text == "") {
        $text = get_string('unknown', 'local_linkchecker_robot');
    }
    $code = local_linkchecker_robot_http_code($row);
    $size = $row->filesize * 1;
    $data = array(
        userdate($row->lastcrawled, '%h %e,&nbsp;%H:%M:%S'),
        $row->text,
        $code,
        display_size($size),
        local_linkchecker_robot_link($row->target, $text),
        $row->mimetype,
    );
    $table->data[] = $data;
}
echo html_writer::table($table);

echo '<h3>' . get_string('incomingurls', 'local_linkchecker_robot') . '</h3>';

$data  = $DB->get_records_sql("
     SELECT concat(l.a, '-', l.b) AS id,
            l.text,
            f.url target,
            f.title,
            f.httpmsg,
            f.httpcode,
            f.filesize,
            f.lastcrawled,
            f.mimetype,
            f.external,
            f.courseid,
            c.shortname
       FROM {linkchecker_edge} l
       JOIN {linkchecker_url} f ON f.id = l.a
       JOIN {linkchecker_url} t ON t.id = l.b
  LEFT JOIN {course} c ON c.id = f.courseid
      WHERE t.url = ?
", array($url));
$table->data = array();
foreach ($data as $row) {
    $text = trim($row->title);
    if (!$text || $text == "") {
        $text = get_string('unknown', 'local_linkchecker_robot');
    }
    $code = local_linkchecker_robot_http_code($row);
    $size = $row->filesize * 1;
    $data = array(
        userdate($row->lastcrawled, '%h %e,&nbsp;%H:%M:%S'),
        $row->text,
        $code,
        display_size($size),
        local_linkchecker_robot_link($row->target, $text),
        $row->mimetype,
    );
    $table->data[] = $data;
}
echo html_writer::table($table);
echo $OUTPUT->footer();

