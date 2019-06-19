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
 * tool_crawler
 *
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_crawler\robot;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/admin/tool/crawler/lib.php');
require_once($CFG->dirroot.'/admin/tool/crawler/locallib.php');
require_once($CFG->dirroot.'/admin/tool/crawler/extlib/simple_html_dom.php');
require_once($CFG->dirroot.'/user/lib.php');

/**
 * tool_crawler
 *
 * @package    tool_crawler
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
            'retentionperiod' => 86400, // 1 week.
            'recentactivity' => 1
        );
        $config = (object) array_merge( $defaults, (array) get_config('tool_crawler') );
        return $config;
    }

    /**
     * Checks that the bot user exists and password works etc
     *
     * @return null|string On success, null. In the case of failure, an error string (which is an HTML snippet).
     */
    public function is_bot_valid() {

        global $DB, $CFG;

        $botusername  = self::get_config()->botusername;
        if (!$botusername) {
            return get_string('configmissing', 'tool_crawler');
        }
        $botuser = $DB->get_record('user', array('username' => $botusername));
        if ( !$botuser ) {
            return get_string('botusermissing', 'tool_crawler') .
                ' <a href="?action=makebot">' . get_string('autocreate', 'tool_crawler') . '</a>';
        }

        // Do a test crawl over the network.
        $result = $this->scrape($CFG->wwwroot.'/admin/tool/crawler/tests/test1.php');
        if ($result->httpcode != '200') {
            return get_string('botcantgettestpage', 'tool_crawler');
        }
        if ($result->redirect) {
            return get_string('bottestpageredirected', 'tool_crawler',
                array('resredirect' => htmlspecialchars($result->redirect, ENT_NOQUOTES | ENT_HTML401)));
        }

        // When the bot successfully scraped the test page (see above), it was logged in and used its own language. So we have to
        // retrieve the expected string in the language set for the _crawler user_, and not in the _current user’s_ language.
        $oldforcelang = force_current_language($botuser->lang);
        $expectedcontent = get_string('hellorobot', 'tool_crawler',
                array('botusername' => self::get_config()->botusername));
        force_current_language($oldforcelang);

        $hello = strpos($result->contents, $expectedcontent);
        if (!$hello) {
            return get_string('bottestpagenotreturned', 'tool_crawler');
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
     * Convert a relative URL to an absolute URL
     *
     * @param string $base URL
     * @param string $rel relative URL
     * @return string absolute URL
     */
    public function absolute_url($base, $rel) {
        // Return if already absolute URL.
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        // Handle links which are only queries or anchors.
        if ($rel && ($rel[0] == '#' || $rel[0] == '?')) {
            return $base.$rel;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'];
        if (isset($parts['path'])) {
            $path = $parts['path'];
        } else {
            $path = '/';
        }
        $host = $parts['host'];

        if (isset($parts['port'])) {
            $port = $parts['port'];
        }

        if ($rel && $rel[0] == '/') {
            if (isset($port)) {
                $abs = $host . ':' . $port . $rel;
            } else {
                $abs = $host . $rel;
            }
        } else {

            // Remove non-directory element from path.
            $path = preg_replace('#/[^/]*$#', '', $path);

            // Dirty absolute URL.
            if (isset($port)) {
                $abs = $host . ':' . $port . $path . '/' . $rel;
            } else {
                $abs = $host . $path . '/' . $rel;
            }
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
     * Returns whether a given URI is external. A URI is external if and only if it does not belong to this Moodle installation.
     *
     * @param string $url The URI to test.
     * @return boolean Whether the URI is external.
     */
    public static function is_external($url) {
        global $CFG;

        if ($url === $CFG->wwwroot) {
            return false;
        }

        $mdlw = strlen($CFG->wwwroot);
        return (strncmp($url, $CFG->wwwroot . '/', $mdlw + 1) != 0);
    }


    /**
     * Reset a node to be recrawled
     *
     * @param integer $nodeid node id
     */
    public function reset_for_recrawl($nodeid) {

        global $DB;

        if ($DB->get_record('tool_crawler_url', array('id' => $nodeid))) {

            $time = self::get_config()->crawlstart;

            // Mark all nodes that link to this as needing a recrawl.
            if ($DB->get_dbfamily() == 'mysql') {
                $DB->execute("UPDATE {tool_crawler_url} u
                         INNER JOIN {tool_crawler_edge} e ON e.a = u.id
                         SET needscrawl = ?,
                                 lastcrawled = null
                         WHERE e.b = ?", [$time, $nodeid]);
            } else {
                $DB->execute("UPDATE {tool_crawler_url} u
                             SET needscrawl = ?,
                                 lastcrawled = null
                            FROM {tool_crawler_edge} e
                           WHERE e.a = u.id
                             AND e.b = ?", [$time, $nodeid]);
            }

            // Delete all edges that point to this node.
            $DB->execute("DELETE
                          FROM {tool_crawler_edge}
                          WHERE b = ?", array($nodeid));

            // Delete the 'to' node as it may be completely wrong.
            $DB->delete_records('tool_crawler_url', array('id' => $nodeid) );

        }
    }

    /**
     * Many URLs are in the queue now (more will probably be added)
     *
     * @return size of queue
     */
    public function get_queue_size() {
        global $DB;

        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {tool_crawler_url}
                 WHERE lastcrawled IS NULL
                    OR lastcrawled < needscrawl");
    }

    /**
     * Adds a URL to the queue for crawling
     *
     * @param string $baseurl
     * @param string $url relative URL
     * @param int the course id if it is known.
     * @return object|boolean The node record if the resource pointed to by the URL can and should be considered; or `false` if the
     *     URL is invalid or excluded.
     */
    public function mark_for_crawl($baseurl, $url, $courseid = null) {

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

        $bad = 0;
        // If this URL is external then check the ext whitelist.
        if (!self::is_external($url)) {
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

        // Now we strip out any unwanted URL params.
        $murl = new \moodle_url($url);
        $excludes = str_replace("\r", '', self::get_config()->excludemdlparam);
        $excludes = explode("\n", $excludes);
        $murl->remove_params($excludes);
        $url = $murl->raw_out(false);

        // Some special logic, if it looks like a course URL or module URL
        // then avoid scraping the URL at all, if it has been excluded.
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
        if (preg_match('/\/course\/(.*?)\//', $url, $matches) ) {
            $course = $DB->get_record('course', array('shortname' => $matches[1]));
            if ($course) {
                $shortname = $course->shortname;
            }
        }
        if ($shortname !== '' && $shortname !== null) {
            $bad = 0;
            $excludes = str_replace("\r", '', self::get_config()->excludecourses);
            $excludes = explode("\n", $excludes);
            if (count($excludes) > 0) {
                foreach ($excludes as $exclude) {
                    $exclude = trim($exclude);
                    if ($exclude == '') {
                        continue;
                    }
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

        // Find the current node in the queue.
        $node = $DB->get_record('tool_crawler_url', array('url' => $url) );

        if (!$node) {
            // If not in the queue then add it.
            $node = (object) array();
            $node->createdate = time();
            $node->url        = $url;
            $node->external   = self::is_external($url);
            $node->needscrawl = time();

            if (isset($courseid)) {
                $node->courseid = $courseid;
            }

            $node->id = $DB->insert_record('tool_crawler_url', $node);
        } else if ( $node->needscrawl < self::get_config()->crawlstart ) {
            // Push this node to the end of the queue.
            $node->needscrawl = time();

            if (isset($courseid)) {
                $node->courseid = $courseid;
            }

            $DB->update_record('tool_crawler_url', $node);
        }
        return $node;
    }

    /**
     * How many URLs have been processed off the queue
     *
     * @return size of processes list
     */
    public function get_processed() {
        global $DB;

        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {tool_crawler_url}
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
                  FROM {tool_crawler_edge}
                 WHERE lastmod >= ?",
                array(self::get_config()->crawlstart));
    }

    /**
     * How many URLs have are broken
     *
     * @return number
     */
    public function get_num_broken_urls() {
        global $DB;

        // What about 20x?
        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {tool_crawler_url}
                 WHERE httpcode != '200'");
    }

    /**
     * How many URLs have broken outgoing links
     *
     * @return number
     */
    public function get_pages_withbroken_links() {
        global $DB;

        // What about 20x?
        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {tool_crawler_url} b
                  JOIN {tool_crawler_edge} l ON l.b = b.id
                 WHERE b.httpcode != '200'");
    }

    /**
     * How many URLs are oversize
     *
     * @return number
     */
    public function get_num_oversize() {
        global $DB;

        $oversizesqlfilter = tool_crawler_sql_oversize_filter();

        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {tool_crawler_url}
                 WHERE {$oversizesqlfilter['wherecondition']}
                ", $oversizesqlfilter['params']);
    }

    /**
     * How many URLs have been processed off the previous queue
     *
     * @return size of old processes list
     */
    public function get_old_queue_size() {
        global $DB;

        // TODO this logic is wrong and will pick up multiple previous sessions.
        return $DB->get_field_sql("
                SELECT COUNT(*)
                  FROM {tool_crawler_url}
                 WHERE lastcrawled < ?",
               array(self::get_config()->crawlstart));
    }

    /**
     * Pops an item off the queue and processes it
     *
     * @param boolean $verbose show debugging
     * @return true if it did anything, false if the queue is empty
     */
    public function process_queue($verbose = false) {

        global $DB;
        $config = $this::get_config();

        if ($config->uselogs == 1) {
            $recentcourses = $this->get_recentcourses();
        }

        // Iterate through the queue until we find an item that is a recent course, or the time runs out.
        $cronstart = time();
        $cronstop = $cronstart + $config->maxcrontime;
        $hasmore = true;
        $hastime = true;
        while ($hasmore && $hastime) {
            // Grab the first item from the queue.
            $node = $DB->get_record_sql('SELECT *
                                         FROM {tool_crawler_url}
                                        WHERE lastcrawled IS NULL
                                           OR lastcrawled < needscrawl
                                     ORDER BY needscrawl ASC, id ASC
                                        LIMIT 1
                                    ');

            if ($config->uselogs == 1) {

                if (isset($node->courseid)) {

                    // If the course id is not in recent courses, remove it from the queue.
                    if (!in_array($node->courseid, $recentcourses)) {

                        // Will not show up in queue, but still keeps the data
                        // in case the course becomes recently active in the future.
                        $node->needscrawl = $node->lastcrawled;
                        $DB->update_record('tool_crawler_url', $node);
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            } else {
                break;
            }

            $hastime = time() < $cronstop;
            set_config('crawltick', time(), 'tool_crawler');
        }

        if (isset($node) && $node !== false) {
            $this->crawl($node, $verbose);
            return true;
        }

        return false;

    }

    /**
     * Takes a queue item and crawls it
     *
     * It crawls a single URL and then passes it off to a mime type handler
     * to pull out the links to other URLs
     *
     * @param object $node a node
     * @param boolean $verbose show debugging
     */
    public function crawl($node, $verbose = false) {

        global $DB;

        if ($verbose) {
            echo "Crawling $node->url ";
        }

        // Function scrape writes to the title property only if there has been a download error. The title may be set by function
        // parse_html later. If it is not, we do not have a valid title. In order to have the _proper_ title (set or null) stored in
        // the database in the end in case of recrawls, we must clear the existing title here (only to maybe re-add it in a few
        // fractions of a second).
        $node->title = null;

        // Scraping returns info about the URL. Not info about the courseid and context, just the URL itself.
        $result = $this->scrape($node->url);
        $result = (object) array_merge((array) $node, (array) $result);

        if ($result->redirect && $verbose) {
            echo "=> $result->redirect ";
        }
        if ($verbose) {
            echo "($result->httpcode) ";
        }
        if ($result->httpcode == '200') {

            if ($result->mimetype == 'text/html') {
                if ($verbose) {
                    echo "html\n";
                }

                // Look for new links on this page from the html.
                // Insert new links into tool_crawler_edge, and into tool_crawler_url table.
                // Find the course, cm, and context of where we are for the main scraped URL.
                $this->parse_html($result, $result->external, $verbose);
            } else {
                if ($verbose) {
                    echo "NOT html\n";
                }
            }
            // Else TODO Possibly we can infer the course purely from the URL
            // Maybe the plugin serving urls?
        } else {
            if ($verbose) {
                echo "\n";
            }
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

        if ($result->title && !$detectutf8($result->title)) {
            $result->title = utf8_decode($result->title);
        }

        // Wait until we've finished processing the links before we save.
        $DB->update_record('tool_crawler_url', $result);

    }

    /**
     * Decodes HTML character entity references in a given text and returns the text with them replaced. Intended to be used on
     * texts obtained from simple_html_dom, because they are returned with entity references intact.
     *
     * @param string $text The text which may contain HTML character entity references, in UTF-8 encoding.
     * @return string The text with all character entity references resolved, in UTF-8 encoding.
     */
    protected static function dom_text_decode_entities($text) {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Converts an HTML DOM node to a plain text form. This is done by removing script and style elements, and by replacing images
     * with their alternative text. Can be used to clean HTML from unwanted and potentially unsafe user-provided content.
     *
     * @param simple_html_dom_node $node The DOM node to convert.
     * @return string The string representation of the DOM node. May be the empty string.
     */
    protected static function clean_html_node_content($node) {
        if (!$node) {
            return '';
        }

        if ($node->nodetype !== HDOM_TYPE_ELEMENT) {
            return self::dom_text_decode_entities($node->plaintext);
        }

        $elementname = mb_strtolower($node->tag, 'UTF-8');

        $ignoredelements = array('script', 'style');
        if (in_array($elementname, $ignoredelements)) {
            return '';
        } else if ($elementname == 'img') {
            return $node->alt ? self::dom_text_decode_entities($node->alt) : '';
        }

        if (!$node->nodes) {
            return '';
        }

        $content = '';
        foreach ($node->nodes as $sub) {
            $content .= self::clean_html_node_content($sub);
        }
        return $content;
    }

    /**
     * Given a recently crawled node, extract links to other pages
     *
     * Should only be run on internal moodle pages, ie never follow
     * links on external pages. We don't want to scrape the whole web!
     *
     * @param object $node a URL node
     * @param boolean $external is the URL ourside moodle
     * @param boolean $verbose show debugging
     */
    private function parse_html($node, $external, $verbose = false) {

        global $CFG;
        $config = self::get_config();

        $raw = $node->contents;

        // Strip out any data URIs - the parser doesn't like them.
        $raw = preg_replace('/"data:[^"]*?"/', '', $raw);

        $html = str_get_html($raw);

        // If couldn't parse html.
        if (!$html) {
            if ($verbose) {
                echo " - Didn't find any html, stopping.\n";
            }
            return;
        }

        $titlenode = $html->find('title', 0);
        if (isset($titlenode)) {
            $node->title = self::dom_text_decode_entities($titlenode->plaintext);
            if ($verbose) {
                echo " - Found title of: '$node->title'\n";
            }
        } else {
            if ($verbose) {
                echo "Did not find a title.\n";
            }
        }

        // Everything after this is only for internal moodle pages.
        // External is set when this link is crawled, in scrape().
        if ($external) {
            if ($verbose) {
                echo " - External so stopping here.\n";
            }
            return $node;
        }

        // Remove any chunks of DOM that we know to be safe and don't want to follow.
        $excludes = explode("\n", $config->excludemdldom);
        foreach ($excludes as $exclude) {
            foreach ($html->find($exclude) as $e) {
                $e->outertext = ' ';
            }
        }

        // Store some context about where we are, the crawled URL.
        foreach ($html->find('body') as $body) {
            // Grabs the course, context, cmid from the classes in the html body section.
            $classes = explode(" ", $body->class);

            $hascourse = false;
            foreach ($classes as $cl) {
                if (substr($cl, 0, 7) == 'course-') {
                    $node->courseid = intval(substr($cl, 7));
                    $hascourse = true;
                }
                if (substr($cl, 0, 8) == 'context-') {
                    $node->contextid = intval(substr($cl, 8));
                }
                if (substr($cl, 0, 5) == 'cmid-') {
                    $node->cmid = intval(substr($cl, 5));
                }
            }

            if ($config->uselogs == 1) {
                // If this page does not have a course specified in it's classes, don't parse the html.
                if ($hascourse === false) {
                    if ($verbose) {
                        echo "No course specified in the html, stopping here.\n";
                    }
                    return $node;
                }
                // If this course has not been viewed recently, then don't continue on to parse the html.
                $recentcourses = $this->get_recentcourses();
                if (!in_array($node->courseid, $recentcourses)) {
                    if ($verbose) {
                        if ($node->courseid == 1) {
                            echo "Ignore index.php page.\n";
                        } else {
                            echo "Course with id " . $node->courseid . " has not been viewed recently, skipping.\n";
                        }
                    }
                    return $node;
                }
            }
        }

        // Finds each link in the html and adds to database.
        $seen = array();

        $links = $html->find('a[href]');
        foreach ($links as $e) {
            $href = $e->href;
            $href = htmlspecialchars_decode($href);

            // We ignore links which are internal to this page.
            if (substr ($href, 0, 1) === '#') {
                continue;
            }

            $href = $this->absolute_url($node->url, $href);

            if (array_key_exists($href, $seen ) ) {
                continue;
            }
            $seen[$href] = 1;

            // Find some context of the link, like the nearest id.
            $idattr = '';
            $walk = $e;
            do {
                $id = $walk->id;
                if (isset($id)) {
                    $id = self::dom_text_decode_entities($id);
                    if ($id != '') {
                        // Ensure that no disallowed characters creep in. See HTML 5.2 about the id attribute.
                        if (preg_match('/[ \\t\\n\\x0C\\r]/', $id) === 0) {
                            $idattr = '#' . $id . ' ' . $idattr;
                        }
                    }
                }
                $walk = $walk->parent;
            } while ($walk);

            $text = self::clean_html_node_content($e);
            if ($verbose > 1) {
                printf (" - Found link to: %-20s / %-50s => %-50s\n", $text, $e->href, $href);
            }
            $this->link_from_node_to_url($node, $href, $text, $idattr);
        }
        return $node;
    }

    /**
     * Upserts a link between two nodes in the URL graph.
     * Which crawled URLs html did we parse to find this link.
     *
     * @param string $from from URL
     * @param string $url current URL
     * @param string $text the link text label
     * @param string $idattr the id attribute of it or it's nearest ancestor
     * @return the new URL node or false
     */
    private function link_from_node_to_url($from, $url, $text, $idattr) {

        global $DB;

        // Add the node URL to the queue.
        $to = $this->mark_for_crawl($from->url, $url);
        if ($to === false) {
            return false;
        }

        // For this link, insert or update with the current time for last modified.
        $link = $DB->get_record('tool_crawler_edge', array('a' => $from->id, 'b' => $to->id));
        if (!$link) {
            $link          = new \stdClass();
            $link->a       = $from->id;
            $link->b       = $to->id;
            $link->lastmod = time();
            $link->text    = $text;
            $link->idattr  = $idattr;
            $link->id = $DB->insert_record('tool_crawler_edge', $link);
        } else {
            $link->lastmod = time();
            $link->idattr  = $idattr;
            $DB->update_record('tool_crawler_edge', $link);
        }
        return $link;
    }

    /**
     * Scrapes a fully qualified URL and returns details about it.
     *
     * The returned object has thus format (properties) that it is ready to be directly inserted into the crawler URL table in the
     * database.
     *
     * @param string $url HTTP/HTTPS URI of the resource which is to be retrieved from the web.
     * @return object The result object.
     */
    public function scrape($url) {

        global $CFG;
        $cookiefilelocation = $CFG->dataroot . '/tool_crawler_cookies.txt';
        $config = self::get_config();

        $s = curl_init();
        curl_setopt($s, CURLOPT_URL,             $url);
        curl_setopt($s, CURLOPT_TIMEOUT,         $config->maxtime);
        if ( $this->should_be_authenticated($url) ) {
            curl_setopt($s, CURLOPT_USERPWD,     $config->botusername . ':' . $config->botpassword);
        }
        curl_setopt($s, CURLOPT_USERAGENT,       $config->useragent . '/' . $config->version . ' (' . $CFG->wwwroot . ')');
        curl_setopt($s, CURLOPT_MAXREDIRS,       5);
        curl_setopt($s, CURLOPT_RETURNTRANSFER,  true);
        curl_setopt($s, CURLOPT_FOLLOWLOCATION,  true);
        curl_setopt($s, CURLOPT_FRESH_CONNECT,   true);
        curl_setopt($s, CURLOPT_HEADER,          true);
        curl_setopt($s, CURLOPT_COOKIEJAR,       $cookiefilelocation);
        curl_setopt($s, CURLOPT_COOKIEFILE,      $cookiefilelocation);
        curl_setopt($s, CURLOPT_SSL_VERIFYHOST,  0);
        curl_setopt($s, CURLOPT_SSL_VERIFYPEER,  0);

        $result = (object) array();
        $result->url              = $url;

        $raw   = curl_exec($s);

        $result->filesize         = curl_getinfo($s, CURLINFO_SIZE_DOWNLOAD);

        $contenttype              = curl_getinfo($s, CURLINFO_CONTENT_TYPE);
        $result->mimetype         = preg_replace('/;.*/', '', $contenttype);

        $result->lastcrawled      = time();

        $result->downloadduration = curl_getinfo($s, CURLINFO_TOTAL_TIME);

        $final                    = curl_getinfo($s, CURLINFO_EFFECTIVE_URL);
        if ($final != $url) {
            $result->redirect = $final;
        } else {
            $result->redirect = '';
        }
        $result->external = self::is_external($final);

        if (empty($raw)) {
            $result->errormsg         = (string)curl_errno($s);
            $result->title            = curl_error($s); // We do not try to translate Curl error messages.
            $result->contents         = '';
            $result->httpcode         = '500';
            $result->httpmsg          = null;
        } else {
            $result->errormsg = null;  // Important in case of repeated scraping in order to reset error status.

            $headersize = curl_getinfo($s, CURLINFO_HEADER_SIZE);
            $headers = substr($raw, 0, $headersize);
            if (preg_match_all('@(^|[\r\n])(HTTP/[^ ]+) ([0-9]+) ([^\r\n]+|$)@', $headers, $httplines, PREG_SET_ORDER)) {
                $result->httpmsg = array_pop($httplines)[4];
            } else {
                $result->httpmsg = '';
            }

            $ishtml = (strpos($contenttype, 'text/html') === 0);
            if ($ishtml) { // Related to Issue #13.
                // May need a significant amount of memory as the data is temporarily stored twice.
                $data = substr($raw, $headersize);
                unset($raw); // Allow to free memory.

                /* Convert it if it is anything but UTF-8 */
                $charset = $this->detect_encoding($contenttype, $data);
                if (is_string($charset) && strtoupper($charset) != "UTF-8") {
                    // You can change 'UTF-8' to 'UTF-8//IGNORE' to
                    // ignore conversion errors and still output something reasonable.
                    $data = iconv($charset, 'UTF-8', $data);
                }
                $result->contents = $data;
            } else {
                $result->contents = '';
            }

            $result->httpcode         = curl_getinfo($s, CURLINFO_HTTP_CODE);
        }

        curl_close($s);
        return $result;
    }

    /**
     * Determines the character encoding of a document from its HTTP Content-Type header and its content.
     *
     * @param string $contenttype The value of the Content-Type header from the HTTP Response message.
     * @param string $data The raw body of the document.
     * @return string|boolean The character encoding declared (or guessed) for the document; `false` if none could be detected.
     */
    private function detect_encoding($contenttype, $data) {
        // See https://stackoverflow.com/questions/9351694/setting-php-default-encoding-to-utf-8 for more.

        unset($charset);

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
            if (strpos($contenttype, "text/html") === 0) {
                $charset = "ISO-8859-1";
            }
        }

        return isset($charset) ? $charset : false;
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
        if (!self::is_external($url)) {
            return true;
        }
        return false;
    }

    /**
     * Grabs the recent courses.
     *
     * @return array
     */
    public function get_recentcourses() {
        global $DB;
        $config = self::get_config();

        $startingtimerecentactivity = strtotime("-$config->recentactivity days", time());

        $sql = "SELECT DISTINCT log.courseid
                                                 FROM {logstore_standard_log} log
                                                WHERE log.timecreated > :startingtime
                                                AND target = 'course'
                                                AND userid NOT IN (
                                                    SELECT id FROM {user} WHERE username = :botusername
                                                )
                                                AND courseid <> 1
                                            ";
        $botusername = isset($config->botusername) ? $config->botusername : '';
        $values = ['startingtime' => $startingtimerecentactivity, 'botusername' => $botusername];

        $rs = $DB->get_recordset_sql($sql, $values);
        $recentcourses = [];
        foreach ($rs as $record) {
            array_push($recentcourses, $record->courseid);
        }
        $rs->close();

        return $recentcourses;
    }
}
