<html><head>
<title>Protected Location</title>
<style type="text/css"><!--
#msg { border: 1px solid #ff0000; background: #ffaaaa; font-weight: bold; padding: 10px; }
a { text-decoration: none; }
a:hover { text-decoration: underline; }
#sig { text-align: center; font-style: italic; margin-top: 50px; color: #777; font-size: .7em; }
#sig a { color: #222; }
.loginbox { background: url(http://www.openid.net/login-bg.gif) no-repeat; background-color: #fff;  background-position: 0 50%; color: #000; padding-left: 18px; }
.marg { margin: 15px 15px 0 15px; }
// -->
</style></head>

<body>
<div style="margin: 30px; width: 50%">
<h1>Protected Location</h1>
<p>This site is protected and requires that you identify yourself with an <a href="http://openid.net">OpenID</a> url.</p>

<? if(isset($_GET["modauthopenid_error"])) { ?>
<div style="background: #fdd; border: 1px dashed #b00">Login failed (error code: <?=$_GET["modauthopenid_error"]?>)</div>
<? } ?>

<form class="marg" action="/" method="get">
<b>Identity URL:</b> <input type="text" name="openid_identifier" value="" size="30" class="loginbox" />
<input type="submit" value="Log In" />
<input type="hidden" name="modauthopenid.referrer" value="<?=htmlspecialchars($_GET["modauthopenid_referrer"])?>" />
</form>

<form style="display:inline" action="/" method="get">
<b class="marg">Shortcut:</b>
<input type="hidden" name="openid_identifier" value="https://www.google.com/accounts/o8/id" />
<input class="marg" type="submit" value="Google Login" />
<input type="hidden" name="modauthopenid.referrer" value="<?=htmlspecialchars($_GET["modauthopenid_referrer"])?>" />
</form>

<form style="display:inline" action="/" method="get">
<input type="hidden" name="openid_identifier" value="http://open.login.yahooapis.com/openid20/www.yahoo.com/xrds" />
<input class="marg" type="submit" value="Yahoo Login" />
<input type="hidden" name="modauthopenid.referrer" value="<?=htmlspecialchars($_GET["modauthopenid_referrer"])?>" />
</form>

<p>If you are surprised to see this because your browser has always just asked for username and password here, fear not.  After you log in with your OpenID for the first time, you can claim your lab notebook (and view other lab members&rsquo; lab notebooks) by entering your old username and password.  After that, you&rsquo;ll just need OpenID.</p>

<p>To find out how OpenID works, see <a href="http://openid.net/what/">http://openid.net/what/</a>.  You can sign up for an identity on one of <a href="http://openid.net/get/">these sites</a>.</p>

</div>
<body>
</html>
