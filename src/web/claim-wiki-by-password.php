<?php ; // -*- mode: java; c-basic-offset: 4; tab-width: 4; indent-tabs-mode: nil; -*-

if ($_SERVER["REQUEST_METHOD"] == "GET" && array_key_exists ("w", $_GET)) {
    printf ("<h2>Success</h2><p>Transferred %d wiki%s, %d group membership%s, and %d wiki access rule%s to %s.</p><p>Return to <a href=\"./\">wiki index</a>.</p>",
	    $_GET["w"], $_GET["w"]==1 ? "" : "s",
	    $_GET["g"], $_GET["g"]==1 ? "" : "s",
	    $_GET["a"], $_GET["a"]==1 ? "" : "s",
	    $_SERVER["REMOTE_USER"]);
    exit;
}

$db = new SQLite3 (getenv("WIKIFARM_DB_FILE"));
$userid = $_SERVER["REMOTE_USER"];
$q_userid = SQLite3::escapeString ($userid);
$q_old_username = SQLite3::escapeString ($_POST["username"]);
$provided_password = ereg_replace ("\n", "", $_POST["password"]);

$q = $db->query ("select cryptpw from users where userid='$q_old_username'");
$row = $q->fetchArray ();
$cryptpw = $row[0];
putenv ("PW=$provided_password");
putenv ("SALT=$cryptpw");
$check = `perl -e 'use Apache::Htpasswd; \$h = new Apache::Htpasswd("/dev/null"); print \$h->CryptPasswd (\$ENV{PW}, \$ENV{SALT})'`;
if (!$userid ||
    strlen($cryptpw) < 6 ||
    trim($check) != trim($cryptpw)) {
    exit ("<h2>Authentication failed</h2><p>Username or password incorrect.</p>");
}
else {
    $db->exec ("update wikis set userid='$q_userid' where userid='$q_old_username'");
    $wikis_claimed = $db->changes();

    $db->exec ("update or ignore usergroups set userid='$q_userid' where userid='$q_old_username'");
    $groups_claimed = $db->changes();

    $db->exec ("update or ignore wikipermission set userid_or_groupname='$q_userid' where userid_or_groupname='$q_old_username'");
    $access_claimed = $db->changes();

    $db->exec ("INSERT OR IGNORE INTO users (userid, realname, email)
    	      SELECT '$q_userid',

	      CASE WHEN realname IS NULL AND userid NOT LIKE '%@%' THEN userid
	      ELSE realname END,

	      CASE WHEN email IS NULL AND userid LIKE '%@%' THEN userid
	      ELSE email END

	      FROM users WHERE userid='$q_old_username'");

    header ("Location: claim-wiki-by-password.php?w=$wikis_claimed&g=$groups_claimed&a=$access_claimed");
    exit;
}