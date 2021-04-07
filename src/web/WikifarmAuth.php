<?php ; // -*- mode: php; indent-tabs-mode: nil; -*-

// License: GPLv2
// Copyright: Curoverse
// Authors: see git-blame

require_once('WikifarmDriver.php');

function setUserId($uid, $username="") {
    global $wikifarmConfig;
    $site_secret = file_get_contents(getenv('WIKIFARM_ETC').'/site_secret');
    setcookie('wikifarm_sig_ts', $_COOKIE['wikifarm_sig_ts'] = $ts = ''.time());
    setcookie('wikifarm_user_id', $_COOKIE['wikifarm_user_id'] = $uid);
    setcookie('wikifarm_sig', $_COOKIE['wikifarm_sig'] = hash_hmac('sha256', $uid.$ts, $site_secret));
    if ($dbfile = $_SERVER['OPENID_DB_FILE']) {
        // Make mod_auth_openid recognize this signature as a session ID.
        $session_id = substr($_COOKIE['wikifarm_sig'], 0, 32);
        setcookie('open_id_session_id', $session_id);
	$db = new SQLite3($dbfile);
	if(!$db){
          error_log("Error opening database file $dbfile: ",$db->lastErrorMsg());
	}
	$db->exec("CREATE TABLE if not exists sessionmanager (session_id VARCHAR(33), hostname VARCHAR(255), path VARCHAR(255), identity VARCHAR(255), username VARCHAR(255), expires_on INT)");
	$rv = $db->exec("insert into sessionmanager (identity,session_id,expires_on,hostname,path) values ('".SQLite3::escapeString($uid)."','".SQLite3::escapeString($session_id)."',".(time()+86400*7).",'".SQLite3::escapeString($wikifarmConfig['servername'])."','/')");
	if(!$rv) {
          error_log("ERROR inserting session: ", $db->lastErrorMsg());
	}
    }
}

function getUserId() {
    global $wikifarmConfig, $currentUserId;
    if (isset($currentUserId)) {
        return $currentUserId;
    }
    if (isset($_COOKIE['wikifarm_user_id']) &&
        isset($_COOKIE['wikifarm_sig_ts']) &&
        ($_COOKIE['wikifarm_sig_ts'] > time() - 86400*7) &&
        isset($_COOKIE['wikifarm_sig']) &&
        ($site_secret = file_get_contents(getenv('WIKIFARM_ETC').'/site_secret')) &&
        $_COOKIE['wikifarm_sig']
        == hash_hmac('sha256',
                     $_COOKIE['wikifarm_user_id'].$_COOKIE['wikifarm_sig_ts'],
                     $site_secret)) {
        if ($_COOKIE['wikifarm_sig_ts'] < time() - 86400) {
            setUserId($_COOKIE['wikifarm_user_id']);
        }
        return ($currentUserId = $_COOKIE['wikifarm_user_id']);
    }
    if ($_SERVER["REMOTE_USER"]) {
        return ($currentUserId = $_SERVER["REMOTE_USER"]);
    }
    return ($currentUserId = false);
}

function migrateUserId($old_id, $new_id) {
    $farm = new WikifarmDriver();
    $farm->migrateUser($old_id, $new_id);
}

?>
