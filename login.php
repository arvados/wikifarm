<html><head>
<? @include(getenv('WIKIFARM_ETC').'/config.php'); ?>
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

<body>
<div style="margin: 30px; width: 50%">
<h1><?= @$wikifarmConfig["servertitle"] ? $wikifarmConfig["servertitle"] : $_SERVER['HTTP_HOST']?></h1>
<p>This site is protected and requires that you identify yourself with an <a href="http://openid.net">OpenID</a> login.</p>

<? if(isset($_GET["modauthopenid_error"])) { ?>
<div style="background: #fdd; border: 1px dashed #b00">Login failed (error code: <?=$_GET["modauthopenid_error"]?>)</div>
<? } ?>

<form style="display:inline" action="/" method="get" class="openidloginform">
<b class="marg">Log in via:</b>
<input type="hidden" name="openid_identifier" value="https://www.google.com/accounts/o8/id" />
<input class="marg" type="submit" value="Google" />
<input type="hidden" name="modauthopenid_referrer" value="<?=htmlspecialchars($_GET["modauthopenid_referrer"])?>" />
</form>

<form style="display:inline" action="/" method="get" class="openidloginform">
<input type="hidden" name="openid_identifier" value="http://open.login.yahooapis.com/openid20/www.yahoo.com/xrds" />
<input class="marg" type="submit" value="Yahoo" />
<input type="hidden" name="modauthopenid_referrer" value="<?=htmlspecialchars($_GET["modauthopenid_referrer"])?>" />
</form>

<p><b>or</b></p>

<form class="marg" action="/" method="get" class="openidloginform">
<b>URL:</b> <input type="text" name="openid_identifier" value="" size="30" class="loginbox" />
<input type="submit" value="Log In" />
<input type="hidden" name="modauthopenid_referrer" value="<?=htmlspecialchars($_GET["modauthopenid_referrer"])?>" />
</form>

<p>If you do not have an account, you may request one after logging in.</p>

</div>
<body>
</html>
