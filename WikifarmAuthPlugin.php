<?php
;

class WikifarmAuthPlugin extends AuthPlugin {

	var $db;
	var $userid;

	function __construct () {
		$this->db = new SQLite3 (getenv("WIKIFARM_DB_FILE"));
		$this->userid = $_SERVER["REMOTE_USER"];
		$this->mwusername = $this->db->querySingle
			("select mwusername from autologin where wikiid='"
			 . getenv("WIKIID")
			 . "' and userid='"
			 . SQLite3::escapeString ($this->userid)
			 . "' order by lastlogintime desc limit 1");
		$this->userrow = $this->db->querySingle
			("select * from users where userid='"
			 . SQLite3::escapeString ($this->userid) . "'",
			 TRUE);
	}

	public function userExists( $username ) {
		return $db->querySingle ("SELECT count(*) from wikis where userid='"
					 . SQLite3::escapeString ($username)
					 . "'");
	}

	public function authenticate( $username, $password ) {
		return $this->mwusername !== false && $this->mwusername == $username;
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
		error_log ("updateUser");
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
			return false;

		global	$wgCommandLineMode;
		if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
			User::SetupSession();
		}
		if ( method_exists($user, 'load' ) ) {
			$user->load();
		} else {
			User::loadFromSession();
		}
		if($user->mId != 0) {
			return 0;
		}

		$user = User::newFromName($this->mwusername);
		if($user->getID() == 0) {
			$user->addToDatabase();
			$user->setPassword(User::randomPassword());
			$user->setToken();
			$this->initUser($user);
		} else {
			$user->loadFromDatabase();
		}
		$user->setCookies();
		$user->saveSettings();
		$this->updateLastLoginTime();
		return true;
	}

	public function addAutoLogin ( $user ) {
		if (!$this->mwusername) {
			$this->db->query ("insert into autologin "
					  . "(wikiid, userid, mwusername) values ('"
					  . getenv("WIKIID") . "', '"
					  . SQLite3::escapeString ($this->userid) . "', '"
					  . SQLite3::escapeString ($user->getName()) . "')");
			$this->mwusername = $user->getName();
		}
		if (!$this->userrow)
			$this->db->query ("insert into users (userid, email, realname) values ('"
					  . SQLite3::escapeString ($this->userid) . "', '"
					  . SQLite3::escapeString ($user->mEmail) . "', '"
					  . SQLite3::escapeString ($user->mRealName) . "')");
		$this->updateLastLoginTime();
		return true;
	}

	function updateLastLoginTime() {
		$this->db->query("update autologin set lastlogintime=strftime('%s','now') where wikiid='"
				 . getenv("WIKIID") . "' and userid='"
				 . $this->userid . "' and mwusername='"
				 . $this->mwusername . "'");
	}
}

session_start();
$wgAuth = new WikifarmAuthPlugin();
$wgHooks['UserLoadFromSession'][] = array ($wgAuth, 'autoAuthenticate');
$wgHooks['UserLoginComplete'][] = array ($wgAuth, 'addAutoLogin');
