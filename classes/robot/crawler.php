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
require_once($CFG->dirroot.'/local/linkchecker_robot/simple_html_dom.php');
require_once($CFG->dirroot.'/user/lib.php');

class crawler {

    protected $config;

    function __construct() {

        $this->config = get_config('local_linkchecker_robot');
        if (!property_exists ($this->config, 'crawlstart') ) {
            $this->config->crawlstart = 0;
        }
    }


    /*
     * checks that the bot user exists and password works etc
     * returns true, or a error string
     */
    public function is_bot_valid() {

        global $DB, $CFG;

        $botusername  = $this->config->botusername;
        if (!$botusername) {
            return 'CONFIG MISSING';
        }
        if (!$botuser = $DB->get_record('user', array('username'=>$botusername) )) {
            return 'BOT USER MISSING <a href="?action=makebot">Auto create</a>';
        }

        // do a test crawl over the network
        $result = $this->scrape($CFG->wwwroot.'/local/linkchecker_robot/tests/test1.php');
        if ($result->httpcode != '200') {
            return 'BOT could not request test page';
        }
        if ($result->redirect) {
            return "BOT test page was  redirected to {$result->redirect}";
        }

        $hello = strpos($result->contents, "Hello robot: '{$this->config->botusername}'");
        if (!$hello) {
            return "BOT test page wasn't returned";
        }

    }

    /*
     * Auto create the moodle user that the robot logs in as
     */
    public function auto_create_bot() {

        global $DB, $CFG;

        // TODO roles?

        $botusername  = $this->config->botusername;
        $botuser = $DB->get_record('user', array('username'=>$botusername) );
        if ($botuser){
            return $botuser;
        } else {
            $botuser = (object) array();
            $botuser->username   = $botusername;
            $botuser->password   = hash_internal_user_password($this->config->botpassword);
            $botuser->firstname  = 'Link checker';
            $botuser->lastname   = 'Robot';
            $botuser->auth       = 'basic';
            $botuser->confirmed  = 1;
            $botuser->email      = 'bot@bots.com';
            $botuser->city       = 'Botville';
            $botuser->country    = 'AU';
            $botuser->mnethostid = $CFG->mnet_localhost_id;

            $botuser->id = user_create_user($botuser, false, false);

            return $botuser;
        }
    }


    /*
     * Adds a url to the queue for crawling
     * and returns the node record
     * if the url is invalid returns false
     */
    public function mark_for_crawl($baseurl, $url) {

        global $DB, $CFG;

        // Filter out non http protocols like mailto:cqulibrary@cqu.edu.au
        $bits = parse_url($url);
        if (array_key_exists('scheme', $bits)
            && !($bits['scheme'] == 'http' || $bits['scheme'] == 'https') ){
            return false;
        }

        // All url's must be fully qualified
        // If it is server relative the add the wwwroot
        if ( substr ( $url ,0, 1) == '/'){
            $url = $CFG->wwwroot . $url;
        }

        // If the url is relative then prepend the from page url
        if (substr ( $url ,0, 4) != 'http' ){
            $rslash = strrpos($baseurl, '/');
            $url = substr($baseurl,0,$rslash+1) . $url;
            // TODO 
            // fix up ../ links
        }

        // If this url is external then check the ext whitelist
        $mdlw = strlen($CFG->wwwroot);
        $bad = 0;
        if (substr ($url,0,$mdlw) === $CFG->wwwroot){
            $excludes = str_replace("\r",'', $this->config->excludemdlurl);
        } else {
            $excludes = str_replace("\r",'', $this->config->excludeexturl);
        }
        $excludes = explode("\n", $excludes);
        if (sizeof($excludes) > 0 && $excludes[0]){
            foreach ($excludes as $exclude){
                if (strpos($url, $exclude) > 0 ){
                    $bad = 1;
                    break;
                }
            }
        }
        if ($bad){
            return false;
        }

        // Ideally this limit should be around 2000 chars but moodle has DB field size limits
        if (strlen($url) > 1333){
            return false;
        }


        $node = $DB->get_record('linkchecker_url', array('url' => $url) );

        if(!$node) {
            // if not in the queue then add it
            $node = (object) array();
            $node->createdate = time();
            $node->url        = $url;
            $node->external   = strpos($url, $CFG->wwwroot) === 0 ? 0 : 1;
            $node->id = $DB->insert_record('linkchecker_url', $node);

        } else {

            $node->needscrawl = $this->config->crawlstart;
            $DB->update_record('linkchecker_url', $node);
        }
        return $node;
    }

    /*
     * When did the current crawl start?
     */
    public function get_crawlstart() {
        return property_exists($this->config, 'crawlstart') ? $this->config->crawlstart : 0;
    }

    /*
     * When did the last crawl finish?
     */
    public function get_last_crawlend() {
        return property_exists($this->config, 'crawlend') ? $this->config->crawlend : 0;
    }

    /*
     * When did the crawler last process anything?
     */
    public function get_last_crawltick() {
        return property_exists($this->config, 'crawltick') ? $this->config->crawltick : 0;
    }

    /*
     * Many urls are in the queue now (more will probably be added)
     */
    public function get_queue_size() {
        global $DB;

        $queuesize = $DB->get_field_sql("SELECT COUNT(*)
                                           FROM {linkchecker_url}
                                          WHERE lastcrawled IS NULL
                                             OR lastcrawled < needscrawl"
                                       );
        return $queuesize;
    }

    /*
     * How many urls have been processed off the queue
     */
    public function get_processed() {
        global $DB;

        return $DB->get_field_sql("SELECT COUNT(*)
                                           FROM {linkchecker_url}
                                          WHERE lastcrawled >= :start",
                                    array('start' =>  $this->config->crawlstart)
                                       );
    }

    /*
     * How many urls have been processed off the queue
     */
    public function get_old_queue_size() {
        global $DB;

        return $DB->get_field_sql("SELECT COUNT(*)
                                           FROM {linkchecker_url}
                                          WHERE lastcrawled < :start",
                                    array('start' =>  $this->config->crawlstart)
                                       );
    }

    /*
     * Pops an item off the queue and processes it
     * returns true if it did anything
     * returns false if the queue is empty
     */
    public function process_queue() {

        global $DB;

        $nodes = $DB->get_records_sql('SELECT *
                                         FROM {linkchecker_url}
                                        WHERE lastcrawled IS NULL
                                           OR lastcrawled < needscrawl
                                    ');

        $node = array_pop($nodes);
        if ($node){
            $this->crawl($node);
            return true;
        }

        return false;

    }

    /*
     * takes a queue item and crawls it
     * crawls a single url and then passes it off to a mime type handler
     * to pull out the links to other urls
     */
    public function crawl($node) {

        global $DB, $CFG;

        $result = $this->scrape($node->url);
        $result = (object) array_merge((array) $node, (array) $result);

        // TODO add external url whitelist
        if ($result->external == 0 && $result->httpcode == '200'){

            if ($result->mimetype == 'text/html'){
                $this->extract_links($result);
            } else {

                // TODO Possibly we can infer the course purely from the url
                // maybe the plugin serving urls?
            }

        }

        // Wait until we've finished processing the links before we save:
        $DB->update_record('linkchecker_url', $result);

    }


    /*
     * Given a recently crawled node, extract links to other pages
     *
     * Should only be run on internal moodle pages
     */
    private function extract_links($node){

        global $CFG;

        $raw = $node->contents;

        // Strip out any data uri's (parse doesn't like them)
        $raw = preg_replace('/"data:[^"]*?"/', '', $raw);

        $html = str_get_html($raw);

        // If couldn't parse html
        if (!$html){
            return;
        }

        // Remove any chunks of DOM that we know to be safe and don't want to follow
        $excludes = explode("\n", $this->config->excludemdldom);
        foreach ($excludes as $exclude){
            foreach($html->find($exclude) as $e) {
                $e->outertext = '';
            }
        }

        $seen = array();
        foreach($html->find('a[href]') as $e) {
            $href = $e->href;
            if (array_key_exists($href,$seen ) ){
                continue;
            }
            $seen[$href] = 1;
            if (substr ($href,0,1) === '#'){
                continue;
            }

            // TODO find some context of the link, like the nearest id
            $this->link_from_node_to_url($node, $href);
        }

        // Store some context about where we are
        foreach ($html->find('body') as $body){
            $classes = explode(" ", $body->class);
            foreach ($classes as $cl){
                if (substr($cl,0,7) == 'course-'){
                    $node->courseid = substr($cl,7);
                }
                if (substr($cl,0,8) == 'context-'){
                    $node->contextid = substr($cl,8);
                }
                if (substr($cl,0,5) == 'cmid-'){
                    $node->cmid = substr($cl,5);
                }
            }
        }

        return $node;

    }


    /*
     * upserts a link between two nodes in the url graph
     * if the url is invalid returns false
     */
    private function link_from_node_to_url($from, $url){

        global $DB;

        $to = $this->mark_for_crawl($from->url, $url);
        if ($to === false){
            return false;
        }

        $link = $DB->get_record('linkchecker_edge', array('a'=>$from->id, 'b'=>$to->id));
        if (!$link){
//e("{$from->id} {$from->url} to $url");
            $link          = new \stdClass();
            $link->a       = $from->id;
            $link->b       = $to->id;
            $link->lastmod = time();
            $link->id = $DB->insert_record('linkchecker_edge', $link);
        } else {
//e('up lnk');
            $link->lastmod = time();
            $DB->update_record('linkchecker_edge', $link);
        }
        return $link;
    }

    /*
     * Scrapes a fully qualified url and returns details about it
     * The format returns is ready to directly insert into the DB queue
     */
    public function scrape($url) {

        global $CFG;
        $cookieFileLocation = $CFG->dataroot . '/linkchecker_cookies.txt';

        $s = curl_init();
        curl_setopt($s, CURLOPT_URL,             $url);
        curl_setopt($s, CURLOPT_TIMEOUT,         $this->config->maxtime);
        curl_setopt($s, CURLOPT_USERPWD,         $this->config->botusername.':'.$this->config->botpassword);
        curl_setopt($s, CURLOPT_USERAGENT,       $this->config->useragent . '/' . $this->config->version );
        curl_setopt($s, CURLOPT_MAXREDIRS,       5);
        curl_setopt($s, CURLOPT_RETURNTRANSFER,  true);
        curl_setopt($s, CURLOPT_FOLLOWLOCATION,  true);
        curl_setopt($s, CURLOPT_FRESH_CONNECT,   true);
        curl_setopt($s, CURLOPT_COOKIEJAR,       $cookieFileLocation);
        curl_setopt($s, CURLOPT_COOKIEFILE,      $cookieFileLocation);

        $result = (object) array();
        $result->url              = $url;
        $result->contents         = curl_exec($s);
        $result->httpcode         = curl_getinfo($s, CURLINFO_HTTP_CODE );
        $result->filesize         = curl_getinfo($s, CURLINFO_SIZE_DOWNLOAD);
        $mimetype                 = curl_getinfo($s, CURLINFO_CONTENT_TYPE);
        $mimetype                 = preg_replace('/; .*/','', $mimetype);
        $result->mimetype         = $mimetype;
        $result->lastcrawled      = time();
        $result->downloadduration = curl_getinfo($s, CURLINFO_TOTAL_TIME);
        $final                    = curl_getinfo($s,CURLINFO_EFFECTIVE_URL);
        if ($final != $url){
            $result->redirect = $final;
        } else {
            $result->redirect = '';
        }

        curl_close($s);
        return $result;
    }

}


