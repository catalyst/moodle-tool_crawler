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
echo $OUTPUT->heading(get_string('status', 'local_linkchecker_robot'));

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
?>

<table>
    <tr>
        <td><?php echo get_string('botuser', 'local_linkchecker_robot'); ?></td>
        <td><?php echo $boterror ? $boterror : 'Good'; ?></td>
    </tr>
    <tr>
        <td><?php echo get_string('curcrawlstart', 'local_linkchecker_robot'); ?></td>
        <td><?php echo $crawlstart ? userdate( $crawlstart) : 'Never run'; ?></td>
    </tr>
    <tr>
        <td><?php echo get_string('lastcrawlend', 'local_linkchecker_robot'); ?></td>
        <td><?php echo $crawlend ? userdate( $crawlend) : 'Never finished'; ?></td>
    </tr>
    <tr>
        <td>
            <?php echo get_string('lastcrawlproc', 'local_linkchecker_robot'); ?></td>
        <td><?php echo $crawltick ? userdate( $crawltick) : '-'; ?></td>
    </tr>
    <tr>
        <td><?php echo get_string('lastqueuesize', 'local_linkchecker_robot'); ?></td>
        <td><?php echo $oldqueuesize ?></td>
    </tr>
    <tr>
        <td><?php echo get_string('queued', 'local_linkchecker_robot'); ?></td>
        <td><a href="report.php?report=queued"><?php echo $queuesize; ?></a></td>
    </tr>
    <tr>
        <td><?php echo get_string('recent', 'local_linkchecker_robot'); ?></td>
        <td><a href="report.php?report=recent"><?php echo $recent; ?></a></td>
    </tr>
    <tr>
        <td><?php echo get_string('broken', 'local_linkchecker_robot'); ?></td>
        <td><a href="report.php?report=broken"><?php echo $broken; ?></a></td>
    </tr>
    <tr>
        <td><?php echo get_string('oversize', 'local_linkchecker_robot'); ?></td>
        <td><a href="report.php?report=oversize"><?php echo $oversize; ?></a></td>
    </tr>
</table>

<!--
<p>crawl as
<p> link to course level reports
<p> link to global reports
<p>Slow urls
<p>high linked urls
-->

<?php
echo $OUTPUT->footer();

