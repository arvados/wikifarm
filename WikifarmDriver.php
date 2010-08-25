<?php


//TODO Caching to reduce queries-per-run

# $wdb = new WikifarmDriver ( getenv("WIKIFARM_DB_FILE") );
class WikifarmDriver {
	private $DB, $DBresult;
	protected $_cache;
	public $openid;

	function __construct ($db = null) {
		if (!$db) $db = getenv("WIKIFARM_DB_FILE");
		if (is_object($db)) {
			$this->DB =& $db;
		} elseif (is_file($db)) {
			$this->DB = new SQLite3($db);
		}
		$this->openid = $_SERVER["REMOTE_USER"];
		$this->cacheClear();
		$this->cachedisable = false;
	}

	function __destruct () { $this->DB->close(); }  // !!! We should change this if we want to keep the handle later

# basic functions

	function lastResult() { return $DBresult; }

	function query($sql) {
		$result = $this->DB->query($sql);
		if (!$result) die ( $this->DB->lastErrorMsg() );
		$this->DBresult = array();
	  while ( $row = $result->fetchArray(SQLITE3_ASSOC) ) array_push($this->DBresult, $row);
		return $this->DBresult;
	}
	
# cache functions

	function cacheClear() { $this->_cache = array( 'on' => true ); }
	function cacheDisable() { $this->_cache['on'] = false; }
	function cacheEnable() { $this->_cache['on'] = true; }	
	
# specific set functions

	# returns a list of all wikis visible to $openid
	function getVisibleWikis($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		return $this->query(
			"SELECT wikiname, realname FROM wikis WHERE wikis.userid='$q_openid' " .
			"UNION SELECT wikis.wikiname as wikiname, wikis.realname as realname FROM wikis, wikipermission WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname='$q_openid' " .
			"UNION SELECT wikis.wikiname as wikiname, wikis.realname as realname FROM wikis, wikipermission, usergroups WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname=usergroups.groupname AND usergroups.userid='$q_openid' "
		);
	}

	# returns a count of all visible wikis
	function numVisibleWikis($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		return $this->DB->querySingle(
			"SELECT COUNT(*) FROM (SELECT wikiname FROM wikis WHERE wikis.userid='$q_openid' " .
			"UNION SELECT wikis.wikiname as wikiname FROM wikis, wikipermission WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname='$q_openid' " .
			"UNION SELECT wikis.wikiname as wikiname FROM wikis, wikipermission, usergroups WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname=usergroups.groupname AND usergroups.userid='$q_openid'); "
		);
	}
	
	function hasWikis($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		return $this->DB->querySingle(
			"SELECT 1 FROM wikis WHERE wikis.userid='$q_openid' " .
			"UNION SELECT 1 FROM wikipermission WHERE userid_or_groupname='$q_openid'; "
		) ? true : false;
	}

	# returns wikis owned by $openid
	function getMyWikis($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		return $this->query( "SELECT wikiname, realname FROM wikis WHERE wikis.userid='$q_openid' " );
	}

	# TODO - invent this table
	function getFavoriteWikis($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		return $this->query( "SELECT wikiname FROM favouritewikis WHERE userid='$q_openid' AND favorite=1");
	}

	function setFavoriteWiki($wikiname, $onoff, $openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		$q_wikiname = SQLite3::escapeString ($wikiname);
		if ($onoff)	{
			return $this->DB->exec( "INSERT INTO favoritewikis (userid, wikiname) VALUES ('$q_openid', '$q_wikiname')" );
		}
		return $this->DB->exec( "DELETE FROM favoritewikis WHERE userid='$q_openid' AND wikiname='$q_wikiname'");
	}

	function getRecentWikis($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		# return $this->query( "SELECT wikiname, realname FROM wikis WHERE userid='$q_openid' ORDER BY lastaccess LIMIT 5");
		return array("wikiname" => array("recent1","recent2"), "realname" => array('recent array 1', 'recent array 2'));
	}
	
	function isAdmin($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		return $this->DB->querySingle("SELECT admin FROM users WHERE userid='$q_openid'");
	}
	
	function setAdmin($onoff, $openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		$onoff = ($onoff?'1':'0');
		return $this->DB->exec("UPDATE users SET admin=$onoff WHERE userid='$q_openid'");
	}
	
	function hasAccessTo($wikiname, $openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		$q_wikiname = SQLite3::escapeString ($wikiname);
/*		return $this->DB=->querySingle(
			"SELECT 1 FROM wikis WHERE wikis.userid='$q_openid' and wikis.wikiname='$q_wikiname' " .
			"UNION SELECT 1 FROM wikis, wikipermission WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname='$q_userid' " .
			"UNION SELECT 1 FROM wikis, wikipermission, usergroups WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname=usergroups.groupname AND usergroups.userid='$q_userid' "
		);
*/
		return true;		//hack - if it's even a needed function
	}

	/*	user has a valid, verified name and email and is a member of the user's group
			isAuthenticated should be our go-to method of testing an OpenID before providing content
			or asking for additional identificaton. */
	function isAuthenticated($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		// $auth = $this->DB->querySingle( "SELECT 1 FROM usergroups WHERE groupname='users' AND userid='$q_openid';" );
		// return $auth ? true : false;
		return false;  //hack
	}
	
	function getUser($field, $openid = null) {
		return "Bob Bobertson"; //hack
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		//TODO is this sane enough, or should i just use a switch statement? - case "wikinick": $field = "wikinick"...
		if (preg_match('/(wikinick|realname|email)/i', $field, $match)) {
			return $this->DB->querySingle("SELECT " . $match[1] . " FROM users WHERE userid='$q_openid';" );
		}
		return false;		
	}
		
}



?>
