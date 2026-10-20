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

use tool_crawler\local\url;

/**
 * Tests that the queue size only counts URLs in courses eligible for crawling.
 *
 * @package    tool_crawler
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_crawler\local\url::get_queue_size
 * @covers     \tool_crawler\robot\crawler::get_allowed_courses
 */
final class queue_size_test extends \advanced_testcase {
    /**
     * Insert a queued URL for a course.
     *
     * @param int $courseid
     * @param string $path
     */
    private function add_queued_url(int $courseid, string $path): void {
        global $DB;
        $DB->insert_record('tool_crawler_url', (object) [
            'url' => 'http://example.com/' . $path,
            'urlhash' => sha1('http://example.com/' . $path),
            'externalurl' => 0,
            'timecreated' => time(),
            'lastcrawled' => null,
            'needscrawl' => time(),
            'courseid' => $courseid,
            'priority' => 0,
        ]);
    }

    /**
     * Without course restrictions all queued URLs are counted.
     */
    public function test_queue_size_unrestricted(): void {
        $this->resetAfterTest();
        set_config('coursemode', 0, 'tool_crawler');
        set_config('uselogs', 0, 'tool_crawler');
        $this->add_queued_url(2, 'a');
        $this->add_queued_url(3, 'b');

        $this->assertFalse((new \tool_crawler\robot\crawler())->get_allowed_courses());
        $this->assertEquals(2, (new url())->get_queue_size());
    }

    /**
     * In course mode only unfinished courses are counted.
     */
    public function test_queue_size_coursemode(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('coursemode', 1, 'tool_crawler');
        $DB->insert_record('tool_crawler_course', (object) ['courseid' => 2, 'timestart' => null, 'timefinish' => null]);
        $DB->insert_record('tool_crawler_course', (object) ['courseid' => 3, 'timestart' => 1, 'timefinish' => 2]);
        $this->add_queued_url(2, 'a');
        $this->add_queued_url(3, 'b');
        $this->add_queued_url(3, 'c');

        $url = new url();
        $this->assertEquals(1, $url->get_queue_size());
        $this->assertEquals(0, $url->get_queue_size(3));
    }

    /**
     * In course mode with no eligible courses the queue is empty.
     */
    public function test_queue_size_coursemode_no_eligible_courses(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('coursemode', 1, 'tool_crawler');
        $DB->insert_record('tool_crawler_course', (object) ['courseid' => 3, 'timestart' => 1, 'timefinish' => 2]);
        $this->add_queued_url(3, 'a');

        $this->assertEquals(0, (new url())->get_queue_size());
        $this->assertTrue((new \tool_crawler\robot\crawler())->process_queue());
    }
}
