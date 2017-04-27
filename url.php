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
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');

require_login(null, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = required_param('url', PARAM_RAW);
$navurl = new moodle_url('/admin/tool/crawler/url.php', array(
    'url' => $url
));
$PAGE->set_context($context);
$PAGE->set_url($navurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('urldetails', 'tool_crawler') );

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('urldetails', 'tool_crawler'));
echo '<p>' . get_string('urldetails_help', 'tool_crawler') . '</p>';

$urlrec = $DB->get_record('tool_crawler_url', array('url' => $url));
echo '<h2>' . tool_crawler_link($url, $urlrec->title, $urlrec->redirect) . '</h2>';

echo '<h3>' . get_string('outgoingurls', 'tool_crawler') . '</h3>';

$data  = $DB->get_records_sql("
     SELECT concat(l.a, '-', l.b) AS id,
            l.text,
            l.idattr,
            t.url target,
            t.title,
            t.redirect,
            t.httpmsg,
            t.httpcode,
            t.filesize,
            t.lastcrawled,
            t.mimetype,
            t.external,
            t.courseid,
            c.shortname
       FROM {tool_crawler_edge} l
       JOIN {tool_crawler_url} f ON f.id = l.a
       JOIN {tool_crawler_url} t ON t.id = l.b
  LEFT JOIN {course} c ON c.id = t.courseid
      WHERE f.url = ?
   ORDER BY f.lastcrawled DESC
", array($url));


/**
 * Print a nice table
 *
 * @param array $data table
 * @return html output
 */
function print_table($data) {

    $table = new html_table();
    $table->head = array(
        get_string('lastcrawledtime', 'tool_crawler'),
        get_string('linktext', 'tool_crawler'),
        get_string('idattr', 'tool_crawler'),
        get_string('response', 'tool_crawler'),
        get_string('size', 'tool_crawler'),
        get_string('url', 'tool_crawler'),
        get_string('mimetype', 'tool_crawler'),
    );
    $table->data = array();
    foreach ($data as $row) {
        $text = trim($row->title);
        if (!$text || $text == "") {
            $text = get_string('unknown', 'tool_crawler');
        }
        $code = tool_crawler_http_code($row);
        $size = $row->filesize * 1;
        $data = array(
            userdate($row->lastcrawled, '%h %e,&nbsp;%H:%M:%S'),
            $row->text,
            str_replace(' #', '<br>#', $row->idattr),
            $code,
            display_size($size),
            tool_crawler_link($row->target, $text, $row->redirect),
            $row->mimetype,
        );
        $table->data[] = $data;
    }
    echo html_writer::table($table);
}

print_table($data);

echo '<h3>' . get_string('incomingurls', 'tool_crawler') . '</h3>';

$data  = $DB->get_records_sql("
     SELECT concat(l.a, '-', l.b) AS id,
            l.text,
            l.idattr,
            f.url target,
            f.title,
            f.redirect,
            f.httpmsg,
            f.httpcode,
            f.filesize,
            f.lastcrawled,
            f.mimetype,
            f.external,
            f.courseid,
            c.shortname
       FROM {tool_crawler_edge} l
       JOIN {tool_crawler_url} f ON f.id = l.a
       JOIN {tool_crawler_url} t ON t.id = l.b
  LEFT JOIN {course} c ON c.id = f.courseid
      WHERE t.url = ?
   ORDER BY f.lastcrawled DESC
", array($url));

print_table($data);

echo $OUTPUT->footer();

