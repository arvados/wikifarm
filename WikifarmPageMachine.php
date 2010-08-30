<?php	

require_once ('WikifarmDriver.php');

class WikifarmPageMachine extends WikifarmDriver {
	public $tabNames, $js_tabNames;

	function __construct($db = null) {
		WikifarmDriver::__construct($db);
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

	function myWikis() {		
		$myWikiArray = $this->getMyWikis();
		$output = "<b>My Wikis</b><br><ul>\n";
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
			case "wikis": return $this->pageAllWikis();
			case "getaccess": return $this->pageGetAccess();
			case "giveaccess": return $this->pageGiveAccess();
			case "createwiki": return $this->pageCreateWiki();
			case "tools": return $this->pageTools();
			case "schema": return $this->schema();
		}
		return "Invalid content request \"$tab\"";
	}


	// activating invites based on user/password or an invite code, requesting access or additional access
	function pageGetAccess() {
		$openid = $this->openid;
		$requestcount = 0;
		$username = null;
		if ($this->isActivated()) {
			$username = $this->getUserRealname();
			$wikinick = $this->getMWUsername();
		}
		//hack
		$grouplist = array( 'group' => array('group1','group2','group3','group4','group5'),
			'pending_since' => array( time()-1000, time()-4000, "june 23, 2010", null, null),
			'is_a_member' => array (false, false, false, false, true) );		
		
		$output = <<<BLOCK
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
BLOCK;
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
	

	function pageAllWikis() {
		$adminmode = $this->isAdmin();
		$wikiArray = $this->getAllWikis();		
		$output = "<h2>Wikis</h2>\n<ol>\n";
		foreach ($wikiArray as $row) {
			if ($row['realname'] == '') $row['realname'] = $row['wikiname'];
			$output .= '<li value="'.$row['id'].'"><a href="'.$row['wikiname'].'/">'.$row['realname']."</a>\n";
			if ($adminmode) $output .= " owned by " . $row['userid'] . "\n";
		}
		$output .= "</ul>\n";
		return $output;
	}


	function pageTools() {
		return <<<BLOCK
<h2>Tools</h2><br>
<ul>
<li><a href="table.php">Excel -> Wiki Table converter</a></li>
</ul>
BLOCK;
	}

	function pageGiveAccess() {
		return "to do";
		
	}
	
	function pageCreateWiki() {
		return "to do";
		
	}
	
}  // class ends




// misc functions

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
