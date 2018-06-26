<?php
// This file is part of Link Checker (tool_crawler)
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
 * @package     tool_crawler
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_crawler\robot;

use stdClass;

defined('MOODLE_INTERNAL') || die();

class scraper {
    private static function get_config() {
        return crawler::get_config();
    }

    public function scrape($url) {
        $result = null;

        try {
            $curl = $this->prepare_curl($url);
            $raw = curl_exec($curl);

            if (empty($raw)) {
                $result = $this->prepare_error_result($url, $curl);
            } else {
                $result = $this->prepare_result($curl, $url, $raw);
            }
        } finally {
            curl_close($curl);
        }

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
        if (strpos($url, $CFG->wwwroot . '/') === 0 || $url === $CFG->wwwroot) {
            return true;
        }
        return false;
    }

    private function prepare_curl($url) {
        global $CFG;

        $cookiefilelocation = $CFG->dataroot . '/tool_crawler_cookies.txt';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::get_config()->maxtime);
        if ($this->should_be_authenticated($url)) {
            curl_setopt($curl, CURLOPT_USERPWD, self::get_config()->botusername . ':' . self::get_config()->botpassword);
        }
        curl_setopt($curl, CURLOPT_USERAGENT,
                    self::get_config()->useragent . '/' . self::get_config()->version . ' (' . $CFG->wwwroot . ')');
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookiefilelocation);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookiefilelocation);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        return $curl;
    }

    private function prepare_error_result($url, $curl) {
        global $CFG;

        $result = new stdClass();
        $result->url = $url;
        $result->httpmsg = 'Curl Error: ' . curl_errno($curl);
        $result->title = curl_error($curl);
        $result->contents = '';
        $result->httpcode = '500';
        $result->filesize = curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);
        $mimetype = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $mimetype = preg_replace('/;.*/', '', $mimetype);
        $result->mimetype = $mimetype;
        $result->lastcrawled = time();
        $result->downloadduration = curl_getinfo($curl, CURLINFO_TOTAL_TIME);
        $final = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        if ($final != $url) {
            $result->redirect = $final;
            $mdlw = strlen($CFG->wwwroot);
            if (substr($final, 0, $mdlw) !== $CFG->wwwroot) {
                $result->external = 1;
            }
        } else {
            $result->redirect = '';
        }

        return $result;
    }

    private function detect_charset($contenttype, $data) {
        /* 1: HTTP Content-Type: header */
        preg_match('@([\w/+]+)(;\s*charset=(\S+))?@i', $contenttype, $matches);
        if (isset($matches[3])) {
            $charset = $matches[3];
        }

        /* 2: <meta> element in the page */
        if (!isset($charset)) {
            preg_match('@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s*charset=([^\s"]+))?@i', $data, $matches);
            if (isset($matches[3])) {
                $charset = $matches[3];
            }
        }

        /* 3: <xml> element in the page */
        if (!isset($charset)) {
            preg_match('@<\?xml.+encoding="([^\s"]+)@si', $data, $matches);
            if (isset($matches[1])) {
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
        return $charset;
    }

    private function convert_to_utf8($contenttype, $data, $result) {
        unset($charset);
        $charset = $this->detect_charset($contenttype, $data);

        /* Convert it if it is anything but UTF-8 */
        /* You can change "UTF-8"  to "UTF-8//IGNORE" to
           ignore conversion errors and still output something reasonable */
        if (isset($charset) && strtoupper($charset) != "UTF-8") {
            $result->contents = iconv($charset, 'UTF-8', $result->contents);
        }
    }

    private function prepare_result($curl, $url, $raw) {
        global $CFG;

        $result = (object)['url' => $url];
        $contenttype = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $ishtml = (strpos($contenttype, 'text/html') === 0); // Related to Issue #13.

        $headersize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $headersize);
        $header = strtok($headers, "\n");
        $result->httpmsg = explode(" ", $header, 3)[2];
        $result->contents = $ishtml ? substr($raw, $headersize) : '';
        $data = $result->contents;
        $this->convert_to_utf8($contenttype, $data, $result);

        $result->httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $result->filesize = curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);
        $mimetype = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $mimetype = preg_replace('/;.*/', '', $mimetype);
        $result->mimetype = $mimetype;
        $result->lastcrawled = time();
        $result->downloadduration = curl_getinfo($curl, CURLINFO_TOTAL_TIME);

        $final = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        if ($final != $url) {
            $result->redirect = $final;
            $mdlw = strlen($CFG->wwwroot);
            if (substr($final, 0, $mdlw) !== $CFG->wwwroot) {
                $result->external = 1;
            }
        } else {
            $result->redirect = '';
        }

        return $result;
    }
}
