<?php ; // -*- mode: php; indent-tabs-mode: nil; -*-

// License: GPLv2
// Copyright: Curoverse
// Authors: see git-blame

@include(getenv('WIKIFARM_ETC').'/config.php');
require_once('WikifarmAuth.php');
require_once('jwt_helper.php');

function verify_token() {
    global $wikifarmConfig;
    $oauth2_code = $_GET['code'];
    $id_token = $_GET['id_token'];
    $discovery = json_decode(file_get_contents('https://accounts.google.com/.well-known/openid-configuration'));
    $ctx = stream_context_create(array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query(array(
                'client_id' => $wikifarmConfig['oauth2_google_client_id'],
                'client_secret' => $wikifarmConfig['oauth2_google_client_secret'],
                'code' => $oauth2_code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $wikifarmConfig['uri_scheme'] . '://' . $wikifarmConfig['servername'] . '/login2.php',
                'openid.realm' => $wikifarmConfig['uri_scheme'] . '://' . $wikifarmConfig['servername'] . '/',
            )),
        ),
    ));
    $resp = file_get_contents($discovery->token_endpoint, false, $ctx);
    if (!$resp) {
        error_log(json_encode($http_response_header));
        error_out('Error verifying token: ' . $http_response_header[0]);
    }
    $resp = json_decode($resp);
    $access_token = $resp->access_token;
    $id_token = $resp->id_token;
    $id_payload = JWT::decode($resp->id_token, null, false);
    if (!$id_payload->sub) {
        error_log(json_encode($id_payload));
        error_out('No subscriber ID provided in ID token! See error log for details.');
    }
    $subscriber_id = 'oauth2://google/' . $id_payload->sub;

    // If $openid_id is in the wikifarm db, migrate it to $subscriber_id
    $openid_id = $id_payload->openid_id;
    if ($openid_id) {
        migrateUserId($openid_id, $subscriber_id);
    }

    setUserId($subscriber_id, $id_payload->email);
    header('Location: /');
}

function error_out($err) {
    header('Location: /login.php?modauthopenid_error='.htmlspecialchars($err));
    exit;
}

verify_token();
?>
