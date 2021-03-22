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
 * @package tool_crawler
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_crawler\table;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/tablelib.php');
use table_sql;
use renderable;
use tool_crawler\helper;
use moodle_url;
use html_writer;

class course_links extends table_sql implements renderable {

    private $courseid;
    /**
     * table constructor.
     *
     * @param $uniqueid table unique id
     * @param \moodle_url $url base url
     * @param int $page current page
     * @param int $perpage number of records per page
     * @throws \coding_exception
     * @throws \coding_exception
     */
    public function __construct($uniqueid, \moodle_url $url, $courseid, $page = 0, $perpage = 20) {
        parent::__construct($uniqueid);

        $this->set_attribute('class', 'tool_crawler');

        // Set protected properties.
        $this->pagesize = $perpage;
        $this->page = $page;
        $this->courseid = $courseid;

        // Define columns in the table.
        $this->define_table_columns();

        // Define configs.
        $this->define_table_configs($url);
    }

    /**
     * Table columns and corresponding headers.
     * @throws \coding_exception
     */
    protected function define_table_columns() {
        $cols = array(
            'lastcrawledtime' => get_string('lastcrawledtime', 'tool_crawler'),
            'priority' => get_string('priority', 'tool_crawler'),
            'response' => get_string('response', 'tool_crawler'),
            'httpcode' => get_string('httpcode', 'tool_crawler'),
            'target' => get_string('links', 'tool_crawler'),
            'url' => get_string('frompage', 'tool_crawler'),
        );

        $this->define_columns(array_keys($cols));
        $this->define_headers(array_values($cols));
    }

    /**
     * Define table configuration.
     * @param \moodle_url $url
     * @throws \coding_exception
     */
    protected function define_table_configs(\moodle_url $url) {
        // Set table url.
        $this->define_baseurl($url);

        // Set table configs.
        $this->collapsible(false);
        $this->sortable(true);
        $this->pageable(true);
    }

    /**
     * Get sql query
     * @param bool $count whether count or get records.
     * @return array
     */
    protected function get_sql_and_params($count = false) {
        if ($count) {
            $select = "COUNT(1)";
        } else {
            $select = "concat(b.id, '-', l.id, '-', a.id) AS id,
                                          b.url target,
                                          b.httpcode,
                                          b.httpmsg,
                                          b.errormsg,
                                          b.lastcrawled,
                                          b.priority,
                                          b.id AS toid,
                                          l.id linkid,
                                          l.text,
                                          a.url,
                                          a.title,
                                          a.redirect,
                                          a.courseid,
                                          c.shortname,
                                          CASE WHEN b.httpcode = '200' THEN 'Working'
                                          ELSE 'Not working' END
                                          AS response";
        }

        $sql = "SELECT {$select}
                  FROM {tool_crawler_url}  b
             LEFT JOIN {tool_crawler_edge} l ON l.b = b.id
             LEFT JOIN {tool_crawler_url}  a ON l.a = a.id
             LEFT JOIN {course} c ON c.id = a.courseid
                 WHERE c.id = $this->courseid";

        if (!$count ) {
            $sort = $this->get_sql_sort();
            if (!empty($sort)) {
                $sql .= " ORDER BY $sort";
            }
        }

        return array($sql, []);
    }

    /**
     * Get data.
     * @param int $pagesize number of records to fetch
     * @param bool $useinitialsbar initial bar
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        list($countsql, $countparams) = $this->get_sql_and_params(true);
        list($sql, $params) = $this->get_sql_and_params();
        $total = $DB->count_records_sql($countsql, $countparams);
        $this->pagesize($pagesize, $total);
        $records = $DB->get_records_sql($sql, $params, $this->pagesize * $this->page, $this->pagesize);
        foreach ($records as $history) {
            $this->rawdata[] = $history;
        }
        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }

    /**
     *
     * @param $row
     * @return string
     */
    protected function col_lastcrawledtime($row) {
        return userdate($row->lastcrawled);
    }

    /**
     *
     * @param $row
     * @return string
     * @throws \coding_exception
     */
    protected function col_priority($row) {
        return tool_crawler_priority_level($row->priority);
    }

    /**
     *
     * @param $row
     * @return mixed
     * @throws \coding_exception
     */
    protected function col_httpcode($row) {
        $text = tool_crawler_http_code($row);
        if ($translation = \tool_crawler\helper::translate_httpcode($row->httpcode)) {
            $text .= "<br/>" . $translation;
        }
        return $text;
    }

    /**
     *
     * @param $row
     * @return mixed
     * @throws \coding_exception
     */
    protected function col_target($row) {
        $text = trim($row->text);
        if ($text == "") {
            $text = get_string('missing', 'tool_crawler');
            $text = htmlspecialchars($text, ENT_NOQUOTES | ENT_HTML401);
            // May add a bit of markup here so that the user can differentiate the "missing" message from an equal link text.
        } else {
            $text = htmlspecialchars($text, ENT_NOQUOTES | ENT_HTML401);
        }
        return tool_crawler_link($row->target, $text, $row->redirect, true, $this->courseid);
    }

    /**
     *
     * @param $row
     * @return mixed
     * @throws \coding_exception
     */
    protected function col_url($row) {
        return tool_crawler_link($row->url, $row->title, $row->redirect, false, $this->courseid);
    }

}
