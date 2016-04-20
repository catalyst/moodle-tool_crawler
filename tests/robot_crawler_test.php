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

    public function test_absolute_urls() {
        $this->resetAfterTest(true);

        $base = "http://test.com/sub/";

        $this->assertNotEquals("http://test.com/file.php",     $this->robot->absolute_url($base, '/file.php'         ));
        $this->assertEquals("http://test.com/sub/file.php", $this->robot->absolute_url($base, 'file.php'          ));
        $this->assertEquals("http://test.com/file.php",     $this->robot->absolute_url($base, '../file.php'       ));
        $this->assertEquals("http://test.com/sib/file.php", $this->robot->absolute_url($base, '../sib/file.php'   ));
        $this->assertEquals("mailto:me@test.com",           $this->robot->absolute_url($base, 'mailto:me@test.com'));

    }

}

