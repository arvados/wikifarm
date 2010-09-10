<?php
;

class WikifarmAuthPlugin extends AuthPlugin {

	var $db;
	var $userid;
	var $userid_is_owner;
	var $userid_is_admin;
	var $userrow;
	var $mwusername;

	function __construct () {
		$this->db = new SQLite3 (getenv("WIKIFARM_DB_FILE"));
		$this->userid = $_SERVER["REMOTE_USER"];
		$autologin = $this->db->querySingle
			("select mwusername, sysop from autologin where wikiid='"
			 . getenv("WIKIID")
			 . "' and userid='"
			 . SQLite3::escapeString ($this->userid)
			 . "' order by lastlogintime desc limit 1", true);
		if ($autologin) {
			$this->mwusername = $autologin["mwusername"];
			$this->userid_is_owner = $autologin["sysop"];
		}

		$this->userrow = $this->db->querySingle
			("select * from users where userid='"
			 . SQLite3::escapeString ($this->userid) . "'",
			 true);
		if (!$this->userid_is_owner)
			$this->userid_is_owner = $this->db->querySingle
				("select 1 from wikis where id='"
				 . getenv("WIKIID") . "' and userid='"
				 . SQLite3::escapeString ($this->userid) . "'");
		$this->userid_is_admin = $this->db->querySingle
			("select 1 from usergroups where userid='"
			 . SQLite3::escapeString ($this->userid) . "' and groupname='ADMIN'");
	}

	public function userExists( $username ) {
		return $db->querySingle ("SELECT count(*) from wikis where userid='"
					 . SQLite3::escapeString ($username)
					 . "'");
	}

	public function authenticate( $username, $password ) {
		return isset ($this->mwusername) &&
			$this->mwusername !== false &&
			$this->mwusername == $username;
	}

	public function modifyUITemplate( &$template ) {
		$template->set( 'usedomain', false );
	}

	public function setDomain( $domain ) {
		$this->domain = $domain;
	}

	public function validDomain( $domain ) {
		return true;
	}

	public function updateUser( &$user ) {
		if (!$this->userrow) return true;
		if ($this->userrow["email"])
			$user->mEmail = $this->userrow["email"];
		if ($this->userrow["realname"])
			$user->mRealName = $this->userrow["realname"];
		return true;
	}

	public function autoCreate() {
		return true;
	}

	public function allowPasswordChange() {
		return false;
	}

	public function setPassword( $user, $password ) {
		return true;
	}

	public function updateExternalDB( $user ) {
		return true;
	}

	public function canCreateAccounts() {
		return false;
	}

	public function addUser( $user, $password, $email='', $realname='' ) {
		return false;
	}

	public function strict() {
		return false;	// allow native logins as well
	}

	public function strictUserAuth( $username ) {
		return false;
	}

	public function initUser( &$user, $autocreate=false ) {
		if ($this->userrow["email"])
			$user->mEmail = $this->userrow["email"];
		if ($this->userrow["realname"])
			$user->mRealName = $this->userrow["realname"];
	}

	public function getCanonicalName( $username ) {
		return $username;
	}
	
	public function getUserInstance( User &$user ) {
		return new AuthPluginUser( $user );
	}

	function autoAuthenticate ($user) {
		if (!isset($this->mwusername))
			return true;

		$freshlogin = false;

		global	$wgCommandLineMode;
		if( !$wgCommandLineMode && (!$_SESSION ||
					    ($_SESSION["wsUserName"] != "" &&
					     $_SESSION["wsUserName"] != $this->mwusername))) {
			$_SESSION = array();
			global $wgCookiePrefix;
			foreach ($_COOKIE as $k => $v) {
				if (strncmp ($k, $wgCookiePrefix, strlen($wgCookiePrefix)) == 0)
					unset ($_COOKIE[$k]);
			}
			User::SetupSession();
			$freshlogin = true;
		}
		$user = User::newFromName($this->mwusername);
		if($user->getID() == 0) {
			$user->addToDatabase();
			$user->setToken();
			$this->initUser($user);
		} else {
			$user->loadFromDatabase();
		}

		$user->setCookies();
		$user->saveSettings();

		if ($freshlogin)
			$this->updateLastLoginTime();

		return true;
	}

	// UserLoginComplete: when a user logs in using MediaWiki's
	// built-in username/password system, add a corresponding row
	// to the autologin table.

	public function UserLoginComplete ( $user ) {
		if (!$this->mwusername) {
			$this->mwusername = $user->getName();
			$this->db->query ("insert into autologin "
					  . "(wikiid, userid, mwusername, sysop) values ('"
					  . getenv("WIKIID") . "', '"
					  . SQLite3::escapeString ($this->userid) . "', '"
					  . SQLite3::escapeString ($this->mwusername) . "', '"
					  . (array_search ("sysop", $user->getGroups()) !== false ? 1 : 0) . "')");
		}
		if (!$this->userrow)
			$this->db->query ("insert into users (userid, email, realname) values ('"
					  . SQLite3::escapeString ($this->userid) . "', '"
					  . SQLite3::escapeString ($user->mEmail) . "', '"
					  . SQLite3::escapeString ($user->mRealName) . "')");
		$this->updateLastLoginTime();
		return true;
	}

	// UserGetRights: add sysop rights if this OpenID owns the
	// wiki or is a site admin

	public function UserGetRights ( $user, &$rights ) {
		if ($this->userid_is_owner || $this->userid_is_admin)
			$rights = array_merge ( $rights, User::getGroupPermissions (array ("sysop")) );
		return true;
	}

	// updateLastLoginTime: remember the last login time so we can
	// log in to the same MW user account next time (in case the
	// OpenID has access to multiple MW user accounts in this
	// wiki)

	function updateLastLoginTime() {
		$this->db->query("update autologin set lastlogintime=strftime('%s','now') where wikiid='"
				 . getenv("WIKIID") . "' and userid='"
				 . $this->userid . "' and mwusername='"
				 . $this->mwusername . "'");
	}
}

if (getenv("WIKIID") && getenv("REMOTE_USER") != "") {
	session_start();
	$wgAuth = new WikifarmAuthPlugin();
	$wgHooks['UserLoadFromSession'][] = array ($wgAuth, 'autoAuthenticate');
	$wgHooks['UserLoginComplete'][] = array ($wgAuth, 'UserLoginComplete');
	$wgHooks['UserGetRights'][] = array ($wgAuth, 'UserGetRights');
	global $wgDisableCookieCheck;
	$wgDisableCookieCheck = true;
}
