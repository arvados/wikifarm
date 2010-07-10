<?php
     ;
$home = getenv("INSTALLDIR");
$db = new SQLite3 ("$home/db/wikis.db");
$userid = $argv[1];
if (!$userid) {
    die ("usage: $argv[0] admin_user_openid\n");
}
$q_userid = SQLite3::escapeString ($userid);
$db->exec ("INSERT OR IGNORE INTO usergroups (userid, groupname) VALUES ('$q_userid', 'ADMIN')");
