<?php
     ;
$home = getenv("INSTALLDIR");
$db = new SQLite3 ("$home/etc/wikis.db");
if (!$db->exec ('CREATE TABLE wikis (
 id integer primary key autoincrement,
 wikiname varchar(32),
 userid varchar(255),
 realname varchar(128),
 unique (wikiname)
 )'))
    die ($db->lastErrorMsg());

if (!$db->exec ('CREATE TABLE users (
 userid varchar(255) primary key,
 cryptpw varchar(128),
 approved integer not null default 0,
 admin integer not null default 0
 )'))
    die ($db->lastErrorMsg());

if (!$db->exec ('CREATE TABLE usergroups (
 userid varchar(255),
 groupname varchar(255)
 )'))
    die ($db->lastErrorMsg());

if (!$db->exec ('CREATE TABLE wikipermission (
 wikiid varchar(255),
 userid_or_groupname varchar(255),
 readonly integer default 1
 )'))
    die ($db->lastErrorMsg());


print "Importing wiki.list...";
$fh = fopen ("$home/etc/wiki.list", "r");
while ($row = fgets ($fh)) {
    $row = explode ("\t", $row);
    foreach ($row as &$x) {
	$x = SQLite3::escapeString ($x);
    }
    if (!$db->exec ("insert into wikis (id, wikiname, userid) values ('$row[0]', '$row[1]', '$row[2]')"))
	die ($db->lastErrorMsg());
    print ".";
}
fclose ($fh);
print "\n";


print "Importing index.html...";
$fh = fopen ("$home/wikis/index.html", "r");
while ($row = fgets ($fh)) {
    if (!ereg ("<a href=\"(.*)/\">(.*)</a>", $row, $regs))
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
$fh = fopen ("$home/etc/.htpasswd", "r");
while ($row = fgets ($fh)) {
    $row = explode (":", $row);
    foreach ($row as &$x)
	$x = SQLite3::escapeString ($x);
    $db->exec ("insert into users (userid, cryptpw) values ('$row[0]', '$row[1]')");
    print ".";
}
fclose ($fh);
print "\n";


print "Importing groups from .htgroup...";
$fh = fopen ("$home/etc/.htgroup", "r");
while ($row = fgets ($fh)) {
    $row = explode (":", $row);
    $group = SQLite3::escapeString ($row[0]);
    foreach (explode (" ", $row[1]) as $userid) {
	if ($userid == "") continue;
	$userid = SQLite3::escapeString ($userid);
	$db->exec ("insert into usergroups (userid, groupname) values ('$userid', '$group')");
	print ".";
    }
}
fclose ($fh);
print "\n";



print "Faking wiki permissions...";
$db->exec ("insert into wikipermissions (wikiid, userid_or_groupname) select id, 'labmembers' from wikis");
$db->exec ("delete from wikipermissions where wikiid in (64,65)");
$db->exec ("insert wikipermissions (wikiid, userid_or_groupname values (64,'joshilab'),(65,'joshilab')");
print "\n";

