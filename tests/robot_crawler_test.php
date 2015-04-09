<?php
/**
 *  Unit tests for cqu_group_sync local plugin
 *
 * @package    local
 * @subpackage link
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden');
class local_cqu_group_sync_test extends advanced_testcase {

    protected function setUp() {
        parent::setup();
        $this->resetAfterTest(true);

        $this->robot = new \local_linkchecker_robot\robot\crawler();

    }

    public function test_absolute_urls() {
        global $DB, $CFG;
        $this->resetAfterTest(true);

        // URL assertions
        $base = "http://test.com/sub/";

        $this->assertEquals("http://test.com/file.php",     $this->robot->absolute_url($base, '/file.php'         ));
        $this->assertEquals("http://test.com/sub/file.php", $this->robot->absolute_url($base, 'file.php'          ));
        $this->assertEquals("http://test.com/file.php",     $this->robot->absolute_url($base, '../file.php'       ));
        $this->assertEquals("http://test.com/sib/file.php", $this->robot->absolute_url($base, '../sib/file.php'   ));
        $this->assertEquals("mailto:me@test.com",           $this->robot->absolute_url($base, 'mailto:me@test.com'));


    }

}

