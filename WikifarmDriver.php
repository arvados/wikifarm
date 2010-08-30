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
		if ($result===false) die ( $this->DB->lastErrorMsg() );
		$this->DBresult = array();
	  while ( $row = $result->fetchArray(SQLITE3_ASSOC) ) array_push($this->DBresult, $row);
		return $this->DBresult;
	}
	
	function querySingle($sql) {
		$this->DBresult = $this->DB->querySingle($sql);
		if ($this->DBresult===false) die ( $this->DB->lastErrorMsg() );
		return $this->DBresult;
	}
		
	function Focus ($openid = null) {
		if (!$openid) $openid = $_SERVER["REMOTE_USER"];
		$this->openid = $openid;
		$this->q_openid = SQLite3::escapeString ($openid);
	}
	
# sanity functions
# NOTE: These are not for sanitizing text input, and do not. They merely verify table data.

	function is_a_group($group) { return ($this->querySingle("SELECT 1 FROM usergroups WHERE groupname = '$group';")) ? true : false; }
	function is_a_user($openid) { return ($this->querySingle("SELECT 1 FROM users WHERE userid = '$openid';")) ? true : false; }
	function is_a_wiki($wikiname) { return ($this->querySingle("SELECT 1 FROM wikis WHERE wikiname = '$wikiname';")) ? true : false; }
		
# cache functions

	function cacheClear() { $this->_cache = array( 'on' => true ); }
	function cacheDisable() { $this->_cache['on'] = false; }
	function cacheEnable() { $this->_cache['on'] = true; }	
	
# specific set functions

	# returns a list of all wikis visible to $this->openid
	function getVisibleWikis() {
		$id = $this->q_openid;
		$list = $this->query(
			"SELECT id as wikiid, wikiname, realname FROM wikis WHERE wikis.userid='$id' " .
			"UNION SELECT wikis.wikiname as wikiname, wikis.realname as realname FROM wikis, wikipermission WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname='$id' " .
			"UNION SELECT wikis.wikiname as wikiname, wikis.realname as realname FROM wikis, wikipermission, usergroups WHERE wikis.id=wikipermission.wikiid AND wikipermission.userid_or_groupname=usergroups.groupname AND usergroups.userid='$id' "
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

	# returns true if the focus user owns any wikis
	function hasWikis() {
		$id = $this->q_openid;
		return $this->querySingle(
			"SELECT 1 FROM wikis WHERE wikis.userid='$id' " .
			"UNION SELECT 1 FROM wikipermission WHERE userid_or_groupname='$id'; "
		) ? true : false;
	}

	# returns wikis owned by the focus user
	function getMyWikis() {
		$id = $this->q_openid;
		return $this->query( "SELECT wikiname, realname FROM wikis WHERE wikis.userid='$id' " );
	}
	
	# returns a list of wikis selected by the focus user as favorites  TODO - invent this table
	function getFavoriteWikis() {
		$id = $this->q_openid;
		return $this->query( "SELECT wikiname FROM favouritewikis WHERE userid='$id' AND favorite=1");
	}

	function setFavoriteWiki($wikiname, $onoff = 1) {
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
		return $this->querySingle("SELECT 1 FROM usergroups WHERE usergroups.userid = '$id' AND groupname = 'ADMIN'" );
	}
	
	function setAdmin($onoff) {
		$id = $this->q_openid;
		$onoff = ($onoff?'1':'0');
		return $this->DB->exec("UPDATE users SET admin=$onoff WHERE userid='$id'");
	}

	function getUserGroups() {
		$id = $this->q_openid;
		return $this->query( "SELECT groupname FROM usergroups WHERE userid='$id'; "	);		
	}

	function getRequestedGroups() {
		$id = $this->q_openid;
		//return $this->query( "SELECT groupname FROM usergroups WHERE usergroups.userid='$id'; "	);		
		return array('grp1'=>"Group One",'grp2'=>"Group Two",'grp3'=>"Group Three",'grp4'=>"Group Four");		
	}
		
	function getAllGroups() {
		return $this->query('SELECT groupname FROM usergroups GROUP BY groupname;');
	}
	

	/*	user has a valid, verified name and email and is a member of the user's group			
			it should be our go-to method of testing an OpenID before providing content
			or asking for additional identificaton. */
	function isActivated($openid = null) {
		$id = $this->q_openid;
		return $this->querySingle( "SELECT 1 FROM usergroups WHERE groupname='users' AND userid='$id';" );
//		return 1;  //hack
	}
	
	function setActivated() {
		
		//TODO
	}

	function getUserRealname() {
		return "Bob Bobertson"; //hack
		$id = $this->q_openid;
		return $this->querySingle("SELECT realname FROM users WHERE userid='$id';" );
	}
	
	function setUserRealname($name) {
		$name = SQLite3::escapeString ($name); //TODO verifiy this is filtered enough
		$id = $this->q_openid;
		return $this->DB->exec("UPDATE users SET realname='$name' WHERE userid='$id';" );
	}		

	function getUserEmail() {
		return "bob@bobbobertson.com"; //hack
		$id = $this->q_openid;
		return $this->querySingle("SELECT email FROM users WHERE userid='$id';" );
	}

	function getUserByEmail($email) {		
		$email = SQLite3::escapeString (filter_var($email, FILTER_VALIDATE_EMAIL));
		return $this->querySingle("SELECT userid FROM users WHERE email='$email';" );
	}
	
	function getMWUsername() {
		//TODO
		return "Bob";
	}
		
	function setMWUsername($nickname) {
		//TODO
		return true;
	}
	
	function requestGroup($groups) {
		if (!is_array($groups)) $groups = array($groups);
		foreach ($groups as $group) {
			$group = SQLite3::escapeString($group);
			if ($this->is_a_group($group)) {  //group is legit
				// TODO
			}
		}
	}

# Admin Stuff
	
	function getAllRequests() {
		//TODO
		
		return array ("requestid" => 123, "userid" => 'erere', "groupname" => "users");
	}
	
	function approveRequestId($requestid) {
		//TODO
	}
	
	function rejectRequestId($requestid) {
		//TODO
	}
	
# Invite Codes	

	function claimInvite($code) {
		$q_openid = $this->q_openid;
		//TODO
		
		
	}
	
	function claimInviteByPassword($username, $password) {
	
		//TODO
	}
	
	function createInvite($group, $wiki, $email) {
		//TODO: figure out what this should do
		return "a secret code!";
	}
	
	function inviteUser($wikiid,$invitee_email,$mwusername=false) {
		$existingUser = $this->getUserByEmail($email);
		if ($existingUser) {
			//TODO
			
		} else {
		
		}
	}



	
}  // WikifarmDriver class ends


?>
