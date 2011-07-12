<?php ; // -*- mode: java; c-basic-indent: 4; tab-width: 4; indent-tabs-mode: nil; -*-

$GLOBALS["microtime0"] = microtime(true);

ini_set('display_errors',1);
error_reporting(E_ALL);

require_once('WikifarmDriver.php');
require_once('WikifarmPageMachine.php');

$wikifarmConfig = array("servername" => $_SERVER["HTTP_HOST"],
			"byline" => "a wiki farm");
if (file_exists ($configfile = getenv("WIKIFARM_ETC") . "/config.php"))
   include_once($configfile);

$wf = new WikifarmPageMachine();

if (isset($_GET["modauthopenid_referrer"]) &&
    strlen($_GET["modauthopenid_referrer"]) &&
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

// Push out a database backup
if (isset($_GET["backup"])) {
	$wf->wikiBackup($_GET["backup"]);
	exit;
}
if (isset($_GET["adminbackup"])) {
	$wf->wikiFarmBackup(isset($_GET["withwikis"]));
	exit;
}

header('Content-Language: en');
	
// Would sir enjoy some tab content?
if (isset($_GET['tab'])) {
	echo $wf->tabGet($_GET['tab']);
	// error_log ($_GET['tab'] . ": " . floor((microtime(true) - $GLOBALS["microtime0"])*1000) . " ms");
	exit;
}

// what tabs should we see?
$tabTitles = array(
			'requests'=>'Requests',
			'wikis'=>'All Wikis',
			'mywikis'=>'My Wikis',
			'groups'=>'Groups',
			'mygroups'=>'Manage Groups',
			'myaccount'=>'My Account',
			'users'=>'User List',
			'debug'=>'Debug',
			'help'=>'Help',
			'adminhelp'=>'Admin Help'
			);

if (!$wf->isAdmin()) {
	unset ( $tabTitles['debug'] );
	unset ( $tabTitles['settings'] );
	unset ( $tabTitles['adminhelp'] );
    if (!count($wf->getMyGroups()))
        unset ( $tabTitles['mygroups'] );
} elseif (!(isset ($_GET["tabActive"]) && $_GET["tabActive"] == "debug")) {
	unset ( $tabTitles['debug'] );
}

if (!$wf->getUserRealname() || !$wf->getUserEmail()) {
	$tabActive = "myaccount";
	$tabTitles = array ($tabActive => $tabTitles[$tabActive],
			    'help' => $tabTitles['help']);
}
else if (!$wf->isActivated()) {
	$tabActive = "groups";
	$tabTitles = array ($tabActive => $tabTitles[$tabActive],
			    'myaccount' => $tabTitles['myaccount'],
			    'help' => $tabTitles['help']);
}
else {
	$tabActive = "wikis";
    if (!$wf->getWikiQuota() && !count($wf->getMyWikis()))
        unset ( $tabTitles['mywikis'] );
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

?><html lang='en'>
<head>
<title><?=preg_replace('{([^\.]+\.?[^\.]*).*}','$1',`hostname`);?> dashboard</title>
<link type="text/css" href="js/DataTables/css/demo_page.css" rel="Stylesheet">
<link type="text/css" href="js/DataTables/css/demo_table.css" rel="Stylesheet">
<link rel="stylesheet" type="text/css" href="style.css">
<link type="text/css" href="css/smoothness/jquery-ui-1.8.4.custom.css" rel="Stylesheet">
<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.4.custom.min.js"></script>
<script type="text/javascript" src="js/DataTables/js/jquery.dataTables.js" language="JavaScript"></script>
<script type="text/javascript" src="js/wikifarm-ui.js" language="JavaScript"></script>
<script type="text/javascript" language="JavaScript">
	var mywikisLoadTabOnce = '';
	var mygroupsLoadTabOnce = '';
	$(function() {
		$("#tabs").tabs({
			selected: <?=$tabActiveId?>,
			show: function(event,ui){window.location.hash="";}
    });
		//autodestructor
		$("#tabs").bind("tabsselect", function(event,ui){
			if ($(ui.panel).parent().attr("id")!="tabs") return;
			$("#tabs>div").empty();
			$("body>div.wf-dialog").empty().remove();
			$("body>div.ui-dialog").empty().remove();
		});
        // If session times out, reload the whole page instead of
        // showing the login form inside a tab.
		$("#tabs").bind("tabsload", function(event,ui){
                if ($(ui.panel).find('form.openidloginform').length)
                    window.location='./';
            });
		mywikisLoadTabOnce = '';
		mygroupsLoadTabOnce = '';
		$(".needhelp").css('font-size', '.8em');
		$("#pageheader a").button().removeClass('ui-corner-all').addClass('ui-corner-tr').addClass('ui-corner-tl').css('padding', '0px');
	});	

</script>
<style type="text/css">
	#pageheader { width: 100%; height: 64px; position: relative; background: url('serverlogo.png') no-repeat top left; }
	#pageheader div { position: absolute; bottom: 0; right: 0; }
	#pageheader div#byline { position: absolute; bottom: 16px; left: 0; text-align: center; }
	#tabs { clear: both; }
</style>
</head>
<body>

<div id="pageheader"><div id="byline"><h3><?=$wikifarmConfig["servername"]?></h3><?=$wikifarmConfig["byline"]?></div>
<div>
<a href="logout.php" class="logoutinfo">Log out</a>
<? if (preg_match ('{^https?://(\w+\.)?google\.com/}i', $_SERVER["REMOTE_USER"])) { ?>
<a href="logout.php?logout_google=1">Log out of here <strong>and</strong> Google</a>
<? } ?>
</div>
</div>

<div id="tabs">
	<ul>
<?php  // jquery ui tabs
	foreach ($tabTitles as $tab => $title) {
		echo "\n\t\t<li><a tab_id=\"$tab\" href=\"?tab=$tab\" title=\"$title\">$title</a></li>";
	}
?>	
	</ul>
</div>

<div id="dialog-container"></div>

</body>
</html>
<script type="text/javascript" language="JavaScript">
		$(".logoutinfo").live("click",function(){return confirm("You will be logged out of the wiki farm, but you may still be logged in to your OpenID provider.\n\nIf you are using a shared computer, after logging out of the wiki farm you should also log out of your OpenID provider, clear your cookies and quit your browser.");});
</script>
