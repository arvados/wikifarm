<?php ; // -*- mode: java; c-basic-offset: 4; tab-width: 4; indent-tabs-mode: nil; -*-

// Copyright 2010 President and Fellows of Harvard College
//
// Authors:
// Tom Clegg <tom@curii.com>
// Jer Ratcliffe
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

namespace MediaWiki\Extension\WikifarmAuthPlugin;

class Hooks implements \MediaWiki\Permissions\Hook\UserGetRightsHook {

  var $db;
  var $userid;
  var $userid_is_owner;
  var $userid_is_admin;
  var $userrow;
  var $mwusername;

  function __construct () {
    $this->db = new \SQLite3 (getenv("WIKIFARM_DB_FILE"));
    $this->userid = $_SERVER["REMOTE_USER"];
    $autologin = $this->db->querySingle
      ("select mwusername, sysop from autologin where wikiid="
       . getenv("WIKIID") * 1
       . " and userid='"
       . \SQLite3::escapeString ($this->userid)
       . "' order by lastlogintime desc limit 1", true);
    if ($autologin) {
      $this->mwusername = preg_replace('{_}',' ',$autologin["mwusername"]);
      $this->userid_is_owner = $autologin["sysop"];
    } else {
      // Try login via group
      $this->userid_is_owner = false;
      $autologin = $this->db->querySingle
                ($sql = "select mwusername, 0 as sysop from users "
                 . "left join usergroups on usergroups.userid=users.userid "
                 . "left join wikipermission on wikiid="
                 . getenv("WIKIID") * 1
                 . " and groupname=userid_or_groupname and readonly=0 "
                 . "where users.userid='"
                 . \SQLite3::escapeString ($this->userid)
                 . "' and wikipermission.readonly is not null", true);

      // Check whether this MW username is already used in this
      // wiki by a different user
      if ($autologin) {
        $conflict = $this->db->querySingle
                    ("select * from autologin where wikiid="
                     . getenv("WIKIID") * 1
                     . " and lower(mwusername)=lower('"
                     . \SQLite3::escapeString ($autologin["mwusername"])
                     . "') and userid<>'"
                     . \SQLite3::escapeString ($this->userid)
                     . "'");
        if ($conflict)
          $autologin = false; // Must be resolved via dashboard
      }

      if ($autologin) {
        $this->mwusername = preg_replace('{_}',' ',$autologin["mwusername"]);
        $this->db->query
                    ("insert into autologin "
                     . "(wikiid, userid, mwusername, sysop) values ("
                     . getenv("WIKIID") * 1 . ", '"
                     . \SQLite3::escapeString ($this->userid) . "', '"
                     . \SQLite3::escapeString ($this->mwusername) . "', '0')");
      }
    }

    $this->userrow = $this->db->querySingle
      ("select * from users where userid='"
       . \SQLite3::escapeString ($this->userid) . "'",
       true);
    if (!$this->userid_is_owner)
      $this->userid_is_owner = $this->db->querySingle
        ("select 1 from wikis where id='"
         . getenv("WIKIID") . "' and userid='"
         . \SQLite3::escapeString ($this->userid) . "'");
    $this->userid_is_admin = $this->db->querySingle
      ("select 1 from usergroups where userid='"
      . \SQLite3::escapeString ($this->userid) . "' and groupname='ADMIN'");

    $this->setWikiTitle();
  }

  /**
   * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGetRights
   * @param \User $user
   * @param \array &$rights
   */
  // UserGetRights: add sysop rights if this OpenID owns the
  // wiki or is a site admin
  public function onUserGetRights ( $user, &$rights ) {
    if ($this->mwusername && ($this->userid_is_owner || $this->userid_is_admin))
      $rights = array_merge ( $rights, \User::getGroupPermissions (array ("sysop", "user")) );
  }

  // onUserLoginComplete: when a user logs in using MediaWiki's
  // built-in username/password system, add a corresponding row
  // to the autologin table.
  public function onUserLoginComplete ( $user ) {
    if (!$this->mwusername) {
      $this->mwusername = $user->getName();
      $this->db->query ("insert into autologin "
            . "(wikiid, userid, mwusername, sysop) values ("
            . getenv("WIKIID") * 1 . ", '"
            . \SQLite3::escapeString ($this->userid) . "', '"
            . \SQLite3::escapeString ($this->mwusername) . "', '"
            . (array_search ("sysop", $user->getGroups()) !== false ? 1 : 0) . "')");
    }
    if (!$this->userrow)
      $this->db->query ("insert into users (userid, email, realname) values ('"
            . \SQLite3::escapeString ($this->userid) . "', '"
            . \SQLite3::escapeString ($user->mEmail) . "', '"
            . \SQLite3::escapeString ($user->mRealName) . "')");
    $this->updateLastLoginTime();
  }

  function onUserLogout() {
    header ("Location: /");
    exit;
  }

  // updateLastLoginTime: remember the last login time so we can
  // log in to the same MW user account next time (in case the
  // OpenID has access to multiple MW user accounts in this
  // wiki)
  function updateLastLoginTime() {
    $this->db->query("update autologin set lastlogintime=strftime('%s','now') where wikiid="
         . getenv("WIKIID") * 1 . " and userid='"
         . $this->userid . "' and mwusername='"
         . $this->mwusername . "'");
  }

  function setWikiTitle() {
    global $wgSitename;
    $title = $this->db->querySingle ("select realname from wikis where id='".getenv("WIKIID")."'");
    if ($title)
      $wgSitename = $title;
  }

}
