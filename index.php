<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

require_once('WikifarmDriver.php');
require_once('WikifarmPageMachine.php');

$db = new SQLite3 (getenv("WIKIFARM_DB_FILE"));
/*
PHP Fatal error:  Uncaught exception 'Exception' with message 'Unable to expand filepath' in /data/home/wikifarm/wikis/index2.php:9
Stack trace:
#0 /data/home/wikifarm/wikis/index2.php(9): SQLite3->__construct('')
#1 {main}
  thrown in /data/home/wikifarm/wikis/index2.php on line 9
*/

$userid = $_SERVER["REMOTE_USER"];

$q_userid = SQLite3::escapeString ($userid);
$wf = new WikifarmPageMachine(&$db);

if (isset($_GET["modauthopenid_referrer"]) && $wf->isAuthenticated()) {
	header ("location: ".$_GET["modauthopenid_referrer"]);
	exit;
}

// Would sir enjoy some tab content?
if (isset($_GET['tab'])) {
	echo $wf->tabGet($_GET['tab']);
	exit;
}

// what tabs should we see?
$tabTitles = array(
			'wikis'=>'Wikis',
			'getaccess'=>'Get Access',
			'giveaccess'=>'Give Access',
			'createwiki'=>'Create a Wiki',
			'tools'=>'Tools',
			'schema'=>'Schema',
			'settings'=>'Wikifarm Settings' );

unset ( $tabTitles['settings'] ); //... etc
$tabActive = ($wf->hasWikis() ? "wikis" : "getaccess");


?><html>
<head>
<title>WikiFarm Dashboard</title>
<link rel="stylesheet" type="text/css" href="style.css">
<link type="text/css" href="css/themename/jquery-ui-1.8.custom.css" rel="Stylesheet" />	
<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.custom.min.js"></script>
<script type="text/javascript" src="js/wikifarm-ui.js" language="JavaScript"></script>
<script language="JavaScript"> 
	initialTab = '<?=$tabActive?>';
</script>
</head>
<body>

[ logo or something ]
<br>
<br>
<table width=100%><tr><td>&nbsp;</td><td align=right>Need help? Check out our <a href="docs/Wiki_Tutorial">Wiki Tutorial</a></font>
<tr><td><font size=-2> Logged in as <?=$_SERVER["REMOTE_USER"]?></font></td><td align=right><font size=-2><a href="logout.php">Log out</a></font></td></td></table>

<?php  // Begin tabs and stuff
	echo "<div style=\"display: block;padding:10px;background-color:#dae6fa\" id=\"tabdiv\">\n<ul id=\"tabmenu\" >\n";
	foreach ($tabTitles as $tab => $title) {
		echo "<li><a class=\"\" id=\"$tab\">$title</a></li>\n";
	}
	echo "</ul>\n<div id=\"content\"></div>\n</div>";
?>

<br>
<br>



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
