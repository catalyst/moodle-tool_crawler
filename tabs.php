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
 * Quick access tabs
 *
 * @package    tool_crawler
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$reports = array('queued', 'recent', 'broken', 'oversize');

$rows = [
    new tabobject('settings', '/admin/settings.php?section=tool_crawler', get_string('settings', 'tool_crawler'), false),
    new tabobject('index', '/admin/tool/crawler/index.php', get_string('status', 'tool_crawler')),
];
foreach ($reports as $rpt) {
    $rows[] = new tabobject($rpt, '/admin/tool/crawler/report.php?report=' . $rpt, get_string($rpt, 'tool_crawler'));
}

$section = optional_param('section', '', PARAM_RAW);
if ($section == 'tool_crawler') {
    $report = 'settings';
}
if (empty($report)) {
    $report = '';
}
$tabs = $OUTPUT->tabtree($rows, $report);

