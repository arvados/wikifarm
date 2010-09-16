<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

require_once('WikifarmDriver.php');
require_once('WikifarmPageMachine.php');

$wf = new WikifarmPageMachine();

if (isset($_GET["modauthopenid_referrer"]) &&
    $wf->isActivated() &&
    !preg_match ('{modauthopenid_referrer}', $_GET["modauthopenid_referrer"]) &&
    !preg_match ('{\?tab=}', $_GET["modauthopenid_referrer"])) {
	error_log ("redirecting to ".$_GET["modauthopenid_referrer"]);
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
			'help'=>'Help');

if (!$wf->isAdmin()) {
	unset ( $tabTitles['debug'] );
	unset ( $tabTitles['settings'] );
}

if (!$wf->getUserRealname() || !$wf->getUserEmail()) {
	$tabActive = "myaccount";
	$tabTitles = array ($tabActive => $tabTitles[$tabActive],
			    'help' => $tabTitles['help']);
}
else if ($wf->isActivated())
	$tabActive = "wikis";
else {
	$tabActive = "groups";
	$tabTitles = array ($tabActive => $tabTitles[$tabActive],
			    'myaccount' => $tabTitles['myaccount'],
			    'help' => $tabTitles['help']);
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
<link type="text/css" href="js/DataTables/css/demo_page.css" rel="Stylesheet">
<link type="text/css" href="js/DataTables/css/demo_table.css" rel="Stylesheet">
<link rel="stylesheet" type="text/css" href="style.css">
<link type="text/css" href="css/smoothness/jquery-ui-1.8.4.custom.css" rel="Stylesheet">
<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.4.custom.min.js"></script>
<script type="text/javascript" src="js/wikifarm-ui.js" language="JavaScript"></script>
<script type="text/javascript" src="js/DataTables/js/jquery.dataTables.js" language="JavaScript"></script>
<script type="text/javascript" language="JavaScript">
	var mywikisLoadTabOnce = '';
	$(function() {
		$("#tabs").tabs({
			selected: <?=$tabActiveId?>,
			show: function(event,ui){window.location.hash="";}
		    });
		$("#tabs").bind("tabsselect", function(){
			$("#tabs .wf-dialog, #tabs .ui-dialog").remove();
		    });
		mywikisLoadTabOnce = '';
		$(".needhelp").css('font-size', '.8em');
		$("#logoutbutton").button().removeClass('ui-corner-all').addClass('ui-corner-tr').addClass('ui-corner-tl').css('padding', '0px');	
	});
</script>
<style type="text/css">
	#pageheader { width: 100%; height: 45; position: relative; }
	#pageheader div { position: absolute; bottom: 0; right: 0; }
	#pageheader img { position: absolute; bottom: 0; left: 0; }
	#tabs { clear: both; }
</style>
</head>
<body>

<div id="pageheader"><div id="logo"></div>
<div><a href="logout.php" id="logoutbutton">Log out</a></div>
</div>

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
