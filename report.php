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
    $data  = $DB->get_records_sql("SELECT b.id || '-' || a.id,
                                          b.url broken,
                                          l.text,
                                          a.*,
                                          c.shortname"       . $sql, array('200'), $start, $perpage);
    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, array('200'));

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();
    $table->head = array('Broken URL', 'From page', 'Title', 'Course', 'Link text');
    $table->data = array();
    foreach ($data as $row) {
        $table->data[] = array(
            html_writer::link($row->broken, $row->broken),
            html_writer::link($row->url, substr($row->url, $mdlw) ),
            $row->title,
            $row->shortname,
            $row->text,
        );
    }

} else if ($report == 'oversize') {

    $sql = "
          FROM {linkchecker_url}  b
     LEFT JOIN {linkchecker_edge} l ON l.b = b.id
     LEFT JOIN {linkchecker_url}  a ON l.a = a.id
     LEFT JOIN {course} c ON c.id = a.courseid
         WHERE b.filesize > ?";
    $opts = array('2000');
    $data  = $DB->get_records_sql("SELECT b.id || '-' || a.id,
                                          b.url target,
                                          b.filesize,
                                          l.text,
                                          a.title,
                                          a.url,
                                          c.shortname"       . $sql . " ORDER BY b.filesize DESC", $opts, $start, $perpage);
    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, $opts);

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();
    $table->head = array('Size', 'URL', 'From page', 'Title', 'Course');
    $table->data = array();
    foreach ($data as $row) {
        $size = $row->filesize * 1;
        $text = trim($row->text);
        if (!$text || $text == ""){
            $text = 'missin';
        }
        $table->data[] = array(
            $size > 1048576 ? (round(100 * $size / 1048576 ) * .01 . 'MB') :
            ($size > 1024   ? (round(10  * $size / 1024    ) * .1  . 'KB') : $size . 'B'),
            html_writer::link($row->target, $text),
            html_writer::link($row->url, substr($row->url, $mdlw) ),
            $row->title,
            $row->shortname,
        );
    }

}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($report, 'local_linkchecker_robot'));
echo "<p>Found ".$count;
echo html_writer::table($table);
$baseurl = new moodle_url('/local/linkchecker_robot/report.php', array('perpage' => $perpage, 'report' => $report));
echo $OUTPUT->paging_bar($count, $page, $perpage, $baseurl);
echo $OUTPUT->footer();

