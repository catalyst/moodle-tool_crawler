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

namespace tool_crawler;
defined('MOODLE_INTERNAL') || die();

/**
 * Provide helper functions for crawling on a course
 *
 * @package tool_crawler
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Queue a course for link checking
     *
     * @param int $courseid course ID
     */
    public static function queue_course($courseid) {
        global $DB;
        $record = self::get_queue_course($courseid);

        if (!empty($record)) {
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
     */
    public static function get_queue_course($courseid) {
        global $DB;
        return $DB->get_record('tool_crawler_course', ['courseid' => $courseid]);
    }

    /**
     * Get unfinished course link checking
     *
     * @return array
     */
    public static function get_onqueue_course_ids() {
        global $DB;
        return $DB->get_fieldset_select('tool_crawler_course', 'courseid', 'timefinish is null');
    }

    /**
     * Remove course from the queue
     *
     * @param int $courseid
     */
    public static function dequeue_course($courseid) {
        global $DB;
        $DB->delete_records('tool_crawler_course', ['courseid' => $courseid]);
    }

    /**
     * Reset link crawling for a course
     *
     * @param int $courseid
     */
    public static function clear_course_link($courseid) {
        global $DB;
        $DB->delete_records('tool_crawler_url', ['courseid' => $courseid]);
        $DB->delete_records('tool_crawler_edge', ['courseid' => $courseid]);
    }

    /**
     * Start link crawling on a course
     *
     * @param int $courseid
     */
    public static function start_course_crawling($courseid) {
        global $DB;
        $DB->set_field('tool_crawler_course', 'timestart', time(), ['courseid' => $courseid]);
    }

    /**
     * Finish crawling on a course
     *
     * @param int $courseid
     */
    public static function finish_course_crawling($courseid) {
        global $DB;
        $DB->set_field('tool_crawler_course', 'timefinish', time(), ['courseid' => $courseid]);
        self::send_email($courseid);
    }

    /**
     * Caluclate progress of crawling on a course
     *
     * @param int $courseid
     * @return array|void
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
     * Translate http code
     *
     * @param string $code
     * @return string
     */
    public static function translate_httpcode($code) {
        // List of http code.
        $httpcodes = [
            '100' => get_string('httpcode_100', 'tool_crawler'),
            '101' => get_string('httpcode_101', 'tool_crawler'),
            '200' => get_string('httpcode_200', 'tool_crawler'),
            '201' => get_string('httpcode_201', 'tool_crawler'),
            '202' => get_string('httpcode_202', 'tool_crawler'),
            '203' => get_string('httpcode_203', 'tool_crawler'),
            '204' => get_string('httpcode_204', 'tool_crawler'),
            '205' => get_string('httpcode_205', 'tool_crawler'),
            '300' => get_string('httpcode_300', 'tool_crawler'),
            '301' => get_string('httpcode_301', 'tool_crawler'),
            '302' => get_string('httpcode_302', 'tool_crawler'),
            '303' => get_string('httpcode_303', 'tool_crawler'),
            '400' => get_string('httpcode_400', 'tool_crawler'),
            '401' => get_string('httpcode_401', 'tool_crawler'),
            '403' => get_string('httpcode_403', 'tool_crawler'),
            '404' => get_string('httpcode_404', 'tool_crawler'),
            '405' => get_string('httpcode_405', 'tool_crawler'),
            '406' => get_string('httpcode_406', 'tool_crawler'),
            '408' => get_string('httpcode_408', 'tool_crawler'),
            '409' => get_string('httpcode_409', 'tool_crawler'),
            '410' => get_string('httpcode_410', 'tool_crawler'),
            '411' => get_string('httpcode_411', 'tool_crawler'),
            '413' => get_string('httpcode_413', 'tool_crawler'),
            '414' => get_string('httpcode_414', 'tool_crawler'),
            '415' => get_string('httpcode_415', 'tool_crawler'),
            '417' => get_string('httpcode_417', 'tool_crawler'),
            '500' => get_string('httpcode_500', 'tool_crawler'),
            '501' => get_string('httpcode_501', 'tool_crawler'),
            '502' => get_string('httpcode_502', 'tool_crawler'),
            '503' => get_string('httpcode_503', 'tool_crawler'),
            '504' => get_string('httpcode_504', 'tool_crawler'),
            '505' => get_string('httpcode_505', 'tool_crawler'),
        ];

        return $httpcodes[$code] ?? '';
    }

    /**
     *
     * Send email to user
     *
     * @param int $courseid
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
     * @param int $courseid the course id
     * @return int number of broken links
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
