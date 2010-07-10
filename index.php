<html>
<head>
<link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<br>
<br>

Links
<ul>
<li><a href="smd/Wiki_Tutorial">Wiki Tutorial</a>
<li><a href="table.php">Excel -> Wiki Table converter</a>
</ul>

Wikis
<ol>
<?php
     ;
$db = new SQLite3 (getenv("WIKIFARM_DB_FILE"));
$userid = getenv("REMOTE_USER");

$q_userid = SQLite3::escapeString ($userid);

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
To claim your wiki, enter the username and password you used to use with browser-based authentication (<i>Authentication required -- a username and password are being requested by https://pub.med.harvard.edu. The site says: "Lab Notebook"</i>)
<blockquote>
<form action="claim-wiki-by-password.php" method="post">
Username: <input type=text name=username size=16>
<br />Password: <input type=password name=password size=16>
<br /><input type=submit value="Give me my wiki">
</form>
</blockquote>
After you do this, your wiki will belong to the OpenID you are currently logged in as (<?=getenv("REMOTE_USER")?>).
</blockquote>


</body>
</html>
