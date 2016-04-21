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
 * Admin report GUI
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$report     = optional_param('report',  '', PARAM_ALPHANUMEXT);
$page       = optional_param('page',    0,  PARAM_INT);
$perpage    = optional_param('perpage', 50, PARAM_INT);
$courseid   = optional_param('course',  0,  PARAM_INT);
$retryid    = optional_param('retryid', 0,  PARAM_INT);
$start = $page * $perpage;

$sqlfilter = '';

$navurl = new moodle_url('/local/linkchecker_robot/report.php', array(
    'report' => $report,
    'course' => $courseid
));
$baseurl = new moodle_url('/local/linkchecker_robot/report.php', array(
    'perpage' => $perpage,
    'report' => $report,
    'course' => $courseid
));

$config = get_config('local_linkchecker_robot');

/**
 * Get a html code chunk
 *
 * @param integer $row row
 * @return html chunk
 */
function http_code($row) {
    $msg = isset($row->httpmsg) ? $row->httpmsg : '?';
    $code = $row->httpcode;
    $cc = substr($code, 0, 1);
    $code = "$msg<br><small class='link-$cc"."xx'>$code</small>";
    return $code;
}

if ($courseid) {
    // If course then this is an a course editor report.
    $course = get_course($courseid);
    require_login($course);

    $coursecontext = context_course::instance($courseid);
    require_capability('moodle/course:update', $coursecontext);

    $PAGE->set_context($coursecontext);
    $PAGE->set_url($navurl);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title( get_string($report, 'local_linkchecker_robot') );
    $sqlfilter = ' AND c.id = '.$courseid;

} else {

    // If no course then this is an admin only report.
    require_capability('moodle/site:config', context_system::instance());
    admin_externalpage_setup('local_linkchecker_robot_'.$report);
}
echo $OUTPUT->header();

$reports = array('queued', 'recent', 'broken', 'oversize');
echo '<p>';
if ($courseid) {
    foreach ($reports as $rpt) {
        if ($rpt != 'queued') {
            echo ' | ';
        }
        if ($report == $rpt) {
            echo '<b>' . html_writer::link(new moodle_url('report.php', array('report' => $rpt, 'course' => $courseid )),
                get_string($rpt, 'local_linkchecker_robot')) . '</b>';
        } else {
            echo html_writer::link(new moodle_url('report.php', array('report' => $rpt, 'course' => $courseid )),
                get_string($rpt, 'local_linkchecker_robot'));
        }
    }
} else {
    echo html_writer::link("/admin/settings.php?section=local_linkchecker_robot",
        get_string('settings', 'local_linkchecker_robot'));
    echo ' | ';
    echo html_writer::link("index.php", get_string('status', 'local_linkchecker_robot'));
    foreach ($reports as $rpt) {
        echo ' | ';
        if ($report == $rpt) {
            echo '<b>' . html_writer::link(new moodle_url('report.php', array('report' => $rpt )),
                get_string($rpt, 'local_linkchecker_robot')) . '</b>';
        } else {
            echo html_writer::link(new moodle_url('report.php', array('report' => $rpt )),
                get_string($rpt, 'local_linkchecker_robot'));
        }
    }
}
echo '</p>';

if ($retryid) {
    $robot = new \local_linkchecker_robot\robot\crawler();
    $robot->reset_for_recrawl($retryid);
}

if ($report == 'broken') {

    $sql = " FROM {linkchecker_url}  b
       LEFT JOIN {linkchecker_edge} l ON l.b = b.id
       LEFT JOIN {linkchecker_url}  a ON l.a = a.id
       LEFT JOIN {course} c ON c.id = a.courseid
           WHERE b.httpcode != ? $sqlfilter";

    $opts = array('200');
    $data  = $DB->get_records_sql("SELECT concat(b.id, '-', l.id, '-', a.id) AS id,
                                          b.url target,
                                          b.httpcode,
                                          b.httpmsg,
                                          b.lastcrawled,
                                          b.id AS toid,
                                          l.id linkid,
                                          l.text,
                                          a.url,
                                          a.title,
                                          a.courseid,
                                          c.shortname $sql
                                 ORDER BY httpcode DESC,
                                          c.shortname ASC",
                                          $opts,
                                          $start,
                                          $perpage);

    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, $opts);

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();
    $table->head = array(
        '',
        get_string('lastcrawledtime', 'local_linkchecker_robot'),
        get_string('response', 'local_linkchecker_robot'),
        get_string('broken', 'local_linkchecker_robot'),
        get_string('frompage', 'local_linkchecker_robot')
    );
    if (!$courseid) {
        array_push($table->head, get_string('course', 'local_linkchecker_robot'));
    }
    $table->data = array();
    foreach ($data as $row) {
        $text = trim($row->text);
        if (!$text || $text == "") {
            $text = get_string('missing', 'local_linkchecker_robot');
        }
        $data = array(
            html_writer::link(new moodle_url($baseurl, array('retryid' => $row->toid )),
                get_string('retry', 'local_linkchecker_robot')),
            userdate($row->lastcrawled, '%h %e,&nbsp;%H:%M:%S'),
            http_code($row),
            html_writer::link($row->target, $text) .
            '<br><small>' . $row->target . '</small>',
            html_writer::link($row->url, $row->title) .
            '<br><small>' . substr($row->url, $mdlw) . '</small>',
        );
        if (!$courseid) {
            array_push($data, html_writer::link('/course/view.php?id='.$row->courseid, $row->shortname) );
        }
        $table->data[] = $data;
    }

} else if ($report == 'queued') {

    $sql = " FROM {linkchecker_url} a
       LEFT JOIN {course} c ON c.id = a.courseid
           WHERE (a.lastcrawled IS NULL OR a.lastcrawled < needscrawl)
                 $sqlfilter";

    $opts = array();
    $data  = $DB->get_records_sql("SELECT a.id,
                                          a.url target,
                                          a.title,
                                          a.lastcrawled,
                                          a.needscrawl,
                                          a.courseid,
                                          c.shortname $sql
                                 ORDER BY a.needscrawl ASC,
                                          a.id ASC",
                                          $opts,
                                          $start,
                                          $perpage);

    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, $opts);

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();

    $table->head = array(
        get_string('whenqueued', 'local_linkchecker_robot'),
        get_string('url', 'local_linkchecker_robot')
    );

    if (!$courseid) {
        array_push($table->head, get_string('incourse', 'local_linkchecker_robot'));
    }
    $table->data = array();
    foreach ($data as $row) {
        $text = trim($row->title);
        if (!$text || $text == "") {
            $text = get_string('notyetknown', 'local_linkchecker_robot');
        }
        $data = array(
            userdate($row->needscrawl, '%h %e,&nbsp;%H:%M:%S'),
            html_writer::link($row->target, $text) .
            '<br><small>' . $row->target . '</small>'
        );
        if (!$courseid) {
            array_push($data, html_writer::link('/course/view.php?id='.$row->courseid, $row->shortname) );
        }
        $table->data[] = $data;
    }

} else if ($report == 'recent') {

    $sql = " FROM {linkchecker_url}  b
       LEFT JOIN {course} c ON c.id = b.courseid
           WHERE b.lastcrawled IS NOT NULL
                 $sqlfilter";

    $opts = array();
    $data  = $DB->get_records_sql("SELECT b.id,
                                          b.url target,
                                          b.lastcrawled,
                                          b.filesize,
                                          b.httpcode,
                                          b.httpmsg,
                                          b.title,
                                          b.mimetype,
                                          b.courseid,
                                          c.shortname
                                          $sql
                                 ORDER BY b.lastcrawled DESC",
                                          $opts,
                                          $start,
                                          $perpage);

    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, $opts);

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();
    $table->head = array(
        get_string('lastcrawledtime', 'local_linkchecker_robot'),
        get_string('response', 'local_linkchecker_robot'),
        get_string('size', 'local_linkchecker_robot'),
        get_string('url', 'local_linkchecker_robot'),
        get_string('mimetype', 'local_linkchecker_robot'),
    );
    if (!$courseid) {
        array_push($table->head, get_string('incourse', 'local_linkchecker_robot'));
    }
    $table->data = array();
    foreach ($data as $row) {
        $text = trim($row->title);
        if (!$text || $text == "") {
            $text = get_string('unknown', 'local_linkchecker_robot');
        }
        $code = http_code($row);
        $size = $row->filesize * 1;
        $data = array(
            userdate($row->lastcrawled, '%h %e,&nbsp;%H:%M:%S'),
            $code,
            display_size($size),
            html_writer::link($row->target, $text) .
            '<br><small>' . $row->target . '</small>',
            $row->mimetype,
        );
        if (!$courseid) {
            array_push($data, html_writer::link('/course/view.php?id='.$row->courseid, $row->shortname) );
        }
        $table->data[] = $data;
    }

} else if ($report == 'oversize') {

    $sql = " FROM {linkchecker_url} b
       LEFT JOIN {linkchecker_edge} l ON l.b = b.id
       LEFT JOIN {linkchecker_url}  a ON l.a = a.id
       LEFT JOIN {course} c ON c.id = a.courseid
           WHERE b.filesize > ?
                 $sqlfilter";

    $bigfilesize = $config->bigfilesize;
    $opts = array($bigfilesize * 1000000);
    $data  = $DB->get_records_sql("SELECT concat(b.id, '-', a.id, '-', l.id) id,
                                          b.url target,
                                          b.filesize,
                                          b.lastcrawled,
                                          b.mimetype,
                                          l.text,
                                          a.title,
                                          a.url,
                                          a.courseid,
                                          c.shortname
                                          $sql
                                 ORDER BY b.filesize DESC,
                                          l.text,
                                          a.id",
                                          $opts,
                                          $start,
                                          $perpage);

    $count = $DB->get_field_sql  ("SELECT count(*) AS count" . $sql, $opts);

    $mdlw = strlen($CFG->wwwroot);

    $table = new html_table();

    $table->head = array(
        get_string('lastcrawledtime', 'local_linkchecker_robot'),
        get_string('size', 'local_linkchecker_robot'),
        get_string('slowurl', 'local_linkchecker_robot'),
        get_string('mimetype', 'local_linkchecker_robot'),
        get_string('frompage', 'local_linkchecker_robot'),
    );

    if (!$courseid) {
        array_push($table->head, get_string('course', 'local_linkchecker_robot'));
    }

    $table->data = array();
    foreach ($data as $row) {
        $size = $row->filesize * 1;
        $text = trim($row->text);
        if (!$text || $text == "") {
            $text = get_string('missing', 'local_linkchecker_robot');
        }
        $data = array(
            userdate($row->lastcrawled, '%h %e,&nbsp;%H:%M:%S'),
            display_size($size),
            html_writer::link($row->target, $text) .    '<br><small>' . $row->target . '</small>',
            $row->mimetype,
            html_writer::link($row->url, $row->title) . '<br><small>' . $row->url    . '</small>',
        );
        if (!$courseid) {
            array_push($data, html_writer::link('/course/view.php?id='.$row->courseid, $row->shortname) );
        }
        $table->data[] = $data;
    }

}

echo $OUTPUT->heading(get_string('numberurlsfound', 'local_linkchecker_robot',
    array(
        'reports_number' => $count,
        'repoprt_type' => $report
    )
));
echo get_string($report . '_header', 'local_linkchecker_robot');
echo html_writer::table($table);
echo $OUTPUT->paging_bar($count, $page, $perpage, $baseurl);
echo $OUTPUT->footer();

