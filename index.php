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
 * A link checker robot
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('local_linkchecker_robot_status');

echo $OUTPUT->header();

$action         = optional_param('action', '', PARAM_ALPHANUMEXT);
$config         = get_config('local_linkchecker_robot');

$robot = new \local_linkchecker_robot\robot\crawler();

if ($action == 'makebot') {

    $botuser = $robot->auto_create_bot();

}

$crawlstart     = $robot->get_crawlstart();
$crawlend       = $robot->get_last_crawlend();
$crawltick      = $robot->get_last_crawltick();
$boterror       = $robot->is_bot_valid();
$queuesize      = $robot->get_queue_size();
$recent         = $robot->get_processed();
$oldqueuesize   = $robot->get_old_queue_size();

$broken = $DB->get_field_sql("SELECT COUNT(*)
                                 FROM {linkchecker_url}
                                WHERE httpcode != ?", array('200') );


$bigfilesize = $config->bigfilesize;
$opts = array($bigfilesize * 1000000);
$oversize = $DB->get_field_sql("SELECT COUNT(*)
                                 FROM {linkchecker_url}
                                WHERE filesize > ?",  $opts );

$table = new html_table();
$table->head = array(get_string('robotstatus', 'local_linkchecker_robot'));
$table->headspan = array(2, 1);
$table->data = array(
    array(
        get_string('botuser', 'local_linkchecker_robot'),
        $boterror ? $boterror : get_string('good', 'local_linkchecker_robot')
    ),
    array(
        get_string('curcrawlstart', 'local_linkchecker_robot'),
        $crawlstart ? userdate( $crawlstart) : get_string('neverrun', 'local_linkchecker_robot')
    ),
    array(
        get_string('lastcrawlend', 'local_linkchecker_robot'),
        $crawlend ? userdate( $crawlend) : get_string('neverfinished', 'local_linkchecker_robot')
    ),
    array(
        get_string('lastcrawlproc', 'local_linkchecker_robot'),
        $crawltick ? userdate( $crawltick) : '-'
    ),
    array(
        get_string('lastqueuesize', 'local_linkchecker_robot'),
        $oldqueuesize
    ),
    array(
        get_string('queued', 'local_linkchecker_robot'),
        "<a href=\"report.php?report=queued\">$queuesize</a>"
    ),
    array(
        get_string('recent', 'local_linkchecker_robot'),
        "<a href=\"report.php?report=recent\">$recent</a>"
    ),
    array(
        get_string('broken', 'local_linkchecker_robot'),
        "<a href=\"report.php?report=broken\">$broken</a>"
    ),
    array(
        get_string('oversize', 'local_linkchecker_robot'),
        "<a href=\"report.php?report=oversize\">$oversize</a>"
    )
);

echo "<br />";
echo html_writer::table($table);

echo $OUTPUT->footer();
