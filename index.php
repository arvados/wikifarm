<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

require_once('WikifarmDriver.php');
require_once('WikifarmPageMachine.php');

// $userid = $_SERVER["REMOTE_USER"];
// $q_userid = SQLite3::escapeString ($userid);

$wf = new WikifarmPageMachine();

if (isset($_GET["modauthopenid_referrer"]) && $wf->isActivated()) {   //TODO is "activated" still really what we're testing?
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
<link rel="stylesheet" type="text/css" href="style.css" />
<link type="text/css" href="css/smoothness/jquery-ui-1.8.4.custom.css" rel="Stylesheet" />
<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.4.custom.min.js"></script>
<? /* <script type="text/javascript" src="js/wikifarm-ui.js" language="JavaScript"></script> */ ?>
<script language="JavaScript">

	$(function() {
		$("#tabs").tabs();
	});

</script>
</head>
<body>

[ logo or something ]
<br>
<br>
<table width=100%><tr><td>&nbsp;</td><td align=right>Need help? Check out our <a href="docs/Wiki_Tutorial">Wiki Tutorial</a></td>
<tr><td><font size=-2> Logged in as <?=$_SERVER["REMOTE_USER"]?></font></td><td align=right><font size=-2><a href="logout.php">Log out</a></font></td></table>

<?php  // Begin tabs and stuff
/*
	echo "<div style=\"display: block;padding:10px;background-color:#dae6fa\" id=\"tabdiv\">\n<ul id=\"tabmenu\" >\n";
	foreach ($tabTitles as $tab => $title) {
		echo "<li><a class=\"\" id=\"$tab\">$title</a></li>\n";
	}
	echo "</ul>\n<div id=\"content\"></div>\n</div>";
*/

// jq ui instead:

	echo "<div id=\"tabs\">\n\t<ul>";
	foreach ($tabTitles as $tab => $title) {
		echo "\n\t\t<li><a href=\"?tab=$tab\">$title</a></li>";
	}
	echo "\n\t</ul>\n</div>";


// echo $wf->pageAllWikis();
?>



<br>
<br>

</body>
</html>
