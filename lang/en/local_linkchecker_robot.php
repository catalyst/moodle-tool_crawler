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
$string['settings'] = 'Settings';
$string['status'] = 'Robot status';
$string['recent'] = 'Recently crawled URL\'s';
$string['recent_header'] = '';
$string['broken_header'] = '<p>Duplicate URLs will only be searched once.</p>';
$string['oversize'] = 'Big / slow links';
$string['oversize_header'] = '<p>Big files with multiple incoming links to them will be duplicated.</p>';
$string['queued'] = 'Queued URL\'s';
$string['queued_header'] = '<p>The title and course are only known if the URL has been seen on a previous crawl.</p>';
$string['event:crawlstart'] = 'Link check crawl started';
$string['event:crawlstartdesc'] = 'Link check crawl started {$a}';
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
$string['bigfilesize'] = 'Size of Big files';
$string['bigfilesizedesc'] = 'How big a file needs to be to get flagged as oversize. In MB';
$string['botuser'] = 'Bot user';
$string['curcrawlstart'] = 'Current crawl started at';
$string['lastcrawlend'] = 'Last crawl ended at';
$string['lastcrawlproc'] = 'Last crawl process';
$string['lastqueuesize'] = 'Last queue size';
$string['configmissing'] = 'Config missing';
$string['botusermissing'] = 'Bot user missing';
$string['botcantgettestpage'] = 'Bot could not request test page';
$string['bottestpageredirected'] = 'Bot test page was redirected to';
$string['hellorobot'] = 'Hello robot:';
$string['bottestpagenotreturned'] = 'Bot test page wasn\'t returned';
$string['robotstatus'] = 'Robot status';
$string['autocreate'] = 'Auto create';
$string['good'] = 'Good';
$string['neverrun'] = 'Never run';
$string['neverfinished'] = 'Never finished';
$string['whenqueued'] = 'When queued';
$string['incourse'] = 'In course';
$string['notyetknown'] = 'Not yet known';
$string['lastcrawledtime'] = 'Last crawled time';
$string['response'] = 'Response';
$string['size'] = 'Size';
$string['url'] = 'URL';
$string['mimetype'] = 'Mime type';
$string['broken'] = 'Broken URL';
$string['frompage'] = 'From page';
$string['course'] = 'Course';
$string['missing'] = 'Missing';
$string['retry'] = 'Retry';
$string['unknown'] = 'Unknown';
$string['slowurl'] = 'Slow URL';
$string['found'] = 'Found';
$string['localplugins'] = 'Local Plugins';
$string['numberurlsfound'] = 'Found {$a->reports_number} {$a->repoprt_type}  URLs';
$string['robotstatus'] = 'Robot status';
$string['autocreate'] = 'Auto create';

