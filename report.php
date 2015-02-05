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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_capability('moodle/site:config', context_system::instance());

$report     = optional_param('report',  '', PARAM_ALPHANUMEXT);
$page       = optional_param('page',    0,  PARAM_INT);
$perpage    = optional_param('perpage', 50, PARAM_INT);
$start = $page * $perpage;

admin_externalpage_setup('local_linkchecker_robot_'.$report);

if ($report == 'broken') {

    $sql = "
          FROM {linkchecker_url}  b
     LEFT JOIN {linkchecker_edge} l ON l.b = b.id
     LEFT JOIN {linkchecker_url}  a ON l.a = a.id
     LEFT JOIN {course} c ON c.id = a.courseid
         WHERE b.httpcode != ?";
    $opts = array('200');
    $data  = $DB->get_records_sql("SELECT b.id || '-' || a.id,
                                          b.url target,
                                          b.httpcode,
                                          l.text,
                                          a.url,
                                          a.title,
                                          a.courseid,
                                          c.shortname" . $sql . " ORDER BY httpcode DESC, c.shortname ASC", $opts, $start, $perpage);
    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, $opts);

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();
    $table->head = array('Code', 'Broken URL', 'From page', 'Course');
    $table->data = array();
    foreach ($data as $row) {
        $text = trim($row->text);
        if (!$text || $text == ""){
            $text = 'Missing';
        }
        $table->data[] = array(
            $row->httpcode,
            html_writer::link($row->target, $text) .
            '<br><small>' . $row->target . '</small>',
            html_writer::link($row->url, $row->title),
            html_writer::link('/course/view.php?id='.$row->courseid, $row->shortname),
        );
    }

} else if ($report == 'recent') {

    $sql = "
          FROM {linkchecker_url}  b
     LEFT JOIN {course} c ON c.id = b.courseid
         WHERE b.lastcrawled IS NOT NULL";
    $opts = array();
    $data  = $DB->get_records_sql("SELECT b.id,
                                          b.url target,
                                          b.lastcrawled,
                                          b.filesize,
                                          b.httpcode,
                                          b.title,
                                          b.mimetype,
                                          b.courseid,
                                          c.shortname"       . $sql . " ORDER BY b.lastcrawled DESC", $opts, $start, $perpage);
    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, $opts);

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();
    $table->head = array('Last crawled', 'Code', 'Size', 'URL', 'Type', 'Course');
    $table->data = array();
    foreach ($data as $row) {
        $text = trim($row->title);
        if (!$text || $text == ""){
            $text = 'UNKNOWN';
        }
        $size = $row->filesize * 1;
        $table->data[] = array(
            userdate($row->lastcrawled, '%y/%m/%d,&nbsp;%H:%M:%S'),
            $row->httpcode,
            $size > 1000000 ? (round(100 * $size / 1000000 ) * .01 . 'MB') :
            ($size > 1000   ? (round(10  * $size / 1000    ) * .1  . 'KB') : $size . 'B'),
            html_writer::link($row->target, $text) .
            '<br><small>' . $row->target . '</small>',
            $row->mimetype,
            html_writer::link('/course/view.php?id='.$row->courseid, $row->shortname),
        );
    }

} else if ($report == 'oversize') {

    $sql = "
          FROM {linkchecker_url}  b
     LEFT JOIN {linkchecker_edge} l ON l.b = b.id
     LEFT JOIN {linkchecker_url}  a ON l.a = a.id
     LEFT JOIN {course} c ON c.id = a.courseid
         WHERE b.filesize > ?";
    $opts = array('150000');
    $data  = $DB->get_records_sql("SELECT b.id || '-' || a.id,
                                          b.url target,
                                          b.filesize,
                                          l.text,
                                          a.title,
                                          a.url,
                                          a.courseid,
                                          c.shortname"       . $sql . " ORDER BY b.filesize DESC", $opts, $start, $perpage);
    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, $opts);

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();
    $table->head = array('Size', 'Slow URL', 'From page', 'Course');
    $table->data = array();
    foreach ($data as $row) {
        $size = $row->filesize * 1;
        $text = trim($row->text);
        if (!$text || $text == ""){
            $text = 'Missing';
        }
        $table->data[] = array(
            $size > 1000000 ? (round(100 * $size / 1000000 ) * .01 . 'MB') :
            ($size > 1000   ? (round(10  * $size / 1000    ) * .1  . 'KB') : $size . 'B'),
            html_writer::link($row->target, $text) .
            '<br><small>' . $row->target . '</small>',
            html_writer::link($row->url, $row->title),
            html_writer::link('/course/view.php?id='.$row->courseid, $row->shortname),
        );
    }

}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($report, 'local_linkchecker_robot'));
echo "<p>Found " . $count. "</p>";
echo "<p>NOTE: Duplicate URL's with multiple incoming links are only scraped once.</p>";
echo html_writer::table($table);
$baseurl = new moodle_url('/local/linkchecker_robot/report.php', array('perpage' => $perpage, 'report' => $report));
echo $OUTPUT->paging_bar($count, $page, $perpage, $baseurl);
echo $OUTPUT->footer();

