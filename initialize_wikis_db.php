<?php
     ;
$home = getenv("INSTALLDIR");
$db = new SQLite3 ("$home/etc/wikis.db");
if (!$db->exec ('CREATE TABLE IF NOT EXISTS wikis (
 id integer primary key autoincrement,
 wikiname varchar(32),
 userid varchar(255),
 realname varchar(128),
 unique (wikiname)
 )'))
    die ($db->lastErrorMsg());

if (!$db->exec ('CREATE TABLE IF NOT EXISTS users (
 userid varchar(255) primary key,
 cryptpw varchar(128),
 approved integer not null default 0,
 admin integer not null default 0
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

