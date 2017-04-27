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
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('tool_crawler_status');

echo $OUTPUT->header();

$action         = optional_param('action', '', PARAM_ALPHANUMEXT);

$robot = new \tool_crawler\robot\crawler();
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
    $progress = $recent / ($recent + max($oldqueuesize, $queuesize));
}

// If old queue is zero the use current queue.
$duration = time() - $crawlstart;
$eta = floor($duration / $progress + $crawlstart);

$robot = $DB->get_record('user', array('username' => $config->botusername));

$table = new html_table();
$table->head = array(get_string('robotstatus', 'tool_crawler'));
$table->headspan = array(2, 1);
$table->data = array(
    array(
        get_string('botuser', 'tool_crawler'),
        $robot->username
        . ' | ' . ($boterror ? $boterror : get_string('good', 'tool_crawler'))
        . ' | ' . html_writer::link(new moodle_url('/user/editadvanced.php',
                array('id' => $robot->id, 'courseid' => 1)), get_string('useraccount', 'tool_crawler'))
        . ' | ' . html_writer::link(new moodle_url('/admin/roles/usersroles.php',
                array('userid' => $robot->id, 'courseid' => 1)), get_string('roles')),
    ),
    array(
        get_string('progress', 'tool_crawler'),
        get_string('progresseta', 'tool_crawler', array(
            'percent' => sprintf('%.2f%%', $progress * 100),
            'eta' => userdate($eta),
        ))
        . ' | ' . html_writer::link(new moodle_url('/admin/tool/crawler/resetprogress.php'),
            get_string('resetprogress', 'tool_crawler'))
    ),
    array(
        get_string('curcrawlstart', 'tool_crawler'),
        $crawlstart ? userdate( $crawlstart) : get_string('neverrun', 'tool_crawler')
    ),
    array(
        get_string('lastcrawlend', 'tool_crawler'),
        $crawlend ? userdate( $crawlend) : get_string('neverfinished', 'tool_crawler')
    ),
    array(
        get_string('lastcrawlproc', 'tool_crawler'),
        $crawltick ? userdate( $crawltick) : '-'
    ),
    array(
        get_string('lastqueuesize', 'tool_crawler'),
        number_format($oldqueuesize)
    ),
    array(
        get_string('numlinks', 'tool_crawler'),
        number_format($numlinks)
    ),
    array(
        get_string('queued', 'tool_crawler'),
        "<a href=\"report.php?report=queued\">" . number_format($queuesize) . "</a>"
    ),
    array(
        get_string('recent', 'tool_crawler'),
        "<a href=\"report.php?report=recent\">" . number_format($recent) . "</a>"
    ),
    array(
        get_string('broken', 'tool_crawler'),
        "<a href=\"report.php?report=broken\">" . number_format($numpageswithurlsbroken)
                . " / " . number_format($numurlsbroken) . "</a>"
    ),
    array(
        get_string('oversize', 'tool_crawler'),
        "<a href=\"report.php?report=oversize\">" . number_format($oversize) . "</a>"
    ),
);

$report = 'index';
require('tabs.php');
echo $tabs;
echo html_writer::table($table);

$table = new html_table();
$table->head = array(
    get_string('crawlstart', 'tool_crawler'),
    get_string('crawlend', 'tool_crawler'),
    get_string('duration', 'tool_crawler'),
    get_string('cronticks', 'tool_crawler'),
    get_string('numurls', 'tool_crawler'),
    get_string('numlinks', 'tool_crawler'),
    get_string('broken', 'tool_crawler'),
    get_string('oversize', 'tool_crawler'),
);
$table->data = array();
$table->colclasses = array('', '', '', 'rightalign', 'rightalign', 'rightalign', 'rightalign', 'rightalign');
$history = $DB->get_records('tool_crawler_history', array(), 'startcrawl DESC', '*', 0, 5);
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
        number_format($record->cronticks),
        number_format($record->urls),
        number_format($record->links),
        number_format($record->broken),
        number_format($record->oversize),
    );
}

echo html_writer::table($table);

echo $OUTPUT->footer();
