<html><head>
<?php @include(getenv('WIKIFARM_ETC').'/config.php'); ?>
<title><?=$_SERVER['HTTP_HOST']?></title>
<style type="text/css"><!--
#msg { border: 1px solid #ff0000; background: #ffaaaa; font-weight: bold; padding: 10px; }
a { text-decoration: none; }
a:hover { text-decoration: underline; }
#sig { text-align: center; font-style: italic; margin-top: 50px; color: #777; font-size: .7em; }
#sig a { color: #222; }
.loginbox { background: url(login-bg.gif) no-repeat; background-color: #fff;  background-position: 0 50%; color: #000; padding-left: 18px; }
.marg { margin: 15px 15px 0 15px; }
// -->
</style></head>

<?php
if ($_GET["modauthopenid_referrer"]) {
  $referrer = $_GET["modauthopenid_referrer"];
  if (preg_match('{^https://}', $_SERVER['SCRIPT_URI']) &&
      preg_match('{^http://}', $referrer)) {
    $referrer = preg_replace('{http://}', 'https://', $referrer);
  }
}
else {
  $referrer = preg_replace('{(//.*?/).*}', '$1', $_SERVER['SCRIPT_URI']);
}
if (@$wikifarmConfig["uri_scheme"]) {
  $referrer = preg_replace('{^[a-z]+://}',
                           $wikifarmConfig["uri_scheme"] . '://',
                           $referrer);
}
?>

<body>
<div style="margin: 30px; width: 50%">
<h1><?= @$wikifarmConfig["servertitle"] ? $wikifarmConfig["servertitle"] : $_SERVER['HTTP_HOST']?></h1>
<p>Please identify yourself using a Google or <a href="http://openid.net">OpenID</a> account.</p>

<?php if(isset($_GET["modauthopenid_error"])) { ?>
<div style="background: #fdd; border: 1px dashed #b00">Login failed (error code: <?=$_GET["modauthopenid_error"]?>)</div>
<?php } ?>

<p><b>Log in via:</b></p>

<?php if ($wikifarmConfig['oauth2_google_client_id']) { ?>
<form style="display:inline" action="https://accounts.google.com/o/oauth2/auth" method="get" class="openidloginform">
<input type="hidden" name="response_type" value="code" />
<input type="hidden" name="client_id" value="<?= htmlspecialchars($wikifarmConfig['oauth2_google_client_id']) ?>" />
<input type="hidden" name="redirect_uri" value="<?= htmlspecialchars($wikifarmConfig["uri_scheme"] . '://' . $wikifarmConfig["servername"] . '/login2.php') ?>" />
<input type="hidden" name="state" value="<?=htmlspecialchars($referrer)?>" />
<input type="hidden" name="scope" value="email openid profile" />
<input type="hidden" name="access_type" value="online" />
<input type="hidden" name="approval_prompt" value="auto" />
<input type="hidden" name="openid.realm" value="<?= htmlspecialchars($wikifarmConfig["uri_scheme"] . '://' . $wikifarmConfig["servername"] . '/') ?>" />
<input class="marg" type="submit" value="Google" />
</form>
<?php } ?>

<?php if (!$wikifarmConfig['oauth2_google_client_id'] || @$wikifarmConfig['openid2_google']) { /* default=disabled */ ?>
<form style="display:inline" action="/" method="get" class="openidloginform">
<input type="hidden" name="openid_identifier" value="https://www.google.com/accounts/o8/id" />
<input class="marg" type="submit" value="Google OpenID" />
<input type="hidden" name="modauthopenid_referrer" value="<?=htmlspecialchars($referrer)?>" />
</form>
<?php } ?>

<?php if (@$wikifarmConfig['openid2_yahoo'] !== false) { ?>
<form style="display:inline" action="/" method="get" class="openidloginform">
<input type="hidden" name="openid_identifier" value="http://open.login.yahooapis.com/openid20/www.yahoo.com/xrds" />
<input class="marg" type="submit" value="Yahoo" />
<input type="hidden" name="modauthopenid_referrer" value="<?=htmlspecialchars($referrer)?>" />
</form>
<?php } ?>

<?php if (@$wikifarmConfig['openid2_custom'] !== false) { ?>
<p><b>or</b></p>

<form class="marg" action="/" method="get" class="openidloginform">
<b>URL:</b> <input type="text" name="openid_identifier" value="" size="30" class="loginbox" />
<input type="submit" value="Log In" />
<input type="hidden" name="modauthopenid_referrer" value="<?=htmlspecialchars($referrer)?>" />
</form>
<?php } ?>

<p><?= @$wikifarmConfig['login_motd_html'] ?></p>

</div>
<body>
</html>
