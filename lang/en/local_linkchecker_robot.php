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

$string['pluginname'] = 'Link checker robot';

$string['status'] = 'Robot status';
$string['broken'] = 'Broken URLs';

$string['checker_help'] = '<a href="{$a->url}">Robot status page</a>';
$string['seedurl'] = 'Seed URL';
$string['seedurldesc'] = 'Where the crawler will start';
$string['botusername'] = 'Bot username';
$string['botusernamedesc'] = 'The username of the moodle user to crawl as';
$string['botpassword'] = 'Bot password';
$string['botpassworddesc'] = 'The password of the moodle user to crawl as. This user should have site wide view permission, but very limited edit permissions, and be configured to use basic auth.';
$string['useragent'] = 'Bot user agent string';
$string['useragentdesc'] = 'The User agent string it use in the http headers + the version of this plugin';
$string['excludeexturl'] = 'Exclude external URL\'s';
$string['excludeexturldesc'] = 'One url regex per line, each is matched against the full url';
$string['excludemdlurl'] = 'Exclude moodle URL\'s';
$string['excludemdlurldesc'] = 'One url regex per line, each is matched excluding the wwwroot';
$string['excludemdldom'] = 'Exclude moodle DOM\'s';
$string['excludemdldomdesc'] = 'One css / xpath expression per line, these parts of the DOM will be removed before links are extracted';
$string['maxtime'] = 'Max execution time';
$string['maxtimedesc'] = 'The timeout for each crawl request';
$string['maxcrontime'] = 'Cron run limit';
$string['maxcrontimedesc'] = 'The crawler will keep crawling until this limit is hit on each cron tick. In seconds';


