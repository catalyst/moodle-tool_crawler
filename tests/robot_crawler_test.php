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
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden');

/**
 *  Unit tests for link crawler robot
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_linkchecker_robot_test extends advanced_testcase {

    protected function setUp() {
        parent::setup();
        $this->resetAfterTest(true);

        $this->robot = new \local_linkchecker_robot\robot\crawler();

    }

    /**
     * @return array of test cases
     *
     * Combinations of base and relative parts of URL
     */
    public function provider() {
        return array(
            array(
                'base' => 'http://test.com/sub/',
                'links' => array(
                    'mailto:me@test.com' => 'mailto:me@test.com',
                    '/file.php' => 'http://test.com/sub/file.php',
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
            )
        );
    }

    /**
     * @dataProvider provider
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
}


