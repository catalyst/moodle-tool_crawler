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
admin_externalpage_setup('local_linkchecker_robot_status');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('status', 'local_linkchecker_robot'));

$report     = optional_param('report',  '', PARAM_ALPHANUMEXT);
$page       = optional_param('page',    0,  PARAM_INT);
$perpage    = optional_param('perpage', 50, PARAM_INT);
$start = $page * $perpage;

if ($report == 'broken') {

    $sql = "
          FROM {linkchecker_url}  b
     LEFT JOIN {linkchecker_edge} l ON l.b = b.id
     LEFT JOIN {linkchecker_url}  a ON l.a = a.id
     LEFT JOIN {course} c ON c.id = a.courseid
         WHERE b.httpcode != ?";
    $data  = $DB->get_records_sql("SELECT b.id,
                                          b.url broken,
                                          a.*,
                                          c.shortname"       . $sql, array('200'), $start, $perpage);
    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, array('200'));

    $table = new html_table();

    echo "<p>Found ".$count;

    $table->head = array('Broken URL', 'From page', 'Course');

    $table->data = array();
    foreach ($data as $row){
        $table->data[] = array(
            html_writer::link($row->broken, $row->broken),
            html_writer::link($row->url, $row->url),
            $row->shortname,
        );
    }

    echo html_writer::table($table);

    $baseurl = new moodle_url('/local/linkchecker_robot/report.php', array('perpage' => $perpage, 'report' => $report));
    echo $OUTPUT->paging_bar($count, $page, $perpage, $baseurl);


}

echo $OUTPUT->footer();

