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

$robot = new \local_linkchecker_robot\robot\crawler();
$config = $robot::get_config();

if ($action == 'makebot') {

    $botuser = $robot->auto_create_bot();

}

$crawlstart     = $config->crawlstart;
$crawlend       = $config->crawlend;
$crawltick      = $config->crawltick;
$boterror       = $robot->is_bot_valid();
$queuesize      = $robot->get_queue_size();
$recent         = $robot->get_processed();
$numlinks       = $robot->get_num_links();
$oldqueuesize   = $robot->get_old_queue_size();
$numurlsbroken  = $robot->get_num_broken_urls();
$numpageswithurlsbroken = $robot->get_pages_withbroken_links();
$oversize       = $robot->get_num_oversize();

if ($queuesize == 0) {
    $progress = 1;
} else if ($oldqueuesize == 0) {
    $progress = $recent / ($recent + $queuesize);
} else {
    $progress = $recent / ($recent + $oldqueuesize);
}

// if old queue is zero the use current queue
$duration = time() - $crawlstart;
$eta = floor($duration / $progress + $crawlstart);

$table = new html_table();
$table->head = array(get_string('robotstatus', 'local_linkchecker_robot'));
$table->headspan = array(2, 1);
$table->data = array(
    array(
        get_string('botuser', 'local_linkchecker_robot'),
        $boterror ? $boterror : get_string('good', 'local_linkchecker_robot')
    ),
    array(
        get_string('progress', 'local_linkchecker_robot'),
        get_string('progresseta', 'local_linkchecker_robot', array(
            'percent' => sprintf('%.2f%%', $progress * 100),
            'eta' => userdate($eta),
        )),
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
        get_string('numlinks', 'local_linkchecker_robot'),
        $numlinks
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
        "<a href=\"report.php?report=broken\">$numpageswithurlsbroken / $numurlsbroken</a>"
    ),
    array(
        get_string('oversize', 'local_linkchecker_robot'),
        "<a href=\"report.php?report=oversize\">$oversize</a>"
    ),
);

require('tabs.php');
echo $tabs;
echo html_writer::table($table);

$table = new html_table();
$table->head = array(
    get_string('crawlstart', 'local_linkchecker_robot'),
    get_string('crawlend', 'local_linkchecker_robot'),
    get_string('duration', 'local_linkchecker_robot'),
    get_string('cronticks', 'local_linkchecker_robot'),
    get_string('numurls', 'local_linkchecker_robot'),
    get_string('numlinks', 'local_linkchecker_robot'),
    get_string('broken', 'local_linkchecker_robot'),
    get_string('oversize', 'local_linkchecker_robot'),
);
$table->data = array();
$history = $DB->get_records('linkchecker_history', array(), 'startcrawl DESC', '*', 0, 5);
foreach ($history as $record) {
    if ($record->endcrawl) {
        $delta = $record->endcrawl - $record->startcrawl;
    } else {
        $delta = time() - $record->startcrawl;
    } 
    $duration = sprintf('%02d:%02d:%02d', $delta / 60 / 60, $delta / 60 % 60, $delta % 60);
    $table->data[] = array(
        userdate($record->startcrawl, '%h %e,&nbsp;%H:%M:%S'),
        $record->endcrawl ? userdate($record->endcrawl, '%h %e,&nbsp;%H:%M:%S') : '-',
        $duration,
        $record->cronticks,
        $record->urls,
        $record->links,
        $record->broken,
        $record->oversize,
    );
}

echo html_writer::table($table);

echo $OUTPUT->footer();
