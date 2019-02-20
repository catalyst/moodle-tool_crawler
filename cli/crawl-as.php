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
 * tool_crawler cli
 *
 * The key different between this and 'crawl-as' is that this one
 * processes the node, ie extracts links and marks them for further
 * crawling, sets the response codes etc.
 *
 * Use this one to force update a particular URL
 *
 * @package    tool_crawler
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot .'/admin/tool/crawler/lib.php');

list($options, $unrecognized) = cli_get_params(array(
    'help'      => false,
    'url'   => null,
),
array(
    'h' => 'help',
    'u' => 'url',
));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help = "Crawl a URL as the robot and parse it.

Useful for when a page has been corrected and you want to instantly reflect this.

Options:
-h, --help      Print out this help
-u, --url       URL to crawl and process

Example:
\$sudo -u www-data php crawl-as.php --url=http://ford.com/
";

if ($options['help']) {
    echo $help;
    die();
}




$robot = new \tool_crawler\robot\crawler();

$error = $robot->is_bot_valid();
if ($error) {
    print "Error: $error";
    exit;
}

if (!$options['url']) {
    echo $help;
    die();
}

$url    = $options['url'];
$node   = $robot->mark_for_crawl($url, $url);
$res    = $robot->scrape($url);
$robot->crawl($node, 2);

