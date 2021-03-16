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

    private static $httpcodes = [
        '100' => 'Continue',
        '101' => 'Switching Protocols',
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '300' => 'Multiple Choices',
        '302' => 'Found',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '407' => 'Proxy Authentication Required',
        '408' => 'Request Timeout',
        '409' => 'Conflict',
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Request Entity Too Large',
        '414' => 'Request-URI Too Long',
        '415' => 'Unsupported Media Type',
        '416' => 'Requested Range Not Satisfiable',
        '417' => 'Expectation Failed',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
        '503' => 'Service Unavailable',
        '504' => 'Gateway Timeout',
        '505' => 'HTTP Version Not Supported'
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
     * @param int $courseid
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function send_email($courseid) {
        $notifyemail = get_config('tool_crawler', 'emailto');

        if (!empty($notifyemail)) {
            $url = new \moodle_url('/admin/tool/crawler/course.php', ['id' => $courseid]);
            $noticehtml = get_string('emailcontent', 'tool_crawler', $url->out());
            $subject = get_string('emailsubject', 'tool_crawler');

            $user = new \stdClass();
            $user->id = -1;
            $user->email = $notifyemail;
            $user->mailformat = 1;

            email_to_user(
                $user,
                get_admin(),
                $subject,
                $noticehtml,
                $noticehtml
            );
        }
    }

}