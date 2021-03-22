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
namespace tool_crawler;
defined('MOODLE_INTERNAL') || die();

class helper {
    // Reference: https://datatracker.ietf.org/doc/html/rfc7231
    private static $httpcodes = [
        '100' => 'The server is waiting to receive request payload body',
        '101' => 'The server agree to switch protocols specified in client\'s request header',
        '200' => 'The request has succeeded.',
        '201' => 'The request has resulted in creating new resource.',
        '202' => 'The processing is not completed.',
        '203' => 'The payload has been modified.',
        '204' => 'There is no additional content.',
        '205' => 'The server request user agent to reset "document view" to its original state.',
        '300' => 'There are more than one representations for the requested resource.',
        '301' => 'There is a new permanent URI for the requested resource.',
        '302' => 'There is a different temporary URI for the requested resource.',
        '303' => 'The request is redirected to a different resource',
        '400' => 'The server cannot process the request due to client error.',
        '401' => 'The request requires user authentication.',
        '403' => 'The server refuses to authorize the request. ',
        '404' => 'The server cannot find the requested resource.',
        '405' => 'The request method is not supported by the server.',
        '406' => 'The requested resource is not acceptable to the user agent.',
        '408' => 'The server cannot complete the request within a specified time.',
        '409' => 'There is a conflict in the current state of the requested resource.',
        '410' => 'The requested resource is no longer available.',
        '411' => 'There is no defined Content-Length in the request.',
        '413' => 'The request payload is too large.',
        '414' => 'The request URI is too long for server to interpret.',
        '415' => 'The media type is not supported.',
        '417' => 'The expectation given cannot be met by inbound servers.',
        '500' => 'The server encountered error.',
        '501' => 'The functionality is not supported by the server',
        '502' => 'Invalid response from inbound server.',
        '503' => 'Temporary overload or scheduled maintenance',
        '504' => 'The server did not receive a timely response from an upstream server.',
        '505' => 'The version of HTTP is not supported by the server.'
    ];

    /**
     * Queue a course for link checking
     *
     * @param int $courseid course ID
     * @throws \dml_exception
     */
    public static function queue_course($courseid) {
        global $DB;
        $record = self::get_queue_course($courseid);

        if(!empty($record)) {
            $record->timestart = null;
            $record->timefinish = null;
            $DB->update_record('tool_crawler_course', $record);
        } else {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->timestart = null;
            $record->timefinish = null;
            $DB->insert_record('tool_crawler_course', $record);
        }

        // Reset.
        self::clear_course_link($courseid);

    }

    /**
     * Get queue course based on course id
     *
     * @param int $courseid
     * @return false|mixed|\stdClass
     * @throws \dml_exception
     */
    public static function get_queue_course($courseid) {
        global $DB;
        return $DB->get_record('tool_crawler_course', ['courseid' => $courseid]);
    }

    /**
     * Get unfinished course link checking
     *
     * @return array
     * @throws \dml_exception
     */
    public static function get_onqueue_course_ids() {
        global $DB;
        return $DB->get_fieldset_select('tool_crawler_course', 'courseid', 'timefinish is null');
    }

    /**
     * Remove course from the queue
     *
     * @param int $courseid
     * @throws \dml_exception
     */
    public static function dequeue_course($courseid) {
        global $DB;
        $DB->delete_records('tool_crawler_course', ['courseid' => $courseid]);
    }

    /**
     * Reset link crawling for a course
     *
     * @param int $courseid
     * @throws \dml_exception
     */
    public static function clear_course_link($courseid) {
        global $DB;
        $DB->delete_records('tool_crawler_url', ['courseid' => $courseid]);
        $DB->delete_records('tool_crawler_edge', ['courseid' => $courseid]);
    }

    /**
     * Start link crawling for a course
     *
     * @param int $courseid
     * @throws \dml_exception
     */
    public static function start_course_crawling($courseid){
        global $DB;
        $DB->set_field('tool_crawler_course', 'timestart', time(), ['courseid' => $courseid]);
    }

    /**
     * Finish link crawling for a course
     *
     * @param int $courseid
     * @throws \dml_exception
     */
    public static function finish_course_crawling($courseid){
        global $DB;
        $DB->set_field('tool_crawler_course', 'timefinish', time(), ['courseid' => $courseid]);
        self::send_email($courseid);
    }


    /**
     * Caluclate progress for a course
     *
     * @param int $courseid
     * @return array|void
     * @throws \dml_exception
     */
    public static function calculate_progress($courseid) {
        $queuecourse = self::get_queue_course($courseid);
        if (empty($queuecourse)) {
            return;
        }

        if (empty($queuecourse->timestart)) {
            return;
        }

        $url = new \tool_crawler\local\url();
        $queuesize = $url->get_queue_size($courseid);
        $processed = $url->get_processed($queuecourse->timestart, $courseid);

        if ($queuesize == 0) {
            $progress = 1;
        } else {
            $progress = $processed / ($processed + $queuesize);
        }

        $duration = time() - $queuecourse->timestart;
        $eta = $progress > 0 ? userdate(floor($duration / $progress + $queuecourse->timestart)) : '';

        if (!empty($queuecourse->timefinish)) {
            $delta = $queuecourse->timefinish - $queuecourse->timestart;
        } else {
            $delta = time() - $queuecourse->timestart;
        }

        $duration = sprintf('%02d:%02d:%02d', $delta / 60 / 60, $delta / 60 % 60, $delta % 60);
        $progress = sprintf('%.2f%%', $progress * 100);

        return [$eta, $duration, $progress];
    }

    /**
     * Translate httpcode
     *
     * @param string $code
     * @return string
     */
    public static function translate_httpcode($code) {
        return isset(self::$httpcodes[$code]) ? self::$httpcodes[$code] : '';
    }


    /**
     *
     * Send email to user
     *
     * @param int $courseid
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function send_email($courseid) {
        $notifyemail = get_config('tool_crawler', 'emailto');

        $context = \context_course::instance($courseid);
        $users = get_users_by_capability($context, 'tool/crawler:courseconfig');

        if (!empty($notifyemail)) {
            $user = new \stdClass();
            $user->id = -1;
            $user->email = $notifyemail;
            $user->mailformat = 1;
            $users[] = $user;
        }

        $url = new \moodle_url('/admin/tool/crawler/course.php', ['id' => $courseid]);
        $noticehtml = get_string('emailcontent', 'tool_crawler', $url->out());
        $subject = get_string('emailsubject', 'tool_crawler');

        foreach ($users as $user) {
            email_to_user(
                $user,
                get_admin(),
                $subject,
                $noticehtml,
                $noticehtml
            );
        }
    }

    /**
     * Count broken links
     *
     * @param $courseid
     * @throws \dml_exception
     */
    public static function count_broken_links($courseid) {
        global $DB;
        $sql = "SELECT count(1) AS count
                  FROM {tool_crawler_url}  b
             LEFT JOIN {tool_crawler_edge} l ON l.b = b.id
             LEFT JOIN {tool_crawler_url}  a ON l.a = a.id
             LEFT JOIN {course} c ON c.id = a.courseid
                 WHERE b.httpcode != '200' AND c.id = $courseid";
        return $DB->count_records_sql($sql);
    }

}