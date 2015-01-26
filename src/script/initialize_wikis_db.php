<?php ; // -*- mode: java; c-basic-offset: 4; tab-width: 4; indent-tabs-mode: nil; -*-

// Copyright 2011 President and Fellows of Harvard College
//
// Author:
// Tom Clegg <tom@clinicalfuture.com>
//
// This file is part of wikifarm.
//
// Wikifarm is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License version 2 as
// published by the Free Software Foundation.
//
// Wikifarm is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with wikifarm.  If not, see <http://www.gnu.org/licenses/>.

$dbdir = getenv("DB");
$etcdir = getenv("ETC");
$db = new SQLite3 ("$dbdir/wikis.db");

$db->exec ('CREATE TABLE sitepref (
 prefid varchar(64),
 value varchar(255),
 description varchar(255))');
if (@$db->exec ('CREATE UNIQUE INDEX sp ON sitepref (prefid)')) {
    $db->exec ('INSERT INTO sitepref (prefid, value) VALUES ("allow_mw_native_login", "0")');
    $db->exec ('INSERT INTO sitepref (prefid, value) VALUES ("unreadable_wikis_visible", "1")');
}
$db->exec ('INSERT OR IGNORE INTO sitepref (prefid, value) VALUES ("default_wiki_quota", 5)');

@$db->exec ('ALTER TABLE usergroups ADD isadmin INTEGER DEFAULT 0');
if(@$db->exec ('ALTER TABLE pref ADD defaultvalue varchar(255)')) {
    $db->exec ('UPDATE pref SET defaultvalue=1 WHERE prefid="admin_notify_requests"');
}

if(@$db->exec ('ALTER TABLE wikis ADD isactive INTEGER DEFAULT 1')) {
    $db->exec ('UPDATE wikis SET isactive=0 WHERE userid not like "%://%"');
}

if (!@$db->exec ('CREATE TABLE userpref (
 userid varchar(255),
 prefid varchar(64),
 value varchar(255))'))
    die ($db->lastErrorMsg() . "\n");
if (!$db->exec ('CREATE UNIQUE INDEX up ON userpref (userid,prefid)'))
    die ($db->lastErrorMsg() . "\n");
if (!$db->exec ('CREATE TABLE pref (
 prefid varchar(64) primary key,
 type varchar(64),
 description varchar(255),
 defaultvalue varchar(255))'))
    die ($db->lastErrorMsg() . "\n");
$db->exec ('INSERT INTO pref (prefid,type,description) VALUES ("notify_requests", "checkbox", "Notify me by email when someone requests access to my wikis")');
$db->exec ('INSERT INTO pref (prefid,type,description,defaultvalue) VALUES ("admin_notify_requests", "checkbox", "Notify me by email about account activation and group membership requests", "1")');

if (!$db->exec ('CREATE TABLE request (
 requestid integer primary key autoincrement,
 userid varchar(255),
 wikiid integer,
 mwusername varchar(255),
 groupname varchar(255)
 )'))
    die ($db->lastErrorMsg() . "\n");
if (!$db->exec ('CREATE INDEX ru ON request (userid)'))
    die ($db->lastErrorMsg() . "\n");
if (!$db->exec ('CREATE INDEX rw ON request (wikiid)'))
    die ($db->lastErrorMsg() . "\n");
if (!$db->exec ('CREATE UNIQUE INDEX runique ON request (userid,wikiid,groupname)'))
    die ($db->lastErrorMsg() . "\n");

if (!$db->exec ('CREATE TABLE wikis (
 id integer primary key autoincrement,
 wikiname varchar(32),
 userid varchar(255),
 realname varchar(128),
 isactive INTEGER DEFAULT 1,
 unique (wikiname)
)'))
    die ($db->lastErrorMsg() . "\n");

if (!$db->exec ('CREATE TABLE users (
 userid varchar(255) primary key,
 cryptpw varchar(128),
 email varchar(255),
 realname varchar(255),
 mwusername varchar(255),
 wikiquota integer default 5
 )'))
    die ($db->lastErrorMsg() . "\n");

if (!$db->exec ('CREATE TABLE usergroups (
 userid varchar(255),
 groupname varchar(255),
 isadmin INTEGER DEFAULT 0
 )'))
    die ($db->lastErrorMsg() . "\n");

if (!$db->exec ('CREATE UNIQUE INDEX ug ON usergroups (userid,groupname)'))
    die ($db->lastErrorMsg() . "\n");

if (!$db->exec ('CREATE TABLE wikipermission (
 wikiid varchar(255),
 userid_or_groupname varchar(255),
 readonly integer default 1
 )'))
    die ($db->lastErrorMsg() . "\n");

if (!$db->exec ('CREATE UNIQUE INDEX uw ON wikipermission (wikiid,userid_or_groupname)'))
    die ($db->lastErrorMsg() . "\n");

if (!$db->exec ('CREATE TABLE autologin (
 wikiid integer,
 userid varchar(255),
 mwusername varchar(255),
 lastlogintime integer,
 sysop integer
 )'))
    die ($db->lastErrorMsg() . "\n");

if (!$db->exec ('CREATE UNIQUE INDEX wu ON autologin (wikiid,userid,mwusername)'))
    die ($db->lastErrorMsg() . "\n");

if (!file_exists ("$etcdir/legacy-wiki.list"))
    exit (0);

print "Importing wiki.list...";
$fh = fopen ("$etcdir/legacy-wiki.list", "r");
while ($row = fgets ($fh)) {
    $row = explode ("\t", trim($row, "\r\n"));
    foreach ($row as &$x) {
	$x = SQLite3::escapeString ($x);
    }
    if (!$db->exec ("insert into wikis (id, wikiname, userid) values ('$row[0]', '$row[1]', '$row[2]')"))
	die ($db->lastErrorMsg() . "\n");
    print ".";
}
fclose ($fh);
print "\n";


print "Importing index.html...";
$fh = fopen ("$etcdir/legacy-index.html", "r");
while ($row = fgets ($fh)) {
    if (!ereg ("<a href=\"(.*)/\">(.*)</a>", trim($row, "\r\n"), $regs))
	continue;
    foreach ($regs as &$x) {
	$x = SQLite3::escapeString ($x);
    }
    $db->exec ("update wikis set realname='$regs[2]' where wikiname='$regs[1]'");
    print ".";
}
fclose ($fh);
print "\n";


print "Importing users from .htpasswd...";
$fh = fopen ("$etcdir/legacy-htpasswd", "r");
while ($row = fgets ($fh)) {
    $row = explode (":", trim($row, "\r\n"));
    foreach ($row as &$x)
	$x = SQLite3::escapeString ($x);
    $db->exec ("insert into users (userid, cryptpw) values ('$row[0]', '$row[1]')");
    print ".";
}
fclose ($fh);
print "\n";


print "Importing groups from .htgroup...";
$fh = fopen ("$etcdir/legacy-htgroup", "r");
while ($row = fgets ($fh)) {
    $row = explode (":", trim($row, "\r\n"));
    $group = SQLite3::escapeString ($row[0]);
    foreach (explode (" ", trim($row[1])) as $userid) {
	if ($userid == "") continue;
	$userid = SQLite3::escapeString ($userid);
	$db->exec ("insert or ignore into usergroups (userid, groupname) values ('$userid', '$group')");
	print ".";
    }
}
fclose ($fh);
print "\n";
