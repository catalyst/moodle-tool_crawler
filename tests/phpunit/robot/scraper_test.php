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

use tool_crawler\robot\scraper;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_crawler_robot_scraper_test extends advanced_testcase {
    /**
     * @return array of test cases
     *
     * Local and external URLs and their tricky combinations
     */
    public function should_auth_provider() {
        return [
            [false, 'http://my_moodle.com', 'http://evil.com/blah/http://my_moodle.com'],
            [false, 'http://my_moodle.com', 'http://my_moodle.com.actually.im.evil.com'],
            [true, 'http://my_moodle.com', 'http://my_moodle.com'],
            [true, 'http://my_moodle.com', 'http://my_moodle.com/whatever/file1.php'],
            [false, 'http://my_moodle.com/subdir', 'http://evil.com/blah/http://my_moodle.com/subdir'],
            [false, 'http://my_moodle.com/subdir', 'http://my_moodle.com/subdir.actually.im.evil.com'],
            [true, 'http://my_moodle.com/subdir', 'http://my_moodle.com/subdir'],
            [true, 'http://my_moodle.com/subdir', 'http://my_moodle.com/subdir/whatever/file1.php'],
        ];
    }

    /** @dataProvider should_auth_provider */
    public function test_should_be_authenticated($expected, $myurl, $testurl) {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->wwwroot = $myurl;

        $scraper = new scraper();
        $actual = $scraper->should_be_authenticated($testurl);

        $this->assertSame($expected, $actual);
    }
}
