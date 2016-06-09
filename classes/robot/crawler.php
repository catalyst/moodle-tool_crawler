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
     * Returns configuration object if it has been initialised.
     * If it is not initialises then it creates and returns it.
     *
     * @return mixed hash-like object or default array $defaults if no config found.
     */
    public static function get_config() {
        $defaults = array(
            'crawlstart' => 0,
            'crawlend' => 0,
            'crawltick' => 0,
            'retentionperiod' => 86400 // 1 week.
        );
        $config = (object) array_merge( $defaults, (array) get_config('local_linkchecker_robot') );
        return $config;
    }

    /**
     * Checks that the bot user exists and password works etc
     *
     * @return mixed true or a error string
     */
    public function is_bot_valid() {

        global $DB, $CFG;

        $botusername  = self::get_config()->botusername;
        if (!$botusername) {
            return get_string('configmissing', 'local_linkchecker_robot');
        }
        if ( !$DB->get_record('user', array('username' => $botusername)) ) {
            return get_string('botusermissing', 'local_linkchecker_robot') .
                ' <a href="?action=makebot">' . get_string('autocreate', 'local_linkchecker_robot') . '</a>';
        }

        // Do a test crawl over the network.
        $result = $this->scrape($CFG->wwwroot.'/local/linkchecker_robot/tests/test1.php');
        if ($result->httpcode != '200') {
            return get_string('botcantgettestpage');
        }
        if ($result->redirect) {
            return get_string('bottestpageredirected', 'local_linkchecker_robot',
                array('resredirect' => $result->redirect));
        }

        $hello = strpos($result->contents, get_string('hellorobot', 'local_linkchecker_robot',
                array('botusername' => self::get_config()->botusername)));
        if (!$hello) {
            return get_string('bottestpagenotreturned', 'local_linkchecker_robot');
        }
    }

    /**
     * Auto create the moodle user that the robot logs in as
     */
    public function auto_create_bot() {

        global $DB, $CFG;

        // TODO roles?

        $botusername  = self::get_config()->botusername;
        $botuser = $DB->get_record('user', array('username' => $botusername) );
        if ($botuser) {
            return $botuser;
        } else {
            $botuser = (object) array();
            $botuser->username   = $botusername;
            $botuser->password   = hash_internal_user_password(self::get_config()->botpassword);
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
        // Return if already absolute URL.
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        // Handle links which are only queries or anchors.
        if ($rel[0] == '#' || $rel[0] == '?') {
            return $base.$rel;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'];
        $path = $parts['path'];
        $host = $parts['host'];

        if ($rel[0] == '/') {
            $abs = $host . $rel;
        } else {

            // Remove non-directory element from path.
            $path = preg_replace('#/[^/]*$#', '', $path);

            // Dirty absolute URL.
            $abs = "$host$path/$rel";
        }

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

            $time = self::get_config()->crawlstart;

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
            $excludes = str_replace("\r", '', self::get_config()->excludemdlurl);
        } else {
            $excludes = str_replace("\r", '', self::get_config()->excludeexturl);
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

        // We ignore differences in hash anchors.
        $url = strtok($url, "#");

        // Now we strip out any unwanted url params.
        $murl = new \moodle_url($url);
        $excludes = str_replace("\r", '', self::get_config()->excludemdlparam);
        $excludes = explode("\n", $excludes);
        $murl->remove_params($excludes);
        $url = $murl->out();

        // Some special logic, if it looks like a course url or module url
        // then avoid scraping the URL at all.
        $shortname = '';
        if (preg_match('/\/course\/(info|view).php\?id=(\d+)/', $url , $matches) ) {
            $course = $DB->get_record('course', array('id' => $matches[2]));
            if ($course) {
                $shortname = $course->shortname;
            }
        }
        if (preg_match('/\/enrol\/index.php\?id=(\d+)/', $url , $matches) ) {
            $course = $DB->get_record('course', array('id' => $matches[1]));
            if ($course) {
                $shortname = $course->shortname;
            }
        }
        if (preg_match('/\/mod\/(\w+)\/(index|view).php\?id=(\d+)/', $url , $matches) ) {
            $cm = $DB->get_record_sql("
                    SELECT cm.*,
                           c.shortname
                      FROM {course_modules} cm
                      JOIN {course} c ON cm.course = c.id
                     WHERE cm.id = ?", array($matches[3]));
            if ($cm) {
                $shortname = $cm->shortname;
            }
        }
        if ($shortname) {
            $bad = 0;
            $excludes = str_replace("\r", '', self::get_config()->excludecourses);
            $excludes = explode("\n", $excludes);
            if (count($excludes) > 0 && $excludes[0]) {
                foreach ($excludes as $exclude) {
                    if (strpos($shortname, $exclude) !== false ) {
                        $bad = 1;
                        break;
                    }
                }
            }
            if ($bad) {
                return false;
            }
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
        } else if ( $node->needscrawl < self::get_config()->crawlstart ) {
            $node->needscrawl = time();
            $DB->update_record('linkchecker_url', $node);
        }
        return $node;
    }

    /**
     * Many urls are in the queue now (more will probably be added)
     *
     * @return size of queue
     */
    public function get_queue_size() {
        global $DB;

        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {linkchecker_url}
                 WHERE lastcrawled IS NULL
                    OR lastcrawled < needscrawl");
    }

    /**
     * How many urls have been processed off the queue
     *
     * @return size of processes list
     */
    public function get_processed() {
        global $DB;

        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {linkchecker_url}
                 WHERE lastcrawled >= ?",
                array(self::get_config()->crawlstart));
    }

    /**
     * How many links have been processed off the queue
     *
     * @return size of processes list
     */
    public function get_num_links() {
        global $DB;

        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {linkchecker_edge}
                 WHERE lastmod >= ?",
                array(self::get_config()->crawlstart));
    }

    /**
     * How many urls have are broken
     *
     * @return number
     */
    public function get_num_broken_urls() {
        global $DB;

        // What about 20x?
        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {linkchecker_url}
                 WHERE httpcode != '200'");
    }

    /**
     * How many urls have broken outgoing links
     *
     * @return number
     */
    public function get_pages_withbroken_links() {
        global $DB;

        // What about 20x?
        return $DB->get_field_sql("
                SELECT COUNT (*)
                  FROM mdl_linkchecker_url b
                  JOIN mdl_linkchecker_edge l ON l.b = b.id
                 WHERE b.httpcode != '200'");
    }

    /**
     * How many urls are oversize
     *
     * @return number
     */
    public function get_num_oversize() {
        global $DB;

        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {linkchecker_url}
                 WHERE filesize > ?", array(self::get_config()->bigfilesize * 1000000));
    }

    /**
     * How many urls have been processed off the previous queue
     *
     * @return size of old processes list
     */
    public function get_old_queue_size() {
        global $DB;

        // TODO this logic is wrong and will pick up multiple previous sessions.
        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {linkchecker_url}
                 WHERE lastcrawled < ?",
               array(self::get_config()->crawlstart));
    }

    /**
     * Pops an item off the queue and processes it
     *
     * @return true if it did anything, false if the queue is empty
     */
    public function process_queue($verbose = false) {

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
            if ($verbose) {
                print "Crawling $node->url\n";
            }
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
    public function crawl($node, $verbose = false) {

        global $DB;

        if ($verbose) {
            echo "Scraping $node->url\n";
        }
        $result = $this->scrape($node->url);
        $result = (object) array_merge((array) $node, (array) $result);

        if ($verbose) {
            echo "Response code of $result->httpcode\n";
        }
        if ($result->httpcode == '200') {

            if ($result->mimetype == 'text/html') {
                if ($verbose) {
                    echo "Parsing html...\n";
                }
                $this->parse_html($result, $result->external, $verbose);
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
     * @param boolean $external print verbose info
     */
    private function parse_html($node, $external, $verbose = false) {

        global $CFG;

        $raw = $node->contents;

        // Strip out any data uri's - the parser doesn't like them.
        $raw = preg_replace('/"data:[^"]*?"/', '', $raw);

        $html = str_get_html($raw);

        // If couldn't parse html.
        if (!$html) {
            if ($verbose) {
                echo "Didn't find any html, stopping.\n";
            }
            return;
        }

        $node->title = $html->find('title', 0)->plaintext;
        if ($verbose) {
            echo "Found title of: '$node->title'\n";
        }

        // Everything after this is only for internal moodle pages.
        if ($external) {
            if ($verbose) {
                echo "External so stopping here.\n";
            }
            return $node;
        }

        // Remove any chunks of DOM that we know to be safe and don't want to follow.
        $excludes = explode("\n", self::get_config()->excludemdldom);
        foreach ($excludes as $exclude) {
            foreach ($html->find($exclude) as $e) {
                $e->outertext = '';
            }
        }

        $seen = array();
        foreach ($html->find('a[href]') as $e) {
            $href = $e->href;
            $href = str_replace('&amp;', '&', $href);

            // We ignore links which are internal to this page.
            if (substr ($href, 0, 1) === '#') {
                continue;
            }

            // should make absolute here?
            $href = $this->absolute_url($node->url, $href);

            if (array_key_exists($href, $seen ) ) {
                continue;
            }
            $seen[$href] = 1;

            // If this url is external then check the ext whitelist.
            $mdlw = strlen($CFG->wwwroot);
            $bad = 0;
            if (substr ($href, 0, $mdlw) === $CFG->wwwroot) {
                $excludes = str_replace("\r", '', self::get_config()->excludemdlurl);
            } else {
                $excludes = str_replace("\r", '', self::get_config()->excludeexturl);
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
            if ($verbose) {
                printf ("Found link to: %-20s / %-50s => %-50s\n", format_string($e->innertext), $e->href, $href);
            }
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
        curl_setopt($s, CURLOPT_TIMEOUT,         self::get_config()->maxtime);
        if ( $this->should_be_authenticated($url) ) {
            curl_setopt($s, CURLOPT_USERPWD,         self::get_config()->botusername.':'.self::get_config()->botpassword);
        }
        curl_setopt($s, CURLOPT_USERAGENT,
            self::get_config()->useragent . '/' . self::get_config()->version . ' ('.$CFG->wwwroot.')' );
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

    /**
     * Checks whether robot should authenticate or not.
     * Bot should authenticate if URL it is crawling over is local URL
     * And bot should not authenticate when crawling over external URLs.
     *
     * @param string $url
     * @return boolean
     */
    public function should_be_authenticated($url) {
        global $CFG;
        if ( strpos($url, $CFG->wwwroot.'/') === 0 || $url === $CFG->wwwroot ) {
            return true;
        }
        return false;
    }
}


