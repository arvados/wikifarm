<?php


//TODO Caching to reduce queries-per-run

# $wdb = new WikifarmDriver ( getenv("WIKIFARM_DB_FILE") );
class WikifarmDriver {
	private $DB, $DBresult;
	protected $_cache;
	public $openid, $q_openid;

	function __construct ($db = null) {
		if (!$db) $db = getenv("WIKIFARM_DB_FILE");
		if (is_object($db)) {
			$this->DB =& $db;
		} elseif (is_file($db)) {
			$this->DB = new SQLite3($db);
		}
		$this->Focus();  // $_SERVER["REMOTE_USER"] by default, the user in focus is the currently signed-in one.
		$this->cacheClear();
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
	
	function querySingle($sql) {
		$result = $this->DB->querySingle($sql);
		if (!$result) die ( $this->DB->lastErrorMsg() );
		$this->DBresult = array();
	  while ( $row = $result->fetchArray(SQLITE3_ASSOC) ) array_push($this->DBresult, $row);
		return $this->DBresult;
	}
	
	
	function Focus($openid == null) {
		if (!$openid) $openid = $_SERVER["REMOTE_USER"];
		$this->openid = $openid;
		$this->q_openid = SQLite3::escapeString ($openid);
	}
	
# cache functions

	function cacheClear() { $this->_cache = array( 'on' => true ); }
	function cacheDisable() { $this->_cache['on'] = false; }
	function cacheEnable() { $this->_cache['on'] = true; }	
	
# specific set functions

	# returns a list of all wikis visible to $this->openid
	function getVisibleWikis() {
		$list = $this->query(
			"SELECT id as wikiid, wikiname, realname FROM wikis WHERE wikis.userid='".$this->q_openid."' " .
			"UNION SELECT wikis.wikiname as wikiname, wikis.realname as realname FROM wikis, wikipermission WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname='".$this->q_openid."' " .
			"UNION SELECT wikis.wikiname as wikiname, wikis.realname as realname FROM wikis, wikipermission, usergroups WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname=usergroups.groupname AND usergroups.userid='".$this->q_openid."' "
		);
		foreach ($list as &$row) {
		  if ($row["wikiid"] == 42)
		    $row["autologin"] = array ("TomClegg", "WikiSysop");
		}
		return $list;
	}

	function getAllWikis() {
		$q_openid = $this->q_openid;
		return $this->query(
			"SELECT wikis.id as id, wikis.wikiname as wikiname, wikis.realname as realname, min(wikipermission.readonly) as readonly, wikis.userid as userid
			FROM wikis
			LEFT JOIN usergroups ON usergroups.userid = '$q_openid'
			LEFT JOIN wikipermission ON wikipermission.wikiid = wikis.id
			AND (wikipermission.userid_or_groupname = '$q_openid'
			OR wikipermission.userid_or_groupname = usergroups.groupname)
			WHERE wikis.userid = '$q_openid'
			OR wikipermission.wikiid IS NOT NULL
			OR usergroups.groupname = 'ADMIN'
			GROUP BY wikis.id
			ORDER BY wikis.id" );
	}

	function hasWikis() {
		$id = $this->q_openid;
		return $this->DB->querySingle(
			"SELECT 1 FROM wikis WHERE wikis.userid='$id' " .
			"UNION SELECT 1 FROM wikipermission WHERE userid_or_groupname='$id'; "
		) ? true : false;
	}

	# returns wikis owned by $this->openid
	function getMyWikis() {
		$id = $this->q_openid;
		return $this->query( "SELECT wikiname, realname FROM wikis WHERE wikis.userid='$id' " );
	}

	# TODO - invent this table
	function getFavoriteWikis() {
		$id = $this->q_openid;
		return $this->query( "SELECT wikiname FROM favouritewikis WHERE userid='$id' AND favorite=1");
	}

	function setFavoriteWiki($wikiname, $onoff) {
		$id = $this->q_openid;
		$q_wikiname = SQLite3::escapeString ($wikiname);
		if ($onoff)	{
			return $this->DB->exec( "INSERT INTO favoritewikis (userid, wikiname) VALUES ('$id', '$q_wikiname')" );
		}
		return $this->DB->exec( "DELETE FROM favoritewikis WHERE userid='$id' AND wikiname='$q_wikiname'");
	}

	function getRecentWikis() {
		$id = $this->q_openid;
		# return $this->query( "SELECT wikiname, realname FROM wikis WHERE userid='$id' ORDER BY lastaccess LIMIT 5");
		return array("wikiname" => array("recent1","recent2"), "realname" => array('recent array 1', 'recent array 2'));
	}

	// returns true if $this->openid is a wikifarm admin	
	function isAdmin () {
		$id = $this->q_openid;
		return $this->DB->querySingle("SELECT 1 FROM usergroups WHERE usergroups.userid = '$id' AND groupname = 'ADMIN'" );
	}
	
	function setAdmin($onoff) {
		$id = $this->q_openid;
		$onoff = ($onoff?'1':'0');
		return $this->DB->exec("UPDATE users SET admin=$onoff WHERE userid='$id'");
	}
	
	function getUser($wikiname) {
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
	function isActivated($openid = null) {
		if (!$openid) $openid = $this->openid;
		$q_openid = SQLite3::escapeString ($openid);
		// $auth = $this->DB->querySingle( "SELECT 1 FROM usergroups WHERE groupname='users' AND userid='$q_openid';" );
		// return $auth ? true : false;
		return false;  //hack
	}
	
	function setActivated() {
		
	
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

	
}  // WikifarmDriver class ends


?>
