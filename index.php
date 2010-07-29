<?php
     ;
$db = new SQLite3 (getenv("WIKIFARM_DB_FILE"));
$userid = $_SERVER["REMOTE_USER"];

$q_userid = SQLite3::escapeString ($userid);

if (isset($_GET["modauthopenid_referrer"]))
{
    $result = $db->query ("SELECT count(*) from usergroups where userid='$q_userid'");
    $groupsrow = $result->fetchArray();
    $result = $db->query ("SELECT count(*) from wikipermission where userid_or_groupname='$q_userid'");
    $wikirow = $result->fetchArray();
    $result = $db->query ("SELECT count(*) from wikis where userid='$q_userid'");
    $ownrow = $result->fetchArray();
    if ($groupsrow[0] + $wikirow[0] + $ownrow[0] > 0) {
	header ("location: ".$_GET["modauthopenid_referrer"]);
	exit;
    }
}

?><html>
<head>
<link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<br>
<br>

OpenID
<ul>
<li>Logged in as <?=$_SERVER["REMOTE_USER"]?></li>
<li><a href="logout.php">Log out</a>
</ul>

Links
<ul>
<li><a href="smd/Wiki_Tutorial">Wiki Tutorial</a></li>
<li><a href="table.php">Excel -> Wiki Table converter</a></li>
</ul>

Wikis
<ol>
<?php
    ;

$adminmode = 0;
$q = $db->query ("SELECT 1 FROM usergroups WHERE usergroups.userid = '$q_userid' AND groupname = 'ADMIN'");
if (($row = $q->fetchArray()) && $row[0] == 1) {
    $adminmode = 1;
}

$result = $db->query ("SELECT wikis.id, wikis.wikiname, wikis.realname, min(wikipermission.readonly), wikis.userid
 FROM wikis
 LEFT JOIN usergroups ON usergroups.userid = '$q_userid'
 LEFT JOIN wikipermission ON wikipermission.wikiid = wikis.id
  AND (wikipermission.userid_or_groupname = '$q_userid'
       OR wikipermission.userid_or_groupname = usergroups.groupname)
 WHERE wikis.userid = '$q_userid'
  OR wikipermission.wikiid IS NOT NULL
  OR usergroups.groupname = 'ADMIN'
 GROUP BY wikis.id
 ORDER BY wikis.id");
while ($row = $result->fetchArray()) {
    if ($row[2] == "")
	$row[2] = $row[1];
    print "<li value=\"$row[0]\"><a href=\"$row[1]/\">$row[2]</a>\n";
    if ($adminmode)
	print " owned by $row[4]\n";
}
?>
</ol>

Claim your old (pre-OpenID) wiki
<blockquote>
To claim your wiki and lab affiliations, enter the username and password you used to use with browser-based authentication (<i>Authentication required -- a username and password are being requested by https://pub.med.harvard.edu. The site says: "Lab Notebook"</i>)
<blockquote>
<form action="claim-wiki-by-password.php" method="post">
Username: <input type=text name=username size=16>
<br />Password: <input type=password name=password size=16>
<br /><input type=submit value="Give me my wiki">
</form>
</blockquote>
After you do this, your wiki and group memberships will be attached to the OpenID you are currently logged in as (<?=getenv("REMOTE_USER")?>).
</blockquote>


</body>
</html>
