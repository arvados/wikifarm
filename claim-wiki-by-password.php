<?;

if ($_SERVER["REQUEST_METHOD"] == "GET" && array_key_exists ("w", $_GET)) {
    printf ("<h2>Success</h2><p>Transferred %d wiki%s and %d group membership%s to %s.</p><p>Return to <a href=\"./\">wiki index</a>.</p>",
	    $_GET["w"], $_GET["w"]==1 ? "" : "s",
	    $_GET["g"], $_GET["g"]==1 ? "" : "s",
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
    $db->exec ("update usergroups set userid='$q_userid' where userid='$q_old_username'");
    $groups_claimed = $db->changes();
    header ("Location: claim-wiki-by-password.php?w=$wikis_claimed&g=$groups_claimed");
    exit;
}