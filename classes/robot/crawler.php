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
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_linkchecker_robot\robot;

require_once($CFG->dirroot.'/local/linkchecker_robot/lib.php');
require_once($CFG->dirroot.'/local/linkchecker_robot/extlib/simple_html_dom.php');
require_once($CFG->dirroot.'/user/lib.php');

/**
 * local_linkchecker_robot
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class crawler {

    /**
     * @var the config object
     */
    protected $config;

    /**
     * Robot constructor
     */
    public function __construct() {

        $this->config = get_config('local_linkchecker_robot');
        if (!property_exists ($this->config, 'crawlstart') ) {
            $this->config->crawlstart = 0;
        }
    }

    /**
     * Checks that the bot user exists and password works etc
     *
     * @return mixed true or a error string
     */
    public function is_bot_valid() {

        global $DB, $CFG;

        $botusername  = $this->config->botusername;
        if (!$botusername) {
            return 'CONFIG MISSING';
        }
        if ( !$DB->get_record('user', array('username' => $botusername)) ) {
            return 'BOT USER MISSING <a href="?action=makebot">Auto create</a>';
        }

        // Do a test crawl over the network.
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

    /**
     * Auto create the moodle user that the robot logs in as
     */
    public function auto_create_bot() {

        global $DB, $CFG;

        // TODO roles?

        $botusername  = $this->config->botusername;
        $botuser = $DB->get_record('user', array('username' => $botusername) );
        if ($botuser) {
            return $botuser;
        } else {
            $botuser = (object) array();
            $botuser->username   = $botusername;
            $botuser->password   = hash_internal_user_password($this->config->botpassword);
            $botuser->firstname  = 'Link checker';
            $botuser->lastname   = 'Robot';
            $botuser->auth       = 'basic';
            $botuser->confirmed  = 1;
            $botuser->email      = 'robot@moodle.invalid';
            $botuser->city       = 'Botville';
            $botuser->country    = 'AU';
            $botuser->mnethostid = $CFG->mnet_localhost_id;

            $botuser->id = user_create_user($botuser, false, false);

            return $botuser;
        }
    }

    /**
     * Convert a relative url to an absolute url
     *
     * @param string $base url
     * @param string $rel relative url
     * @return string absolute url
     */
    public function absolute_url($base, $rel) {
        /* return if already absolute URL */
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        /* queries and anchors */
        if ($rel[0] == '#' || $rel[0] == '?') {
            return $base.$rel;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'];
        $path = $parts['path'];
        $host = $parts['host'];

        // Remove non-directory element from path.
        $path = preg_replace('#/[^/]*$#', '', $path);

        // Dirty absolute URL.
        $abs = "$host$path/$rel";

        // Replace '//' or '/./' or '/foo/../' with '/' */.
        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        do {
            $abs = preg_replace($re, '/', $abs, -1, $n);
        } while ($n > 0);

        // Absolute URL is ready!
        return $scheme.'://'.$abs;
    }


    /**
     * Reset a node to be recrawled
     *
     * @param integer $nodeid node id
     */
    public function reset_for_recrawl($nodeid) {

        global $DB;

        if ($DB->get_record('linkchecker_url', array('id' => $nodeid))) {

            $time = $this->get_crawlstart();

            // Mark all nodes that link to this as needing a recrawl.
            $DB->execute("UPDATE {linkchecker_url} u
                             SET needscrawl = ?,
                                 lastcrawled = null
                            FROM {linkchecker_edge} e
                           WHERE e.a = u.id
                             AND e.b = ?", array($time, $nodeid) );

            // Delete all edges that point to this node.
            $DB->execute("DELETE
                            FROM {linkchecker_edge} e
                           WHERE e.b = ?", array($nodeid) );

            // Delete the 'to' node as it may be completely wrong.
            $DB->delete_records('linkchecker_url', array('id' => $nodeid) );

        }
    }

    /**
     * Adds a url to the queue for crawling
     *
     * @param string $baseurl
     * @param string $url relative url
     * @return the node record or if the url is invalid returns false.
     */
    public function mark_for_crawl($baseurl, $url) {

        global $DB, $CFG;

        $url = $this->absolute_url($baseurl, $url);

        // Filter out non http protocols like mailto:cqulibrary@cqu.edu.au etc.
        $bits = parse_url($url);
        if (array_key_exists('scheme', $bits)
            && $bits['scheme'] != 'http'
            && $bits['scheme'] != 'https'
            ) {
            return false;
        }

        // If this url is external then check the ext whitelist.
        $mdlw = strlen($CFG->wwwroot);
        $bad = 0;
        if (substr ($url, 0, $mdlw) === $CFG->wwwroot) {
            $excludes = str_replace("\r", '', $this->config->excludemdlurl);
        } else {
            $excludes = str_replace("\r", '', $this->config->excludeexturl);
        }
        $excludes = explode("\n", $excludes);
        if (count($excludes) > 0 && $excludes[0]) {
            foreach ($excludes as $exclude) {
                if (strpos($url, $exclude) > 0 ) {
                    $bad = 1;
                    break;
                }
            }
        }
        if ($bad) {
            return false;
        }

        // Ideally this limit should be around 2000 chars but moodle has DB field size limits.
        if (strlen($url) > 1333) {
            return false;
        }

        $node = $DB->get_record('linkchecker_url', array('url' => $url) );

        if (!$node) {
            // If not in the queue then add it.
            $node = (object) array();
            $node->createdate = time();
            $node->url        = $url;
            $node->external   = strpos($url, $CFG->wwwroot) === 0 ? 0 : 1;
            $node->needscrawl = time();
            $node->id = $DB->insert_record('linkchecker_url', $node);

        } else {

            $node->needscrawl = time();
            $DB->update_record('linkchecker_url', $node);
        }
        return $node;
    }

    /**
     * When did the current crawl start?
     *
     * @return timestamp of crawl start
     */
    public function get_crawlstart() {
        return property_exists($this->config, 'crawlstart') ? $this->config->crawlstart : 0;
    }

    /**
     * When did the last crawl finish?
     *
     * @return timestamp of crawl end
     */
    public function get_last_crawlend() {
        return property_exists($this->config, 'crawlend') ? $this->config->crawlend : 0;
    }

    /**
     * When did the crawler last process anything?
     *
     * @return timestamp of last crawl process
     */
    public function get_last_crawltick() {
        return property_exists($this->config, 'crawltick') ? $this->config->crawltick : 0;
    }

    /**
     * Many urls are in the queue now (more will probably be added)
     *
     * @return size of queue
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

    /**
     * How many urls have been processed off the queue
     *
     * @return size of processes list
     */
    public function get_processed() {
        global $DB;

        return $DB->get_field_sql("SELECT COUNT(*)
                                           FROM {linkchecker_url}
                                          WHERE lastcrawled >= :start",
                                    array('start' => $this->config->crawlstart)
                                       );
    }

    /**
     * How many urls have been processed off the previous queue
     *
     * @return size of old processes list
     */
    public function get_old_queue_size() {
        global $DB;

        return $DB->get_field_sql("SELECT COUNT(*)
                                           FROM {linkchecker_url}
                                          WHERE lastcrawled < :start",
                                    array('start' => $this->config->crawlstart)
                                       );
    }

    /**
     * Pops an item off the queue and processes it
     *
     * @return true if it did anything, false if the queue is empty
     */
    public function process_queue() {

        global $DB;

        $nodes = $DB->get_records_sql('SELECT *
                                         FROM {linkchecker_url}
                                        WHERE lastcrawled IS NULL
                                           OR lastcrawled < needscrawl
                                     ORDER BY needscrawl ASC, id ASC
                                        LIMIT 1
                                    ');

        $node = array_pop($nodes);
        if ($node) {
            $this->crawl($node);
            return true;
        }

        return false;

    }

    /**
     * Takes a queue item and crawls it
     *
     * It crawls a single url and then passes it off to a mime type handler
     * to pull out the links to other urls
     *
     * @param object $node a node
     */
    public function crawl($node) {

        global $DB;

        $result = $this->scrape($node->url);
        $result = (object) array_merge((array) $node, (array) $result);

        if ($result->httpcode == '200') {

            if ($result->mimetype == 'text/html') {
                $this->parse_html($result, $result->external);
            }
            // Else TODO Possibly we can infer the course purely from the url
            // Maybe the plugin serving urls?
        }

        $detectutf8 = function ($string) {
                return preg_match('%(?:
                [\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
                |\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
                |[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
                |\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
                |\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
                |[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
                |\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
                )+%xs', $string);
        };

        if (!$detectutf8($result->title)) {
            $result->title = utf8_decode($result->title);
        }

        // Wait until we've finished processing the links before we save.
        $DB->update_record('linkchecker_url', $result);

    }


    /**
     * Given a recently crawled node, extract links to other pages
     *
     * Should only be run on internal moodle pages, ie never follow
     * links on external pages. We don't want to scrape the whole web!
     *
     * @param object $node a url node
     * @param boolean $external is the url ourside moodle
     */
    private function parse_html($node, $external) {

        global $CFG;

        $raw = $node->contents;

        // Strip out any data uri's - the parser doesn't like them.
        $raw = preg_replace('/"data:[^"]*?"/', '', $raw);

        $html = str_get_html($raw);

        // If couldn't parse html.
        if (!$html) {
            return;
        }

        $node->title = $html->find('title', 0)->plaintext;

        // Everything after this is only for internal moodle pages.
        if ($external) {
            return $node;
        }

        // Remove any chunks of DOM that we know to be safe and don't want to follow.
        $excludes = explode("\n", $this->config->excludemdldom);
        foreach ($excludes as $exclude) {
            foreach ($html->find($exclude) as $e) {
                $e->outertext = '';
            }
        }

        $seen = array();
        foreach ($html->find('a[href]') as $e) {
            $href = $e->href;
            $href = str_replace('&amp;', '&', $href);

            if (array_key_exists($href, $seen ) ) {
                continue;
            }
            $seen[$href] = 1;
            if (substr ($href, 0, 1) === '#') {
                continue;
            }

            // If this url is external then check the ext whitelist.
            $mdlw = strlen($CFG->wwwroot);
            $bad = 0;
            if (substr ($href, 0, $mdlw) === $CFG->wwwroot) {
                $excludes = str_replace("\r", '', $this->config->excludemdlurl);
            } else {
                $excludes = str_replace("\r", '', $this->config->excludeexturl);
            }
            $excludes = explode("\n", $excludes);
            if (count($excludes) > 0 && $excludes[0]) {
                foreach ($excludes as $exclude) {
                    if (strpos($href, $exclude) > 0 ) {
                        $bad = 1;
                        break;
                    }
                }
            }

            // TODO find some context of the link, like the nearest id.
            $this->link_from_node_to_url($node, $href, $e->innertext);
        }

        // Store some context about where we are.
        foreach ($html->find('body') as $body) {
            $classes = explode(" ", $body->class);
            foreach ($classes as $cl) {
                if (substr($cl, 0, 7) == 'course-') {
                    $node->courseid = intval(substr($cl, 7));
                }
                if (substr($cl, 0, 8) == 'context-') {
                    $node->contextid = intval(substr($cl, 8));
                }
                if (substr($cl, 0, 5) == 'cmid-') {
                    $node->cmid = intval(substr($cl, 5));
                }
            }
        }

        return $node;
    }


    /**
     * Upserts a link between two nodes in the url graph
     *
     * @param string $from from url
     * @param string $url current url
     * @param string $text the link text label
     * @return the new url node or false
     */
    private function link_from_node_to_url($from, $url, $text) {

        global $DB;

        $to = $this->mark_for_crawl($from->url, $url);
        if ($to === false) {
            return false;
        }

        $link = $DB->get_record('linkchecker_edge', array('a' => $from->id, 'b' => $to->id));
        if (!$link) {
            $link          = new \stdClass();
            $link->a       = $from->id;
            $link->b       = $to->id;
            $link->lastmod = time();
            $link->text    = $text;
            $link->id = $DB->insert_record('linkchecker_edge', $link);
        } else {
            $link->lastmod = time();
            $link->text    = $text;
            $DB->update_record('linkchecker_edge', $link);
        }
        return $link;
    }

    /**
     * Scrapes a fully qualified url and returns details about it
     *
     * The format returns is ready to directly insert into the DB queue
     *
     * @param string $url current url
     * @return the result object
     */
    public function scrape($url) {

        global $CFG;
        $cookiefilelocation = $CFG->dataroot . '/linkchecker_cookies.txt';

        $s = curl_init();
        curl_setopt($s, CURLOPT_URL,             $url);
        curl_setopt($s, CURLOPT_TIMEOUT,         $this->config->maxtime);
        curl_setopt($s, CURLOPT_USERPWD,         $this->config->botusername.':'.$this->config->botpassword);
        curl_setopt($s, CURLOPT_USERAGENT,       $this->config->useragent . '/' . $this->config->version . ' ('.$CFG->wwwroot.')' );
        curl_setopt($s, CURLOPT_MAXREDIRS,       5);
        curl_setopt($s, CURLOPT_RETURNTRANSFER,  true);
        curl_setopt($s, CURLOPT_FOLLOWLOCATION,  true);
        curl_setopt($s, CURLOPT_FRESH_CONNECT,   true);
        curl_setopt($s, CURLOPT_HEADER,          true);
        curl_setopt($s, CURLOPT_COOKIEJAR,       $cookiefilelocation);
        curl_setopt($s, CURLOPT_COOKIEFILE,      $cookiefilelocation);

        $result = (object) array();
        $result->url              = $url;
        $raw   = curl_exec($s);
        $headersize = curl_getinfo($s, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $headersize);
        $header = strtok($headers, "\n");
        $result->httpmsg          = explode(" ", $header, 3)[2];
        $result->contents         = substr($raw, $headersize);
        $data = $result->contents;

        // See http://stackoverflow.com/questions/9351694/setting-php-default-encoding-to-utf-8 for more.
        unset($charset);
        $contenttype = curl_getinfo($s, CURLINFO_CONTENT_TYPE);

        /* 1: HTTP Content-Type: header */
        preg_match( '@([\w/+]+)(;\s*charset=(\S+))?@i', $contenttype, $matches );
        if ( isset( $matches[3] ) ) {
            $charset = $matches[3];
        }

        /* 2: <meta> element in the page */
        if (!isset($charset)) {
            preg_match( '@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s*charset=([^\s"]+))?@i', $data, $matches );
            if ( isset( $matches[3] ) ) {
                $charset = $matches[3];
            }
        }

        /* 3: <xml> element in the page */
        if (!isset($charset)) {
            preg_match( '@<\?xml.+encoding="([^\s"]+)@si', $data, $matches );
            if ( isset( $matches[1] ) ) {
                $charset = $matches[1];
            }
        }

        /* 4: PHP's heuristic detection */
        if (!isset($charset)) {
            $encoding = mb_detect_encoding($data);
            if ($encoding) {
                $charset = $encoding;
            }
        }

        // 5: Default for HTML.
        if (!isset($charset)) {
            if (strstr($contenttype, "text/html") === 0) {
                $charset = "ISO 8859-1";
            }
        }

        /* Convert it if it is anything but UTF-8 */
        /* You can change "UTF-8"  to "UTF-8//IGNORE" to
           ignore conversion errors and still output something reasonable */
        if (isset($charset) && strtoupper($charset) != "UTF-8") {
             $result->contents  = iconv($charset, 'UTF-8', $result->contents);
        }

        $result->httpcode         = curl_getinfo($s, CURLINFO_HTTP_CODE );
        $result->filesize         = curl_getinfo($s, CURLINFO_SIZE_DOWNLOAD);
        $mimetype                 = curl_getinfo($s, CURLINFO_CONTENT_TYPE);
        $mimetype                 = preg_replace('/;.*/', '', $mimetype);
        $result->mimetype         = $mimetype;
        $result->lastcrawled      = time();
        $result->downloadduration = curl_getinfo($s, CURLINFO_TOTAL_TIME);
        $final                    = curl_getinfo($s, CURLINFO_EFFECTIVE_URL);
        if ($final != $url) {
            $result->redirect = $final;
        } else {
            $result->redirect = '';
        }

        curl_close($s);
        return $result;
    }

}


