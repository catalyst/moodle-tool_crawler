<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * @package   tool_crawler
 * @copyright 2019 Nicolas Roeser, Ulm University <nicolas.roeser@uni-ulm.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The crawler has determined the exact size of the requested resource. This value does not indicate that the crawler has indeed
 * fully downloaded the resource; it may also rely on header fields that it has seen.
 *
 * Value for the `filesizestatus` column in the database table.
 */
define('TOOL_CRAWLER_FILESIZE_EXACT', 0);

/**
 * The crawler has detected that the requested resource is _at least_ the size stored in the `filesize` column in the database
 * table. This value is normally used when the download has been aborted, but some data has already been received.
 *
 * Value for the `filesizestatus` column in the database table.
 */
define('TOOL_CRAWLER_FILESIZE_ATLEAST', 1);

/**
 * The crawler has tried to, but has been unable to detect the size of the resource, and can not give a minimum size. This can
 * happen if a redirection is followed, but the final header is not processed at all: for example, if an overlong header is
 * encountered and the crawler has decided to abort the download, then the final header will not be seen.
 *
 * This value is to be distinguished from NULL, which indicates that the crawler has made _no attempt_ to find out about the size of
 * the requested resource. So NULL says that there is no information about the meaning of the value in the `filesize` column in the
 * database table (if that is non-NULL at all).
 *
 * Value for the `filesizestatus` column in the database table.
 */
define('TOOL_CRAWLER_FILESIZE_UNKNOWN', 2);
