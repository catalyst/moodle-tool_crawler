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
 * @package   tool_crawler
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_crawler\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
use html_writer;
use moodleform;
class courselinkchecker extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $course = $this->_customdata['course'];
        $queuecourse = $this->_customdata['queuecourse'];

        $mform->addElement('hidden', 'courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $buttonarray = array();
        if (empty($queuecourse)) {
            $buttonarray[] = $mform->createElement('submit', 'addcourse', get_string('addcourse', 'tool_crawler'));
        } else {
            if (!empty($queuecourse->timefinish)) {
                $buttonarray[] = $mform->createElement('submit', 'resetcourse', get_string('resetcourse', 'tool_crawler'));
                $buttonarray[] = $mform->createElement('submit', 'stopcourse', get_string('stopcourse', 'tool_crawler'));
            } else {
                $buttonarray[] = $mform->createElement('submit', 'stopcourse', get_string('stopcourse', 'tool_crawler'));
            }
        }
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);

    }
}

