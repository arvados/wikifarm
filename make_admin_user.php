#!/usr/bin/php
<?php
    ;
$etc = dirname(__FILE__);
$dbdir = `. $etc/env; echo -n \$DB`;
$db = new SQLite3 ("$dbdir/wikis.db");
$userid = $argv[1];
if (!$userid) {
    die ("usage: $argv[0] admin_user_openid\n");
}
$q_userid = SQLite3::escapeString ($userid);
$db->exec ("INSERT OR IGNORE INTO usergroups (userid, groupname) VALUES ('$q_userid', 'ADMIN')");
