<?php ; // -*- mode: java; c-basic-indent: 4; tab-width: 4; indent-tabs-mode: nil; -*-

// Copyright 2010 President and Fellows of Harvard College
//
// Authors:
// Tom Clegg <tom@clinicalfuture.com>
// Jer Ratcliffe <jer@clinicalfuture.com>
//
// This file is part of wikifarm.
//
// Wikifarm is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License version 2 as
// published by the Free Software Foundation.
//
// Wikifarm is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with wikifarm.  If not, see <http://www.gnu.org/licenses/>.

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
		if (!$this->DB) {
			error_log(__METHOD__.": Fatal! Unable to connect to database. \$this->DB is not an object.");
			die("Fatal: The wikifarm database was unavailable.\n\n");
		}
		$this->cacheClear();
		$this->Focus();  // $_SERVER["REMOTE_USER"] by default, the user in focus is the currently signed-in one.
	}

	function getAdminEmails() {
		$ret = array();
		foreach ($this->query ("SELECT email FROM usergroups LEFT JOIN users ON users.userid=usergroups.userid LEFT JOIN userpref ON users.userid=userpref.userid AND userpref.prefid='admin_notify_requests' WHERE groupname='ADMIN' AND userpref.value") as $g)
			if (!array_search ($g["email"], $ret))
				$ret[] = $g["email"];
		return $ret;
	}

	function __destruct () { $this->DB->close(); }  // !!! We should change this if we want to keep the handle later

# basic functions

	function lastResult() { return $DBresult; }

	// takes a sql statement, returns a numeric array (rows) filled with associative arrays (cols)
	function query($sql) {
		$result = $this->DB->query($sql);
		if ($result===false) die ( $this->DB->lastErrorMsg() );
		$this->DBresult = array();
	  while ( $row = $result->fetchArray(SQLITE3_ASSOC) ) array_push($this->DBresult, $row);
		return $this->DBresult;
	}
	
	// returns a single result
	function querySingle($sql) {
		$this->DBresult = $this->DB->querySingle($sql);
		if ($this->DBresult===false) die ( $this->DB->lastErrorMsg() );
		return $this->DBresult;
	}
	
	// returns a one-dimentional numeric array
	function queryList($sql) {
		$result = $this->DB->query($sql);
		if ($result===false) die ( $this->DB->lastErrorMsg() );
		$this->DBresult = array();
	  while ( $row = $result->fetchArray(SQLITE3_NUM) ) array_push($this->DBresult, $row[0]);
		return $this->DBresult;
	}

	// sets the object's focus on a user based on their openid
	function Focus ($openid = null) {
		$lastid = $this->openid;
		if (!$openid) $openid = $_SERVER["REMOTE_USER"];
		if ($openid === $this->openid) return;
		$this->openid = $openid;
		$this->q_openid = SQLite3::escapeString ($openid);
		$this->cacheClear();  //hack
		$user =& $this->cacheRefDefault('user');
		if ($lastid && $user) $this->_cache["user:$lastid"] = $user;
		if ($this->cacheHit("user:$openid")) {
			$user = $this->_cache["user:$openid"];
		} else {
			foreach ($this->query ("SELECT * FROM users WHERE userid='".$this->q_openid."' LIMIT 1") as $u)
				$user = $u;
			if (!$user)
				foreach ($this->query ("SELECT users.* FROM wikis LEFT JOIN users ON 1=2 LIMIT 1") as $u)
					$user = $u;
		}
		return ($user ? true : false);
	}
	
	// security function - tests and logs security calls
	// if (!$this->_security( array( 'access' => 'activated', method => __METHOD__ ) )) return false;
	// if (!$this->_security( array( 'access' => 'read', 'wiki' => 42 ))) return "No access to wiki";
	// if (!$this->_security('admin')) return false;
	// activated, admin (for site) read, write, owner (for wiki)
	function _security($param = 'activated', $wiki = false ) {
		if (is_array($param)) {
			extract ($param);
		} else {
			$access = $param;
			$wiki += 0;
		}
		$backtrace = debug_backtrace();
		if (!isset($method)) $method = $backtrace[1]['function'];
		$log = "(security) $method: ";
		if (preg_match("/a(ctivated)?/i", $access) && !$this->isActivated() ) {
			$defaultmessage = "called by non-activated user.";
		} elseif (preg_match("/admin/i", $access) && !$this->isAdmin()) {
			$defaultmessage = "called by non-admin user.";
		} elseif (preg_match("/read/i", $access)) { // TODO finish this read/write/owner wiki privs
			$defaultmessage = "function unable to complete this!";
		} elseif (preg_match("/write/i", $access)) { // TODO finish this read/write/owner wiki privs
			$defaultmessage = "security function unable to complete this request!";
		} elseif (preg_match("/owner/i", $access)) { // ~jer
			if ($wiki+0 == 0)			
				$owner_userid = $this->querySingle ("SELECT userid FROM wikis WHERE wikiname= '". (SQLite3::escapeString ($wiki)) ."';" );
			else
				$owner_userid = $this->querySingle ("SELECT userid FROM wikis WHERE id=".($wiki+0) );
			if ($this->openid === $owner_userid || ($or_admin && $this->isAdmin())) 
				return true;			
			$defaultmessage = "User doesn't own that wiki! User:{$this->openid} Wiki:{$wiki}";
		} else {
			return true;
		}
		if (!isset($no_log))
			error_log ("(security) $method: ".(isset($message) ? $message : $defaultmessage) );
		return false;
	}

	// sanity functions
	// NOTE: These are not for sanitizing text input, and do not. They merely verify table data.
	// Never mind, it appears now as though they do

	function is_a_group($group) {
		$groups =& $this->cacheRefDefault('is_a_group');
		if (!is_array($groups)) {
			$groups = array();
			foreach ($this->getAllGroups(true) as $g)
				$groups[$g['groupid']] = true;
		}			
		return array_key_exists($group, $groups);
	}
	function is_a_user($openid) {	
		return ($this->querySingle("SELECT 1 FROM users WHERE userid = '".SQLite3::escapeString($openid)."'")) ? true : false;
	}
	function is_a_wiki($wikiname) {
		return $this->querySingle("SELECT 1 FROM wikis WHERE wikiname = '".SQLite3::escapeString($wikiname)."'") ? true : false;
	}
	
		
	// cache functions

	function cacheClear() { $this->_cache = array( 'on' => true ); }
	function cacheDisable() { $this->_cache['on'] = false; }
	function cacheEnable() { $this->_cache['on'] = true; }
	function &cacheRef($i) { return $this->_cache[$i]; }
	function &cacheRefDefault($i, $val = false) {
		if (!$this->cacheHit($i)) 
			$this->_cache[$i] = $val;
		return $this->_cache[$i];
	}
	function cacheHit($i) { return array_key_exists($i, $this->_cache); }
	function cacheDebugDump() { $this->_cache; }
	
	// specific set functions

	// returns a list of all wikis visible to $this->openid
	function &getVisibleWikis() {
		$wikis =& $this->getAllWikis();
		foreach ($wikis as $k => $w)
			if (!$w["readable"])
				unset ($wikis[$k]);
		return $wikis;
	}

	function _preloadMyRequests() {
		if (array_key_exists ("requested_autologin", $this->_cache))
			return;

		$this->_cache["requested_autologin"] = array();
		$this->_cache["requested_readable"] = array();
		$this->_cache["requested_group"] = array();

		$reqs = $this->query ("SELECT * FROM request WHERE userid='".$this->q_openid."'");
		foreach ($reqs as &$x) {
			if ($x["wikiid"] && $x["mwusername"]) {
				$this->_cache["requested_autologin"][$x["wikiid"]][] = $x["mwusername"];
				$this->_cache["requested_readable"][$x["wikiid"]] = true;
			}
			else if ($x["wikiid"])
				$this->_cache["requested_readable"][$x["wikiid"]] = true;
			else if ($x["groupname"])
				$this->_cache["requested_group"][] = $x["groupname"];
		}
	}

	function getAllWikis() {
		if (array_key_exists ("allwikis", $this->_cache))
			return $this->_cache["allwikis"];

		$wikis =& $this->query(
			"SELECT wikis.id as id,
			wikis.wikiname as wikiname,
			wikis.realname as realname,
			wikis.userid as userid,
			wikis.userid as owner_userid,
			users.realname as owner_realname
			FROM wikis 
			LEFT JOIN users ON users.userid = wikis.userid
			ORDER BY wikis.id" );

		$readable = array();
		$x = $this->query ("SELECT * FROM usergroups
			LEFT JOIN wikipermission ON (userid_or_groupname=groupname OR groupname = 'ADMIN' OR userid_or_groupname=userid)
			WHERE userid='".$this->q_openid."' AND userid_or_groupname IS NOT NULL
			GROUP BY wikiid");
		foreach ($x as &$row)
			$readable[$row["wikiid"]] = true;

		$wikigroup = array();
		$x = $this->query ("SELECT * FROM wikipermission LEFT JOIN usergroups ON groupname=userid_or_groupname WHERE groupname IS NOT NULL GROUP BY wikiid, groupname");
		foreach ($x as &$row)
			$wikigroup[$row["wikiid"]][] = $row["groupname"];

		$this->_preloadMyRequests();
		$autologin = array();
		$x = $this->query ("SELECT * FROM autologin WHERE userid='".$this->q_openid."'");
		foreach ($x as &$row)
			$autologin[$row["wikiid"]][] = $row["mwusername"];

		foreach ($wikis as &$row) {
		    $row["wikiid"] = $row["id"];
		    $row["readable"] = false;
		    $row["requested_readable"] = false;
		    if ($row["userid"] == $this->openid)
			$row["readable"] = true;
		    else if (array_key_exists ($row["id"], $readable))
			$row["readable"] = true;
		    else if (array_key_exists ($row["id"], $this->_cache["requested_readable"]))
			$row["requested_readable"] = true;

		    if (array_key_exists ($row["id"], $autologin))
			$row["autologin"] = $autologin[$row["id"]];
		    else
			$row["autologin"] = false;
		    if (array_key_exists ($row["id"], $this->_cache["requested_autologin"]))
			$row["requested_autologin"] = $this->_cache["requested_autologin"][$row["id"]];
		    else
			$row["requested_autologin"] = false;

		    if (array_key_exists ($row["id"], $wikigroup))
			    $row["groups"] = $wikigroup[$row["id"]];
		    else
			    $row["groups"] = array();
		}

		$this->_cache["allwikis"] = $wikis;
		return $wikis;
	}

	// returns true if the focus user owns any wikis
	function hasWikis() {
		$id = $this->q_openid;
		$ci = "user:$id:".__FUNCTION__;
		if (!$this->cacheHit($ci)) $this->_cache[$ci] = $this->querySingle(
				"SELECT 1 FROM wikis WHERE wikis.userid='$id' " .
				"UNION SELECT 1 FROM wikipermission WHERE userid_or_groupname='$id';" );
		return $this->_cache[$ci];
	}

	// returns wikis owned by the focus user
	function getMyWikis() {
		$wikis = array();
		foreach ($this->getAllWikis() as $w)
			if ($w["owner_userid"] == $this->openid)
				$wikis[] = $w;
		return $wikis;
	}

	// returns groups managed by the focus user
	function getMyGroups() {
		$id = $this->q_openid;
        if ($this->isAdmin())
            return $this->getAllGroups();
        else
            return $this->query("SELECT groupname FROM usergroups WHERE userid='$id' AND isadmin>0 ORDER BY groupname");
	}

	function getWiki ($wikiid) {
		foreach ($this->getAllWikis() as $w)
			if ($w["wikiid"] == $wikiid)
				return $w;
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

	function getWikiQuota() {
		if (!$this->isActivated()) return 0;
		$quota = $this->_cache["user"]["wikiquota"];
		if (!isset($quota)) $quota = 5;
		return $quota;
	}
	
	function setWikiQuota($newquota) {
		$this->_cache["user"]["wikiquota"] = $newquota;
		$this->DB->exec ("UPDATE users SET wikiquota=$newquota WHERE userid='{$this->q_openid}'");
	}

	function canCreateWikis() {
		return (count($this->getMyWikis()) < $this->getWikiQuota());
	}

	function isWikiNameAvailable($wikiname) {
		return !$this->querySingle ("SELECT 1 FROM wikis WHERE wikiname='".SQLite3::escapeString ($wikiname)."'");
	}

	function createWiki($wikiname, $realname, $mwusername, $groups) {
		$ok = $this->DB->exec ("INSERT INTO wikis (wikiname, userid, realname) values ('"
				       .SQLite3::escapeString ($wikiname)."','"
				       .SQLite3::escapeString ($this->openid)."','"
				       .SQLite3::escapeString ($realname)."')");
		if (!$ok) return false;
		$wikiid = $this->querySingle ("SELECT last_insert_rowid()");
		if (!$wikiid) return false;
		$this->DB->exec ("INSERT INTO autologin (wikiid, userid, mwusername, lastlogintime, sysop) values ($wikiid, '".$this->q_openid."','".SQLite3::escapeString ($mwusername)."',strftime('%s','now'),1)");
		foreach ($this->getAllGroups() as $g)
			if ($groups && false !== array_search ($g["groupid"], $groups))
				$this->DB->exec ("INSERT INTO wikipermission (wikiid, userid_or_groupname) VALUES ($wikiid, '".SQLite3::escapeString ($g["groupid"])."')");
		$this->inviteUser ($wikiid, $this->openid, $mwusername);

		$wikiid = sprintf ("%02d", $wikiid);
		$etc = getenv("WIKIFARM_ETC");
		if (false === system ("sudo -u ubuntu $etc/wikifarm-create-wiki "
				      .escapeshellarg($wikiid)." "
				      .escapeshellarg($wikiname)." "
				      .escapeshellarg($realname)." "
				      ." >>/tmp/wikifarm-create-wiki.log." . posix_getpid()))
			return false;
		return true;
	}

	// Affects a group's relationship with a wiki
	function inviteGroup ($wikiid, $groupid) {
		$wikiid += 0;		
		$this->DB->exec ("INSERT OR IGNORE INTO wikipermission (wikiid, userid_or_groupname) values ($wikiid, '".SQLite3::escapeString($groupid)."')");
	}

	function disinviteGroup ($wikiid, $groupid) {
		$wikiid += 0;
		$this->DB->exec ("DELETE FROM wikipermission WHERE wikiid=$wikiid AND userid_or_groupname='".SQLite3::escapeString($groupid)."'");
	}

	function getInvitedUsers ($wikiid) {
		$this->DB->exec ("INSERT OR IGNORE INTO users (userid) SELECT DISTINCT userid FROM usergroups");
		$u = $this->query ("
SELECT users.userid, CASE WHEN usergroups.groupname=userid_or_groupname THEN usergroups.groupname ELSE NULL END AS read_via_group, autologin.mwusername, autologin.sysop
 FROM wikis
 LEFT JOIN users
 LEFT JOIN usergroups ON users.userid = usergroups.userid
 LEFT JOIN wikipermission ON wikipermission.wikiid=wikis.id AND (usergroups.groupname=userid_or_groupname OR users.userid=userid_or_groupname)
 LEFT JOIN autologin ON autologin.wikiid=wikis.id AND autologin.userid=users.userid
 WHERE wikis.id='$wikiid'
 AND wikipermission.wikiid IS NOT NULL
 AND usergroups.groupname IS NOT NULL");
		return $u;
	}

	// returns true if $this->openid is a wikifarm admin	
	function isAdmin () {
		$id = $this->q_openid;
		if (!array_key_exists ('isadmin', $this->_cache)) {
			$this->_cache['isadmin'] = $this->querySingle("SELECT 1 FROM usergroups WHERE usergroups.userid = '$id' AND groupname = 'ADMIN'" );
		}
		return $this->_cache['isadmin'];		
	}		
	
	function getUserGroups() {
		$id = $this->q_openid;
		if (!array_key_exists ("usergroups", $this->_cache)) {
			$this->_cache["usergroups"] = array();
			$x = $this->query( "SELECT groupname FROM usergroups WHERE userid='$id'");
			foreach ($x as $row)
				$this->_cache["usergroups"][] = $row["groupname"];
		}
		return $this->_cache["usergroups"];
	}

	function getRequestedGroups() {
		$this->_preloadMyRequests();
		return $this->_cache["requested_group"];
	}
		
	function getAllGroups($with_admin=false) {
		if (!array_key_exists ("allgroups:$with_admin", $this->_cache)) {
			$this->_preloadMyRequests();
			$skipadmin = ($this->isAdmin() || $with_admin) ? "" : "WHERE groupname <> 'ADMIN'";
			$this->_cache["allgroups"] = $this->query("SELECT groupname as groupid, groupname as groupname FROM usergroups $skipadmin GROUP BY groupname UNION SELECT 'users', 'users'");
			foreach ($this->_cache["allgroups"] as &$g) {
				$g["requested"] = false !== array_search ($g["groupname"], $this->_cache["requested_group"]);
				$g["member"] = false !== array_search ($g["groupname"], $this->getUserGroups());
			}
		}
		return $this->_cache["allgroups"];
	}
	
	// simple 1d list of groupids
	function getAllGroupIDs() {
		$all_groupids =& $this->cacheRefDefault('getAllGroupIDs');
		if (!is_array($all_groupids))
			$all_groupids = array_map( function ($g) { return $g["groupid"]; }, $this->getAllGroups() );
		return $all_groupids;
	}

    function getGroupMembers($groupname) {
        $q_groupname = SQLite3::escapeString($groupname);
        return $this->query("SELECT users.*, usergroups.isadmin FROM usergroups LEFT JOIN users ON users.userid=usergroups.userid WHERE usergroups.groupname='{$q_groupname}' AND users.userid IS NOT NULL");
    }
	
	// Has this user been added to one or more groups, i.e.,
	// sanctioned as a legitimate user?  If not, we have no idea
	// whether she's a spammer, attacker, spy, hater, etc.

	function isActivated() {
		return false !== array_search ("users", $this->getUserGroups())
			|| false !== array_search ("ADMIN", $this->getUserGroups());
	}

	function isActivationRequested() {
		if ($this->isActivated()) return false;
		foreach ($this->getAllGroups() as $g)
			if ($g["groupid"] == "users" && $g["requested"])
				return true;
		return false;
	}
	
	function getUserRealname() {
		$id = $this->q_openid;
		return $this->querySingle("SELECT realname FROM users WHERE userid='$id';" );
	}
	
	function setUserRealname($name) {
		$id = $this->q_openid;
		$this->DB->exec ("INSERT OR IGNORE INTO users (userid) VALUES ('$id')");
		return $this->DB->exec("UPDATE users SET realname='$name' WHERE userid='$id';" );
	}		

	function getUserEmail() {
		$id = $this->q_openid;
		return $this->querySingle("SELECT email FROM users WHERE userid='$id';" );
	}

	function setUserEmail($email) {
		$id = $this->q_openid;
		$this->DB->exec ("INSERT OR IGNORE INTO users (userid) VALUES ('$id')");
		return $this->DB->exec("UPDATE users SET email='".SQLite3::escapeString(filter_var($email, FILTER_VALIDATE_EMAIL))."' WHERE userid='$id';" );
	}

	function getUserPrefs() {
		$noadmin = $this->isAdmin() ? "" : "WHERE pref.prefid NOT LIKE 'admin_%'";
		return $this->query ("SELECT pref.prefid, type, description, value FROM pref LEFT JOIN userpref ON userpref.userid='{$this->q_openid}' AND pref.prefid=userpref.prefid $noadmin");
	}

	function setUserPrefs($prefs) {
		$id = $this->q_openid;
		$this->DB->exec ("INSERT OR IGNORE INTO users (userid) VALUES ('$id')");
		foreach ($prefs as $p) {
			$this->DB->exec ("INSERT OR REPLACE INTO userpref (userid,prefid,value) VALUES ('".$this->q_openid."', '".SQLite3::escapeString($p["prefid"])."', '".SQLite3::escapeString($p["value"])."')");
		}
	}

	function setAutologin($wikiid, $mwusername) {
		$this->DB->exec ("UPDATE autologin SET lastlogintime=strftime('%s','now') WHERE wikiid=".($wikiid+0)." AND userid='".$this->q_openid."'");
		return $this->DB->changes() > 0;
	}

	function getUserByEmail($email) {		
		$email = SQLite3::escapeString (filter_var($email, FILTER_VALIDATE_EMAIL));
		return $this->querySingle("SELECT userid FROM users WHERE email='$email';" );
	}

	function getUsersByGroup($group) {
		$users =& $this->cacheRefDefault("getUsersByGroup:$group");
		if (!is_array($users)) {
			$q_group = SQLite3::escapeString ($group);			
			$users = $this->queryList("SELECT userid FROM usergroups WHERE groupname='{$q_group}' AND userid <> ' ' GROUP BY userid;");
		}
		return $users;
	}

	
	function getMWUsername() {
		$id = $this->q_openid;
		return $this->querySingle("SELECT mwusername FROM users WHERE userid='$id';" );
	}
		
	function setMWUsername($nickname) {
		if (strlen ($nickname = trim($nickname))) {
			$id = $this->q_openid;
			$this->DB->exec ("INSERT OR IGNORE INTO users (userid) VALUES ('$id')");
			$this->DB->exec ("UPDATE users SET mwusername='".SQLite3::escapeString ($nickname)."' WHERE userid='$id'");
			return $this->DB->changes() == 1;
		}
	}
	
	function selfActivate() {
		// Warning to caller: I assume you have a good reason to think you're allowed.
		$this->joinGroup('users');
		return $this->DB->changes();
	}

	function setGroups($groups) {
		$this->DB->exec ("DELETE FROM usergroups WHERE userid='{$this->q_openid}'");
		foreach ($groups as $g) {					
			if (!$this->is_a_group($g))				
				$this->newGroup($g);
			$this->joinGroup($g);
		}
		return true;
	}

	function setGroupMembership($group, $userid, $ismember, $isadmin=false) {
		$q_group = SQLite3::escapeString ($group);
		$q_userid = SQLite3::escapeString ($userid);
        if ($ismember) {
            $this->DB->exec ("INSERT OR IGNORE INTO usergroups (userid, groupname) VALUES ('{$q_userid}', '{$q_group}')");
            $isadmin = $isadmin ? 1 : 0;
            $this->DB->exec ("UPDATE usergroups SET isadmin={$isadmin} WHERE userid='{$q_userid}' AND groupname='{$q_group}'");
        }
        else
            $this->DB->exec ("DELETE FROM usergroups WHERE userid='{$q_userid}' AND groupname='{$q_group}'");
		return true;
	}
	
	function joinGroup($group) {
		$q_group = SQLite3::escapeString ($group);
		$this->DB->exec ("INSERT OR IGNORE INTO usergroups (userid, groupname) VALUES ('{$this->q_openid}', '{$q_group}')");
		return true;
	}
	
	// Add a blank "placeholder" userid to a group
	function newGroup($group) {
		$q_group = SQLite3::escapeString ($group);
		$this->DB->exec ("INSERT OR IGNORE INTO usergroups (userid, groupname) VALUES (' ', '{$q_group}')");
		return true;
	}
	
	// Re-sets all "placeholder" users created by newGroup, effectivly removing empty groups
	function removeEmptyGroups() {
		$this->DB->exec ("DELETE FROM usergroups WHERE userid=' '");
		foreach ($this->getAllGroups() as $g)
			$this->newGroup($g['groupid']);
		return true;
	}		

	function requestGroup($groups) {
		if (!is_array($groups))
			$groups = array($groups);

		$admin_requests_before = $this->getAdminRequests();

		$allgroups =& $this->getAllGroups();
		foreach (array_merge ($groups, array ("users")) as $group) {
			$found = false;
			foreach ($allgroups as &$realgroup)
				if ($realgroup["groupname"] == $group) {
					if ($realgroup["member"])
						;
					else if ($realgroup["requested"])
						;
					else {
						if ($this->DB->exec ("INSERT OR IGNORE INTO request (userid, groupname) VALUES ('".$this->q_openid."', '".SQLite3::escapeString($group)."')"))
							$realgroup["requested"] = true;
						else
							error_log ("requestGroup insert failed: ".$this->DB->lastErrorMsg());
					}
					$found = true;
					break;
				}
			if (!$found) 
				error_log ("requestGroup nonexistent group: $group");
		}

		if (count($admin_requests_before) == 0) {
			$admin_requests_after = $this->getAdminRequests();
			if (count($admin_requests_after) > 0) {
				$requests_text = "";
				foreach ($admin_requests_after as $r)
					$requests_text .= "\n* Join '{$r['groupname']}' group - {$r['realname']} <{$r['email']}>, {$r['userid']}\n";
				$requests_text = preg_replace ("{Join 'users' group}", "Activate account", $requests_text);
				$subject = "[Wikifarm] Requests need administrator approval";
				$message = <<<BLOCK
Hi,

This is the wikifarm at https://{$_SERVER['HTTP_HOST']} .

Administrator approval is needed for the following requests.
{$requests_text}
No more of these notifications will be sent until all outstanding requests are cleared.
BLOCK;
				foreach ($this->getAdminEmails() as $e) {
					$this->Mail ($e,
						     $subject,
						     wordwrap($message));
				}
			}
		}
	}

	function Mail ($to, $subject, $message) {
		$unsubscribe = "\n\n-- \n-- Sent to {$to} .\n-- To disable notifications: https://{$_SERVER['HTTP_HOST']}/?tabActive=myaccount\n";
		mail ($to, $subject, $message.$unsubscribe,
		      "From: <".$this->getAdminSenderAddress().">\r\n".
		      "Return-Path: <".$this->getAdminSenderAddress().">",
		      "-r".$this->getAdminSenderAddress());
	}

	function getAdminSenderAddress () {
		if (getenv ("WIKIFARM_ADMIN_EMAIL")) {
			preg_match ('{[^\s,]+}', getenv ("WIKIFARM_ADMIN_EMAIL"), $matches);
			return $matches[0];
		}
		return "postmaster@".trim(`hostname`);
	}

	function requestWiki ($wikiid, $mwusername=false) {
		$wikiid = $wikiid + 0;
		$owner_userid = $this->querySingle ("SELECT userid FROM wikis WHERE id=$wikiid");
		$user_requests_before = $this->getUserRequests ($owner_userid);

		// DELETE + INSERT instead of INSERT OR REPLACE to
		// ensure that a request with a different mwusername
		// gets a new requestid.  Otherwise the mwusername
		// could change after the wiki owner decides to accept
		// the requestid but before pressing the Approve
		// button.
		$this->DB->exec ("DELETE FROM request WHERE userid='".$this->q_openid."' AND wikiid='$wikiid'");
		$this->DB->exec ("INSERT INTO request (userid, wikiid, mwusername) VALUES ('".$this->q_openid."', '$wikiid', '".SQLite3::escapeString($mwusername)."')");

		if (count ($user_requests_before)) return true;
		$user_requests_after = $this->getUserRequests ($owner_userid);
		if (count ($user_requests_after) == 0) return true;

		$wf = new WikifarmDriver ($this->DB);
		$wf->Focus ($owner_userid);
		$want_email = false;
		foreach ($wf->getUserPrefs() as $p)
			if ($p["prefid"] == "notify_requests" && $p["value"])
				$want_email = true;
		if (!$want_email) return true;
		$e = $wf->getUserEmail();
		if (!$e) return true;

		$requestor = $this->getUserRealname() . " <" . $this->getUserEmail() . ">";
		$wiki = $this->getWiki($wikiid);

		$subject = "[Wikifarm] Requests need your approval";
		$message = <<<BLOCK
Hi,

This is the wikifarm at {$_SERVER['HTTP_HOST']}.

{$requestor} has requested access to your "{$wiki['realname']}" wiki.

Please visit https://{$_SERVER['HTTP_HOST']} to approve or reject the request.

No more of these notifications will be sent until all of your outstanding requests are cleared.
BLOCK;
		$this->Mail ($e, $subject, wordwrap($message));
		return true;
	}

	// Responding to requests
	function getAllRequests() {
		if (!array_key_exists ("getAllRequests", $this->_cache)) {
			$reqs = $this->getUserRequests ($this->openid);
			if (!$this->isAdmin()) {
				$this->_cache['getAllRequests'] = $reqs;
			} else {
				$group_reqs = $this->getAdminRequests();
				$this->_cache['getAllRequests'] = array_merge ($group_reqs, $reqs);
			}
		}
		return $this->_cache['getAllRequests'];
	}

	function getAdminRequests() {
		return $this->query ("SELECT request.*, email, realname FROM request LEFT JOIN users ON users.userid=request.userid WHERE wikiid IS NULL ORDER BY request.userid");
	}

	function getUserRequests($userid) {
		$q_openid = SQLite3::escapeString ($userid);
		return $this->query ("
SELECT request.*, wikis.realname wikititle, wikiname, users.realname, users.email
FROM request
LEFT JOIN wikis ON request.wikiid=wikis.id
LEFT JOIN users ON users.userid=request.userid
WHERE wikiid IN (SELECT id FROM wikis WHERE userid='$q_openid')");
	}

	// Am I allowed to approve or deny this request?  If not
	// allowed, return false.  If allowed, return assoc array with
	// the request details

	function canApproveRequest($requestid) {
		// $requestid itself must be reasonable
		if (!preg_match ('{^[0-9]+$}', $requestid)) return false;

		// there must be exactly one request with this id
		$req = $this->query ("select * from request where requestid=$requestid");
		if (count($req) != 1) {
			error_log ("canApproveRequest: count(req id $requestid)=".count($req));
			throw new Exception ("No such request.");
		}

		// admin can approve any request
		if ($this->isAdmin()) return $req[0];

		// non-admin can only approve a request concerning a wiki she actually owns
		if ($this->querySingle("select 1 from wikis where id=".$req[0]["wikiid"]." and userid='".$this->q_openid."'"))
			return $req[0];

		error_log ("canApproveRequest: user ".$this->openid." cannot do ".print_r($req[0],true));
		return false;
	}
	
	function approveRequestId($requestid, $initial_quota) {
		$req = $this->canApproveRequest ($requestid);
		if (!$req)
			throw new Exception ("You are not allowed to do that.");
		error_log ("approving request: ".print_r($req,true));
		if ($req["wikiid"]) {
			$who = $req["userid"];
			$this->DB->exec ("insert or replace into wikipermission (wikiid, userid_or_groupname) values ('".$req["wikiid"]."', '".SQLite3::escapeString($who)."')");
			if ($req["mwusername"])
				$this->DB->exec ("insert or ignore into autologin (wikiid, userid, mwusername, sysop) values ('".$req["wikiid"]."', '".SQLite3::escapeString($who)."', '".SQLite3::escapeString($req["mwusername"])."', 0)");
		}
		else if ($req["groupname"])
			$this->DB->exec ("insert or replace into usergroups (userid, groupname) values ('".SQLite3::escapeString($req["userid"])."', '".SQLite3::escapeString($req["groupname"])."')");
		else
			throw new Exception ("approveRequestId: unknown request type: ".print_r($req,true));

		if ($req["groupname"] == 'users' &&
		    preg_match ('{^\d+$}', $initial_quota)) {
			$this->Focus ($req["userid"]);
			$this->setWikiQuota ($initial_quota);
		}

		$this->DB->exec ("delete from request where requestid=$requestid");
		return true;
	}
	
	function rejectRequestId($requestid) {
		if (!$this->canApproveRequest ($requestid))
			throw new Exception ("You are not allowed to do that.");
		$this->DB->exec ("delete from request where requestid=$requestid");
		return $this->DB->changes() == 1;
	}

	// Invitations

	function claimInvite($code) {
		$q_openid = $this->q_openid;
		// TODO
	}

	function claimInvitationByPassword($username, $password)
	{
		$userid = $this->openid;
		$q_userid = SQLite3::escapeString ($userid);
		$q_old_username = SQLite3::escapeString ($username);
		$provided_password = str_replace ("\n", "", $password);

		$cryptpw = $this->querySingle ("select cryptpw from users where userid='$q_old_username'");
		putenv ("PW=$provided_password");
		putenv ("SALT=$cryptpw");
		$check = `perl -e 'use Apache::Htpasswd; \$h = new Apache::Htpasswd("/dev/null"); print \$h->CryptPasswd (\$ENV{PW}, \$ENV{SALT})'`;
		if (!$userid ||
		    strlen($cryptpw) < 6 ||
		    trim($check) != trim($cryptpw))
			throw new Exception ("Authentication failed: username or password incorrect.");

		$this->DB->exec ("update wikis set userid='$q_userid' where userid='$q_old_username'");
		$wikis_claimed = $this->DB->changes();

		$this->DB->exec ("update or ignore usergroups set userid='$q_userid' where userid='$q_old_username'");
		$groups_claimed = $this->DB->changes();

		$this->DB->exec ("update or ignore wikipermission set userid_or_groupname='$q_userid' where userid_or_groupname='$q_old_username'");
		$access_claimed = $this->DB->changes();

		$this->DB->exec ("INSERT OR IGNORE INTO users (userid, realname, email)
			SELECT '$q_userid',

			CASE WHEN realname IS NULL AND userid NOT LIKE '%@%' THEN userid
			ELSE realname END,

			CASE WHEN email IS NULL AND userid LIKE '%@%' THEN userid
			ELSE email END

			FROM users WHERE userid='$q_old_username'");
		return array ("wikis" => $wikis_claimed,
			      "groups" => $groups_claimed,
			      "access" => $access_claimed);
	}
	
	function createInvitation ($group, $wiki, $email) {
		// TODO: make this useful
		return md5(`head -c32 /dev/urandom`);
	}
	
	function inviteUser ($wikiid, $userid, $mwusername=false) {
		$q_userid = SQLite3::escapeString ($userid);
		if (!preg_match ('{^\d+$}', $wikiid)) {
			error_log ("inviteUser: invalid wikiid $wikiid");
			$this->_error = "No such wiki";
			return false;
		}
		if (!$this->isAdmin() &&
		    !$this->querySingle ("select 1 from wikis where id='$wikiid' and userid='".$this->q_openid."'")) {
			error_log ("inviteUser: wikiid $wikiid not owned by ".$this->openid);
			$this->_error = "Permission denied";
			return false;
		}
		if (!$this->DB->exec ("insert or ignore into wikipermission (wikiid, userid_or_groupname) values ($wikiid, '$q_userid')")) {
			error_log ("inviteUser: db error ".$this->DB->lastErrorMsg());
			$this->_error = "Database error";
			return false;
		}
		if ($mwusername) {
			$q_mwusername = SQLite3::escapeString ($mwusername);
			$this->DB->exec ("insert or ignore into autologin (wikiid, userid, mwusername, sysop) values ('$wikiid', '$q_userid', '$q_mwusername', 0)");
		}
		return true;
	}

	function disinviteUser ($wikiid, $userid) {
		$this->DB->exec ("DELETE FROM wikipermission WHERE wikiid=$wikiid AND userid_or_groupname='".SQLite3::escapeString($userid)."'");
	}

	function disinviteEditor ($wikiid, $userid) {
		$this->DB->exec ("DELETE FROM autologin WHERE wikiid=$wikiid AND userid='".SQLite3::escapeString($userid)."'");
	}

	function getAllActivatedUsers() {
		if (!$this->_security()) return false;
		return $this->query ("SELECT usergroups.userid userid, email, realname, mwusername FROM usergroups LEFT JOIN users ON users.userid = usergroups.userid WHERE usergroups.userid LIKE '%://%' GROUP BY usergroups.userid");
	}

	function getAllUnactivatedUsers() {
		if (!$this->_security('admin')) return false;
		return $this->query ("SELECT users.userid userid, email, realname, mwusername FROM users LEFT JOIN usergroups ON users.userid = usergroups.userid WHERE users.userid LIKE '%://%' AND usergroups.userid IS NULL");
	}

	function getUser ($userid) {		
		foreach ($this->getAllActivatedUsers() as $u)
			if ($u["userid"] == $userid)
				return $u;
		foreach ($this->getAllUnactivatedUsers() as $u)
			if ($u["userid"] == $userid) {
				if (!$this->_security ('admin')) return false;
				return $u;
			}
		return false;
	}
	
	function wikiFarmBackup($with_wikis) {
		if (!$this->_security('admin')) return false;
		$dbdir = getenv("WIKIFARM_DB");
		$etcdir = getenv("WIKIFARM_ETC");
		$wwwdir = getenv("WIKIFARM_WWW");
		$wikibasedir = "/home/wikifarm";
		$wikifiles = "$wwwdir/FarmSettings.php $wwwdir/DefaultFarmSettings.php $wwwdir/*/LocalSettings.php";
		if ($with_wikis)
			$wikifiles .= " $wwwdir/mediawiki/ /usr/share/mediawiki-extensions /etc/mediawiki-extensions $wwwdir/*/private/wikidb*.sql.gz $wwwdir/*/images";
		else
			$wikifiles .= " $wwwdir/mediawiki/extensions/ /etc/mediawiki-extensions";
		$stamp = preg_replace ('{^(.*?\..*?)\..*}', '${1}', $_SERVER['HTTP_HOST']);
		
		$stamp = $stamp."_".strftime("%Y-%m-%d", $with_wikis ? time() : filemtime("$dbdir/dump-wikis.db.sql")).($with_wikis ? "" : "-wikifarm-db-only");
		header ("Content-type: application/gzip-compressed");
		header ("Content-Disposition: attachment; filename=\"{$stamp}.tar.gz\"");
		passthru("cd /tmp && GZIP=--rsyncable tar --owner=root --group=root --transform 's:^:{$stamp}/:' -chzf - $dbdir/dump-wikis.db.sql $wikifiles");
		return true;
	}
	
	function wikiBackup($wiki) {
		if (!$this->_security( array( 'access' => 'owner', 'wiki' => $wiki, 'or_admin' => true ))) {
			header("location: /");
			return false;
		}
		if (($wiki+=0) == 0)
			return false;
		$wiki = sprintf ("%02d", $wiki);
		$wikidir = getenv("WIKIFARM_WWW")."/$wiki";
		$dbbackup = "private/wikidb{$wiki}.sql.gz";
		$stamp = preg_replace ('{^(.*?\..*?)\..*}', '${1}', $_SERVER['HTTP_HOST']);
		$stamp = $stamp."_".$wiki."_".strftime("%Y-%m-%d");
		if (!file_exists("$wikidir/$dbbackup"))
			$dbbackup = "";
		header ("Content-type: application/gzip-compressed");
		header ("Content-Disposition: attachment; filename=\"{$stamp}.tar.gz\"");
		passthru("GZIP=--rsyncable tar -C $wikidir --owner=root --group=root --transform 's:^:{$stamp}/:' -chzf - $dbbackup images");
		return true;
	}

}  // WikifarmDriver class ends


?>
