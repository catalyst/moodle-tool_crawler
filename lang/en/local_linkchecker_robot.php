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
 * Defines the lang strings of linkchecker_robot local plugin
 *
 * @package    local_linkchecker_robot
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Link checker';

$string['status'] = 'Link checker status';

$string['checker_help'] = '<a href="{$a->url}">Robot status page</a>';
$string['seedurl'] = 'Seed URL';
$string['seedurldesc'] = 'Where the crawler will start';
$string['excludeexturl'] = 'Exclude external URL\'s';
$string['excludeexturldesc'] = 'One url regex per line, each is matched against the full url';
$string['excludemdlurl'] = 'Exclude moodle URL\'s';
$string['excludemdlurldesc'] = 'One url regex per line, each is matched excluding the wwwroot';
$string['excludemdldom'] = 'Exclude moodle DOM\'s';
$string['excludemdldomdesc'] = 'One xpath expression per line, these parts of the DOM will be removed before links are extracted';
$string['maxtime'] = 'Max execution time';
$string['maxtimedesc'] = 'The timeout for each crawl request';
$string['maxcrontime'] = 'Max execution time in seconds';
$string['maxcrontimedesc'] = 'A soft limit to how much time the robot will spend on each cron run across multiple crawls, NOT each url. In seconds';


