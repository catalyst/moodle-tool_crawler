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

$rows = [
    new tabobject('settings', '/admin/settings.php?section=tool_crawler',       get_string('settings', 'tool_crawler')),
    new tabobject('index',    '/admin/tool/crawler/index.php',                  get_string('status',   'tool_crawler')),
    new tabobject('queued',   '/admin/tool/crawler/report.php?report=queued',   get_string('queued',   'tool_crawler')),
    new tabobject('recent',   '/admin/tool/crawler/report.php?report=recent',   get_string('recent',   'tool_crawler')),
    new tabobject('broken',   '/admin/tool/crawler/report.php?report=broken',   get_string('broken',   'tool_crawler')),
    new tabobject('oversize', '/admin/tool/crawler/report.php?report=oversize', get_string('oversize', 'tool_crawler')),
];

$section = optional_param('section', '', PARAM_RAW);
if ($section == 'tool_crawler') {
    $report = 'settings';
}
if (empty($report)) {
    $report = '';
}
$tabs = $OUTPUT->tabtree($rows, $report);

