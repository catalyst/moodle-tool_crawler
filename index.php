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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('local_linkchecker_robot_status');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('status', 'local_linkchecker_robot'));

$action         = optional_param('action', '', PARAM_ALPHANUMEXT);
$config         = get_config('local_linkchecker_robot');

$robot = new \local_linkchecker_robot\robot\crawler();

if ($action == 'makebot') {

    $botuser = $robot->auto_create_bot();

} else {

}

$crawlstart     = $robot->get_crawlstart();
$crawlend       = $robot->get_last_crawlend();
$crawltick      = $robot->get_last_crawltick();
$boterror       = $robot->is_bot_valid();
$queuesize      = $robot->get_queue_size();
$processed      = $robot->get_processed();
$oldqueuesize   = $robot->get_old_queue_size();

?>

<table>
<tr><td>Bot user                        <td><?php echo $boterror   ? $boterror : 'Good' ?>
<tr><td>Current crawl started at        <td><?php echo $crawlstart ? userdate( $crawlstart) : 'Never run'      ?>
<tr><td>Last crawl ended at             <td><?php echo $crawlend   ? userdate( $crawlend)   : 'Never finished' ?>
<tr><td>Last crawl process              <td><?php echo $crawltick  ? userdate( $crawltick)  : '-'              ?>
<tr><td>How many URL's in the queue     <td><?php echo $queuesize    ?>
<tr><td>How many URL's processed so far <td><?php echo $processed    ?>
<tr><td>Last queue size                 <td><?php echo $oldqueuesize ?>
</table>


<h3>Summary</h3>

<p>Broken URL's:
<?php echo $DB->get_field_sql("SELECT COUNT(*)
                                 FROM {linkchecker_url}
                                WHERE httpcode != ?", array('200') ); ?>
 <a href="report.php?report=broken">See all</a>

<!--
<p>crawl as
<p> link to course level reports
<p> link to global reports
<p>broken urls:
<p>Slow urls
<p>high linked urls
-->

<?php
echo $OUTPUT->footer();

