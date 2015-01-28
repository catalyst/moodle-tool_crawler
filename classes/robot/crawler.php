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

namespace local_linkchecker_robot\robot;

require_once($CFG->dirroot.'/local/linkchecker_robot/lib.php');
require_once($CFG->dirroot.'/user/lib.php');

class crawler {

    /*
     * checks that the bot user exists and password works etc
     * returns true, or a error string
     */
    public function is_bot_valid() {

        global $DB;

        $config = get_config('local_linkchecker_robot');
        $botusername  = $config->botusername;
        if (!$botusername){
            return 'CONFIG MISSING';
        }
        if (!$botuser = $DB->get_record('user', array('username'=>$botusername) )) {
            return 'BOT USER MISSING <a href="?action=makebot">Auto create</a>';
        }
        // check auth?
        // check password?

        // do a test crawl over the network
    }

    /*
     *
     */
    public function auto_create_bot() {

        global $DB;

        $config = get_config('local_linkchecker_robot');
        $botusername  = $config->botusername;
        $botuser = $DB->get_record('user', array('username'=>$botusername) );
        if ($botuser){
            return $botuser;
        } else {
            $botuser = (object) array();
            $botuser->username  = $botusername;
            $botuser->password  = hash_internal_user_password($config->botpassword);
            $botuser->firstname = 'Link checker';
            $botuser->lastname  = 'Robot';
            $botuser->auth      = 'basic';

            $botuser->id = user_create_user($botuser, false, false);

            return $botuser;
        }
    }

    /*
     * crawls a single url and then passes it off to a mime type handler
     * to pull out the links to other urls
     */
    public function crawl($url) {

        global $DB, $CFG;

        // Strip the url first of unwanted query params like sesskey

        $start = time();

        // All url's must be fully qualified
        if ( substr ( $url ,0, 4) != 'http' ){
            $url = $CFG->wwwroot . $url;
        }

        if(!$node = $DB->get_record('linkchecker_url', array('url' => $url) )){

            $node = (object) array();
            $node->url = $url;
            $node->createdate = $start;
e($node);
            $DB->insert_record('linkchecker_url', $node);

        }

        // do we tell curl to follow links or do that ourselves? can we just retrieve the final url from curl?


//        $DB->update_record('linkchecker_url', $node);

        $finish = time();

        // how long does it take
        // what is the return code
        // dump to a file
        // pass the file to a mime type handler

        // need a mapping of mime types to handles


    }

}


