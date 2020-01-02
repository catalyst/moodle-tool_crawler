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
 *  Unit tests for link crawler robot
 *
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_crawler\robot\crawler;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden');

require_once(__DIR__ . '/../../locallib.php');

/**
 *  Unit tests for link crawler robot
 *
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_crawler_robot_crawler_test extends advanced_testcase {

    protected function setUp() {
        parent::setup();
        $this->resetAfterTest(true);

        $this->robot = new \tool_crawler\robot\crawler();

    }

    /**
     * @return array of test cases
     *
     * Combinations of base and relative parts of URL
     */
    public function absolute_urls_provider() {
        return array(
            array(
                'base' => 'http://test.com/sub/',
                'links' => array(
                    'mailto:me@test.com' => 'mailto:me@test.com',
                    '/file.php' => 'http://test.com/file.php',
                    'file.php' => 'http://test.com/sub/file.php',
                    '../sub2/file.php' => 'http://test.com/sub2/file.php',
                    'http://elsewhere.com/path/' => 'http://elsewhere.com/path/'
                )
            ),
            array(
                'base' => 'http://test.com/sub1/sub2/',
                'links' => array(
                    'mailto:me@test.com' => 'mailto:me@test.com',
                    '../../file.php' => 'http://test.com/file.php',
                    'file.php' => 'http://test.com/sub1/sub2/file.php',
                    '../sub3/file.php' => 'http://test.com/sub1/sub3/file.php',
                    'http://elsewhere.com/path/' => 'http://elsewhere.com/path/'
                )
            ),
            array(
                'base' => 'http://test.com/sub1/sub2/$%^/../../../',
                'links' => array(
                    'mailto:me@test.com' => 'mailto:me@test.com',
                    '/file.php' => 'http://test.com/file.php',
                    '/sub3/sub4//$%^/../../../file.php' => 'http://test.com/file.php',
                    'http://elsewhere.com/path/' => 'http://elsewhere.com/path/'
                    )
            ),
            array(
                'base' => 'http://test.com/sub1/sub2/file1.php',
                'links' => array(
                    'mailto:me@test.com' => 'mailto:me@test.com',
                    'file2.php' => 'http://test.com/sub1/sub2/file2.php',
                    '../file2.php' => 'http://test.com/sub1/file2.php',
                    'sub3/file2.php' => 'http://test.com/sub1/sub2/sub3/file2.php'
                )
            ),
            array(
                'base' => 'http://test.com/sub1/foo.php?id=12',
                'links' => array(
                    '/sub2/bar.php?id=34' => 'http://test.com/sub2/bar.php?id=34',
                    '/sub2/bar.php?id=34&foo=bar' => 'http://test.com/sub2/bar.php?id=34&foo=bar',
                ),
            ),
        );
    }

    /**
     * @dataProvider absolute_urls_provider
     *
     * Executing test cases returned by function provider()
     *
     * @param string $base Base part of URL
     * @param array $links Combinations of relative paths of URL and expected result
     */
    public function test_absolute_urls($base, $links) {
        foreach ($links as $key => $value) {
            $this->assertEquals($value, $this->robot->absolute_url($base, $key));
        }
    }

    /**
     * @return array of test cases
     *
     * Local and external URLs and their tricky combinations
     */
    public function should_auth_provider() {
        return array(
            array(false, 'http://my_moodle.com', 'http://evil.com/blah/http://my_moodle.com'),
            array(false, 'http://my_moodle.com', 'http://my_moodle.com.actually.im.evil.com'),
            array(true,  'http://my_moodle.com', 'http://my_moodle.com'),
            array(true,  'http://my_moodle.com', 'http://my_moodle.com/whatever/file1.php'),
            array(false, 'http://my_moodle.com/subdir', 'http://evil.com/blah/http://my_moodle.com/subdir'),
            array(false, 'http://my_moodle.com/subdir', 'http://my_moodle.com/subdir.actually.im.evil.com'),
            array(true,  'http://my_moodle.com/subdir', 'http://my_moodle.com/subdir'),
            array(true,  'http://my_moodle.com/subdir', 'http://my_moodle.com/subdir/whatever/file1.php'),
        );
    }

    /**
     * @dataProvider should_auth_provider
     *
     * Tests method should_be_authenticated($url) of class \tool_crawler\robot\crawler()
     *
     * @param bool $expected
     * @param string $myurl URL of current Moodle installation
     * @param string $testurl URL where we should authenticate
     */
    public function test_should_be_authenticated($expected, $myurl, $testurl) {
        global $CFG;
        $CFG->wwwroot = $myurl;
        $this->assertEquals((bool)$expected, $this->robot->should_be_authenticated($testurl));
        $this->resetAfterTest(true);
    }

    /**
     * Tests existence of new plugin parameter 'retentionperiod'
     */
    public function test_param_retention_exists() {
        $param = get_config('tool_crawler', 'retentionperiod');
        $this->assertNotEmpty($param);
    }

    /** Regression test for Issue #17  */
    public function test_reset_queries() {
        global $DB;

        $node = [
            'url' => 'http://crawler.test/course/index.php',
            'external' => 0,
            'createdate' => strtotime("16-05-2016 10:00:00"),
            'lastcrawled' => strtotime("16-05-2016 11:20:00"),
            'needscrawl' => strtotime("17-05-2017 10:00:00"),
            'httpcode' => 200,
            'mimetype' => 'text/html',
            'title' => 'Crawler Test',
            'downloadduration' => 0.23,
            'filesize' => 44003,
            'filesizestatus' => TOOL_CRAWLER_FILESIZE_EXACT,
            'redirect' => null,
            'courseid' => 1,
            'contextid' => 1,
            'cmid' => null,
            'ignoreduserid' => null,
            'ignoredtime' => null,
            'httpmsg' => 'OK',
            'errormsg' => null
        ];
        $nodeid = $DB->insert_record('tool_crawler_url', $node);

        $crawler = new crawler();
        $crawler->reset_for_recrawl($nodeid);

        // Record should not exist anymore.
        $found = $DB->record_exists('tool_crawler_url', ['id' => $nodeid]);
        self::assertFalse($found);
    }

    /**
     * Regression test for Issue #48: database must store URI without HTML-escaping, but URI must still be escaped when it is output
     * to an HTML document.
     */
    public function test_uri_escaping() {
        $baseurl = 'http://crawler.test/';
        $relativeurl = 'course/view.php?id=1&section=2'; // The '&' character is the important part here.
        $expectedurl = $baseurl . $relativeurl;
        $escapedexpected = 'http://crawler.test/course/view.php?id=1&amp;section=2';
        $node = $this->robot->mark_for_crawl($baseurl, $relativeurl);
        self::assertEquals($expectedurl, $node->url);

        $this->setAdminUser();
        $page = tool_crawler_url_create_page($expectedurl);
        $expectedpattern = '@' .
                preg_quote('<h2>', '@') .
                '.*' .
                preg_quote('<a ', '@') .
                '[^>]*' . // XXX: Not *100%* reliable, as '>' *might* be contained in attribute values.
                preg_quote('href="' . $escapedexpected . '">↗</a><br><small>' . $escapedexpected . '</small>', '@') .
                '@';
        self::assertRegExp($expectedpattern, $page);
    }

    /**
     * Regression test for an issue similar to Issue #48: redirection URI must be escaped when it is output to an HTML document.
     */
    public function test_redirection_uri_escaping() {
        global $DB;

        $url = 'http://crawler.test/course/view.php?id=1&section=2';
        $redirecturl = 'http://crawler.test/local/extendedview/viewcourse.php?id=1&section=2'; // The '&' is the important part.
        $escapedredirecturl = 'http://crawler.test/local/extendedview/viewcourse.php?id=1&amp;section=2';
        $node = [
            'url' => $url,
            'external' => 0,
            'createdate' => strtotime("16-05-2016 10:00:00"),
            'lastcrawled' => strtotime("16-05-2016 11:20:00"),
            'needscrawl' => strtotime("17-05-2017 10:00:00"),
            'httpcode' => 200,
            'mimetype' => 'text/html',
            'title' => 'Crawler Test',
            'downloadduration' => 0.23,
            'filesize' => 44003,
            'filesizestatus' => TOOL_CRAWLER_FILESIZE_EXACT,
            'redirect' => $redirecturl,
            'courseid' => 1,
            'contextid' => 1,
            'cmid' => null,
            'ignoreduserid' => null,
            'ignoredtime' => null,
            'httpmsg' => 'OK',
            'errormsg' => null
        ];
        $DB->insert_record('tool_crawler_url', $node);

        $this->setAdminUser();
        $page = tool_crawler_url_create_page($url);
        $expectedpattern = '@' .
                preg_quote('<h2>', '@') .
                '.*' .
                preg_quote('<br>Redirect: <a href="' . $escapedredirecturl . '">' . $escapedredirecturl . '</a></h2>', '@') .
                '@';
        self::assertRegExp($expectedpattern, $page);
    }

    /**
     * Test for Issue #92: specified dom elements in the config should be excluded.
     */
    public function test_should_be_excluded() {
        global $DB;

        $url = 'http://crawler.test/course/view.php?id=1&section=2';
        $node = [
            'url' => $url,
            'external' => 0,
            'createdate' => strtotime("03-01-2020 10:00:00"),
            'lastcrawled' => strtotime("31-12-2019 11:20:00"),
            'needscrawl' => strtotime("01-01-2020 10:00:00"),
            'httpcode' => 200,
            'mimetype' => 'text/html',
            'title' => 'Crawler Parse Test',
            'downloadduration' => 0.23,
            'filesize' => 44003,
            'filesizestatus' => TOOL_CRAWLER_FILESIZE_EXACT,
            'courseid' => 1,
            'contextid' => 1,
            'cmid' => null,
            'ignoreduserid' => null,
            'ignoredtime' => null,
            'httpmsg' => 'OK',
            'errormsg' => null
        ];
        $insertid = $DB->insert_record('tool_crawler_url', $node);

        $this->setAdminUser();
        $page = tool_crawler_url_create_page($url);

        $linktoexclude = '<div class="exclude"><a href="http://crawler.test/foo/bar.php"></div>';

        $node = new stdClass();
        $node->contents = $page . $linktoexclude;
        $node->url      = $url;
        $node->id       = $insertid;

        $this->resetAfterTest(true);

        set_config('excludemdldom',
            ".block.block_settings\n.block.block_book_toc\n.block.block_calendar_month\n" .
            ".block.block_navigation\n.block.block_cqu_assessment\n.exclude",
            'tool_crawler');

        $this->robot->parse_html($node, false);

        // URL should not exist for crawling.
        $found = $DB->record_exists('tool_crawler_url', array('url' => 'http://crawler.test/foo/bar.php') );
        self::assertFalse($found);
    }

}


