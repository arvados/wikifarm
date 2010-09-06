<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

require_once('WikifarmDriver.php');
require_once('WikifarmPageMachine.php');

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
			'requests'=>'Requests',
			'wikis'=>'All Wikis',
			'mywikis'=>'My Wikis',
			'groups'=>'Groups',
			'myaccount'=>'My Account',
			'getaccess'=>'Get Access',
			'createwiki'=>'Create a Wiki',
			'tools'=>'Tools',
			'debug'=>'Debug',
			'settings'=>'Wikifarm Settings' );

if (!$wf->isAdmin()) {
	unset ( $tabTitles['settings'] );
	// unset ( $tabTitles['wikis'] );
	if (!$wf->isActivated()) {
		unset ( $tabTitles['mywikis'] );
		
	}
}
		
	


if (count($wf->getUserGroups()))
	$tabActive = "wikis";
else {
	if ($wf->getUserRealname() && $wf->getUserEmail()) {
		$tabActive = "groups";
		unset ($tabTitles['wikis']);
		unset ($tabTitles['createwiki']);
	} else {
		$tabActive = "myaccount";
		$tabTitles = array ($tabActive => $tabTitles[$tabActive]);
	}
}
if (0 == count($wf->getAllRequests()))
	unset ($tabTitles['requests']);


?><html>
<head>
<title>WikiFarm Dashboard</title>
<link rel="stylesheet" type="text/css" href="style.css" />
<link type="text/css" href="css/smoothness/jquery-ui-1.8.4.custom.css" rel="Stylesheet" />
<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.4.custom.min.js"></script>
<? /* <script type="text/javascript" src="js/wikifarm-ui.js" language="JavaScript"></script> */ ?>
<style type="text/css">
		.button { padding: .5em 1em; text-decoration: none; }
</style>
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
<tr><td><font size=-2> Logged in as <?=$_SERVER["REMOTE_USER"]?></font></td><td align=right><font size=-2><a href="logout.php" class="button ui-state-default ui-corner-all">Log out</a></font></td></table>

<?php  // Begin tabs and stuff

	echo "<div id=\"tabs\">\n\t<ul>";
	foreach ($tabTitles as $tab => $title) {
		echo "\n\t\t<li><a href=\"?tab=$tab\">$title</a></li>";
	}
	echo "\n\t</ul>\n</div>";

?>


<br>
<br>

</body>
</html>
