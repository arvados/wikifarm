<?php	

require_once ('WikifarmDriver.php');

class WikifarmPageMachine extends WikifarmDriver {
	public $tabNames, $js_tabNames;

	function __construct($db = null) {
		WikifarmDriver::__construct($db);
		$this->tabNames = array(
			'wikis'=>'Wikis',
			'getaccess'=>'Get Access',
			'giveaccess'=>'Give Access',
			'createwiki'=>'Create a Wiki',
			'tools'=>'Tools' );
	}

	function schema() {		
		$output = "<b>current sqlite schema: </b><br><pre>";
		$result = $this->query( "SELECT sql FROM sqlite_master" );
		foreach ($result as $row) { 
			$output .= htmlspecialchars($row['sql']) . "\n\n";
		}
		$output .= "</pre>";
		return $output;
	}

	function myWikis($openid = null) {
		if (!$openid) $openid = $this->openid;
		$myWikiArray = $this->getMyWikis($openid);
		$output = "<b>My Wikis</b><br><ul>\n";
		foreach ($wikiArray as $w) {
			$output .= '<li><a href="'. $this->wikiURL($w['wikiname']) . '">' . $w['realname'] . "</a> ...</li>\n";
		}
		$output .= "</ul>\n";
		return $output;
	}
	
	function allWikis($openid = null) {
		if (!$openid) $openid = $this->openid;
		$wikiArray = $this->getVisibleWikis($openid);
		$output = "<b>All Wikis</b><br><ul>\n";
		foreach ($wikiArray as $w) {
			$output .= '<li><a href="'. $this->wikiURL($w['wikiname']) . '">' . $w['realname'] . "</a> ...</li>\n";
		}
		$output .= "</ul>\n";
		return $output;
	}

	function wikiURL($wikiid) {
		return "http://serverlyserver.com/pathy-path-path/";
	}

# all about the tabs	

	function tabGet($tab) {
		switch ($tab) {
			case "wikis": return $this->allWikis();
			case "getaccess": return $this->pageGetAccess();
			case "giveaccess": return $this->allWikis();
			case "createwiki": return $this->allWikis();
			case "tools": return $this->allWikis();
		}
		return "Invalid content request \"$tab\"";
	}

	function tabFrame($openid = null) {
		if (!$openid) $openid = $this->openid;
		$js = array();		
		$output = "<div style=\"display: block;padding:10px;background-color:#dae6fa\" id=\"tabdiv\">\n<ul id=\"tabmenu\" >\n";
		foreach ($this->tabNames as $tab => $friendly) {
			$output .= "<li><a class=\"\" id=\"$tab\">$friendly</a></li>\n";
			array_push ($js, "\"$tab\"");
		}
		$this->js_tabNames = "tabNames = [" . implode(',', $js) . "];\n";
		$output .= "</ul>\n<div id=\"content\"></div>\n</div>";
		return $output;
	}

	// activating invites based on user/password or an invite code, requesting access or additional access
	function pageGetAccess($openid = null) {
		if (!$openid) $openid = $this->openid;
		$requestcount = 0;
		$username = null;
		if ($this->isAuthenticated($openid)) {
			$username = $this->getUser('realname', $openid);
			$wikinick = $this->getUser('wikinick', $openid);
		}
		// $grouplist = $this->query("SELECT ..."); TODO
		//hack
		$grouplist = array( 'group' => array('group1','group2','group3','group4','group5'),
			'pending_since' => array( time()-1000, time()-4000, "june 23, 2010", null, null),
			'is_a_member' => array (false, false, false, false, true) );		
		
		$output = <<<EOM
<table width=100%><tr><td>
Already have an invite code or a pre-OpenID username and password?<br><br>
<blockquote>
<form action="index.php" method="post">
Username: <input type=text name=username size=16>
<br />Password: <input type=password name=password size=16>

<blockquote>Or</blockquote>

Invite Code: <input type=text name=invite size=16>
<br /><input type=submit value="Get Access">
</form>

</blockquote>
After you do this, your wiki and group memberships will be
attached to the OpenID you are currently logged in as ($openid).

</td><td class=vertbreak>|</td><td>

Request access to stuff (approval required, we'll let you know)
<blockquote>
<form action="index.php" method="post">
EOM;
		if ($username) {
			$output .= "You are signed in as: <b>$username</b><br>";
		} else {
			$output .= "Your Name: <input type=text name=realname size=16> Email Adress: <input type=text name=email size=16>";
		}
		$output .= "Groups you wish to request membership to:<br>
<table><tr><td>group name</td><td>membership status</td></tr>";
		foreach ($grouplist['group'] as $i => $group) {
			$requestcount++;
			$output .= "\n<tr><td>$group</td><td>";
			if ($grouplist['pending_since'][$i]) {
				$output .= "Request pending since " . PMRelativeTime($grouplist['pending_since'][$i]);
			} elseif ($grouplist['is_a_member'][$i]) {
				$output .= "You are a member";
			} else {
				$output .= "<input type=\"checkbox\" name=\"request$requestcount\" value=\"$group\" /> Request membership";
			}
			$output .= "</td></tr>";
		}
		$output .= "</table><input type=submit value=\"Send Request\"></form>\n</blockquote>";
		return $output;
	}
}

function PMRelativeTime($date) {
	if ($date+0 == 0) $date = strtotime($date);
	$diff = time() - $date;
	if ($diff<60) {
		$r = "$diff second";
	} else {
		$diff = round($diff/60);
		if ($diff<60) {
			$r = "$diff minute";
		} else {
			$diff = round($diff/60);
			if ($diff<24) {
				$r = "$diff hour";
			} else {
				$diff = round($diff/24);
				if ($diff<7) {
					$r = "$diff day";
				} else {
					$diff = round($diff/7);
					if ($diff<4) {
						$r = "$diff week";
					} else {
						return date("F j, Y", $date);
					}
				}
			}
		}
	}
	return $r . ($diff !=1 ? 's' : '') . " ago";
}

?>
