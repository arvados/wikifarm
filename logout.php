<?
$db = new SQLite3 (getenv("OPENID_DB_FILE"));
$q_session_id = SQLite3::escapeString ($_COOKIE["open_id_session_id"]);
$db->exec ("delete from sessionmanager where session_id='$q_session_id'");
setcookie ("open_id_session_id", "", 0, "/");
header ("Location: /");
?>
