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
 * local_linkchecker_robot
 *
 * @package    local_linkchecker_robot
 * @copyright  2015 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function local_linkchecker_robot_crawl() {

    global $DB, $CFG;

    $config = get_config('local_linkchecker_robot');

    $crawlstart = $DB->get_field('config_plugins', 'value', array('plugin'=>'local_linkchecker_robot','name' =>'crawlstart') );
    $crawlend   = $DB->get_field('config_plugins', 'value', array('plugin'=>'local_linkchecker_robot','name' =>'crawlend'  ) );

    $robot = new \local_linkchecker_robot\robot\crawler();

    // If we need to start a new crawl, push the seed url into the crawl queue
    if (!$crawlstart || $crawlstart <= $crawlend){

        $start = time();
        set_config('crawlstart', $start, 'local_linkchecker_robot');
        $robot->mark_for_crawl($CFG->wwwroot.'/', $config->seedurl);
#        print "Added seed url {$config->seedurl} to queue " . userdate($start) . "\n";

    }

    // while we are not exceeding the maxcron time, and the queue is not empty
    // find the next url in the queue and crawl it

    // if the queue is empty then mark the crawl as ended

    $cronstart = time();
    $cronstop = $cronstart + $config->maxcrontime;

    $hasmore = true;
    $hastime = true;
    while ($hasmore && $hastime){

        $hasmore = $robot->process_queue();
        $hastime = time() < $cronstop;
        set_config('crawltick', time(), 'local_linkchecker_robot');
    }

    if ($hastime){
        // Time left over, which means the queue is empty!
        // Mark the crawl as ended
        set_config('crawlend', time(), 'local_linkchecker_robot');
    }

}
