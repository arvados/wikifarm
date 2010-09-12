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

// Perhaps this is an ajax request.
if (preg_match ('{application/json}', $_SERVER["HTTP_ACCEPT"]) ||
    array_key_exists ("ga_action", $_POST)) {
	ini_set ('display_errors', false);
	header ("Content-type: application/json");
	$response = array_merge(array ("request" => $_POST),
				$wf->dispatch_ajax(&$_POST));
	print json_encode($response);
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
//			'getaccess'=>'Get Access',
			'tools'=>'Tools',
			'users'=>'User List',
			'debug'=>'Debug',
			'settings'=>'Wikifarm Settings' );

if (!$wf->isAdmin()) {
	unset ( $tabTitles['debug'] );
	unset ( $tabTitles['settings'] );
}

if (!$wf->getUserRealname() || !$wf->getUserEmail()) {
	$tabActive = "myaccount";
	$tabTitles = array ($tabActive => $tabTitles[$tabActive]);
}
else if ($wf->isActivated())
	$tabActive = "wikis";
else {
	$tabActive = "groups";
	$tabTitles = array ($tabActive => $tabTitles[$tabActive],
			    'myaccount' => $tabTitles['myaccount']);
}
if (0 == count($wf->getAllRequests()))
	unset ($tabTitles['requests']);

if (isset($_GET["tabActive"]) &&
    isset ($tabTitles[$_GET["tabActive"]]))
	$tabActive = $_GET["tabActive"];

$tabActiveId = 0;
if (isset ($_GET["tabActive"]))
    foreach ($tabTitles as $tab => $title)
	if ($_GET["tabActive"] == $tab)
	    break;
	else
	    ++$tabActiveId;

?><html>
<head>
<title>WikiFarm Dashboard</title>
<link type="text/css" href="js/DataTables/css/demo_page.css" rel="Stylesheet" />
<link type="text/css" href="js/DataTables/css/demo_table.css" rel="Stylesheet" />
<link rel="stylesheet" type="text/css" href="style.css" />
<link type="text/css" href="css/smoothness/jquery-ui-1.8.4.custom.css" rel="Stylesheet" />
<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.4.custom.min.js"></script>
<script type="text/javascript" src="js/wikifarm-ui.js" language="JavaScript"></script>
<script type="text/javascript" src="js/DataTables/js/jquery.dataTables.js" language="JavaScript"></script>
<script language="JavaScript">
	$(function() {
		$("#tabs").tabs({selected: <?=$tabActiveId?>});
		$(".needhelp").css('font-size', '.8em');
		$("#logoutbutton").button().removeClass('ui-corner-all').css('padding', '0px');
	});
</script>
</head>
<body>

[ logo or something ]
<br>
<br>
<table width=100%><tr><td>&nbsp;</td><td class="needhelp" align=right>Need help? Check out our <a href="docs/Wiki_Tutorial">Wiki Tutorial</a></td>
<tr><td><font size=-2> Logged in as <?=$_SERVER["REMOTE_USER"]?></font></td><td align=right><font size=-2><a href="logout.php" id="logoutbutton" class="ui-corner-tl ui-corner-tr">Log out</a></font></td></table>

<?php  // Begin tabs and stuff

	echo "<div id=\"tabs\">\n\t<ul>";
	foreach ($tabTitles as $tab => $title) {
		echo "\n\t\t<li><a tab_id=\"$tab\" href=\"?tab=$tab\" title=\"$title\">$title</a></li>";
	}
	echo "\n\t</ul>\n</div>";

?>


<br>
<br>

</body>
</html>
