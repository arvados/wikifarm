<?php	

require_once ('WikifarmDriver.php');

class WikifarmPageMachine extends WikifarmDriver {
	public $tabNames, $js_tabNames;

	function __construct($db = null) {
		WikifarmDriver::__construct($db);
	}

	function page_debug() {
		$output = "";
		$output .= <<<BLOCK
<h3>ajax tests</h3>
<FORM id="fooform"><INPUT type="text" name="sample_id" value="sample" /></FORM>
<P>test_success: <button class="generic_ajax" ga_form_id="fooform" ga_message_id="foomessage" ga_action="test_success">Test success</button></P>
<P>test_failure: <button class="generic_ajax" ga_form_id="fooform" ga_message_id="foomessage" ga_action="test_failure">Test failure</button></P>
<P>test_alert: <button class="generic_ajax" ga_form_id="fooform" ga_message_id="foomessage" ga_action="test_alert">Test alert</button></P>
<P>test_alert_redirect: <button class="generic_ajax" ga_form_id="fooform" ga_message_id="foomessage" ga_action="test_alert_redirect">Test alert-and-redirect</button></P>
<P>test_activated: <button class="generic_ajax" ga_message_id="foomessage" ga_action="test_activated">Make sure my account is activated</button></P>
<P id="foomessage"></P>
<h3>current sqlite schema</h3>
<pre>
BLOCK;
		$result = $this->query( "SELECT sql FROM sqlite_master" );
		foreach ($result as $row) { 
			$output .= htmlspecialchars($row['sql']) . "\n\n";
		}
		$output .= "</pre>";
		return $output;
	}

	// all about the tabs	

	function tabGet($tab) {
		if (!method_exists ($this, "page_$tab"))
			return __METHOD__.": Invalid page request: \"$tab\"";
		return call_user_func (array ($this, "page_$tab"));
	}

	// activating invites based on user/password or an invite code, requesting access or additional access
	function page_getaccess() {
		$openid = $this->openid;
		$requestcount = 0;
		$username = null;
		if ($this->isActivated()) {
			$username = $this->getUserRealname();
			$wikinick = $this->getMWUsername();
		}
		//hack
		$grouplist = array( 'group' => array('group1','group2','group3','group4','group5'),
			'pending_since' => array( time()-1000, time()-4000, "june 23, 2010", null, null),
			'is_a_member' => array (false, false, false, false, true) );		
		
		$output = <<<BLOCK
<table width=100%><tr><td>
Already have an invite code or a pre-OpenID username and password?<br><br>
<blockquote>
<form action="index.php" method="post">
Username: <input type=text name=username size=16>
<br />Password: <input type=password name=password size=16>

<blockquote>Or</blockquote>

Invite Code: <input type=text name=invite size=16>
<br /><input type=submit value="Get Access">
</form>

</blockquote>
After you do this, your wiki and group memberships will be
attached to the OpenID you are currently logged in as ($openid).

</td><td class=vertbreak>|</td><td>

Request access to stuff (approval required, we will let you know)
<blockquote>
<form action="index.php" method="post">
BLOCK;
		if ($username) {
			$output .= "You are signed in as: <b>$username</b><br>";
		} else {
			$output .= "Your Name: <input type=text name=realname size=16> Email Adress: <input type=text name=email size=16>";
		}
		$output .= "Groups you wish to request membership to:<br>
<table><tr><td>group name</td><td>membership status</td></tr>";
		foreach ($grouplist['group'] as $i => $group) {
			$requestcount++;
			$output .= "\n<tr><td>$group</td><td>";
			if ($grouplist['pending_since'][$i]) {
				$output .= "Request pending since " . PMRelativeTime($grouplist['pending_since'][$i]);
			} elseif ($grouplist['is_a_member'][$i]) {
				$output .= "You are a member";
			} else {
				$output .= "<input type=\"checkbox\" name=\"request$requestcount\" value=\"$group\" /> Request membership";
			}
			$output .= "</td></tr>";
		}
		$output .= "</table><input type=submit value=\"Send Request\"></form>\n</blockquote>";
		return $output;
	}

	function page_myaccount() {
		return $this->uglydumpling (array ("userid" => $this->openid,
						   "email" => $this->getUserEmail(),
						   "realname" => $this->getUserRealname(),
						   "mwusername" => $this->getMWUsername(),
						   "prefs" => $this->getUserPrefs()));
	}


	function page_wikis() {
		if (!$this->isActivated()) {			
			error_log ("page_wikis: requested by unactivated user");
			return page_wikis_unactivated();
		}
		$wikiArray = $this->getAllWikis();
/* --- Javascript and CSS --- */		
		$output = "<script type='text/javascript'>\n\t$(function() {\n".
				"\t\t$('.controls a').button();\n".
				"\t\t$('#viewallradio').buttonset();\n".
				"\t\t$('#viewallradio input').change( function(){ if ($('#viewallyes').attr('checked')) { $('.nonreadable').show(); } else { $('.nonreadable').hide(); } });\n" .
				"\t\t$('.requestedbutton').click(function(){ $('#tabs').tabs('select', 0); });\n".
				"\t\t$('.linkbutton').click(function(){ var url = $(this).attr('link'); $(location).attr('href',url); })\n".
			"\t});\n</script>\n<style type=\"text/css\">\n" .
				"#allwikis td { padding-right: 10px; }\n".
				"#allwikis td.wikiid { width: 25px; text-align: right; padding-right: 10px; }\n".
				"#allwikis td.controls { padding-right: 0px; }\n".
				".controls a { padding: 0px; margin: 0px; }\n".
			"</style>\n";
			//$output .= $this->textRequestAccess();
/* --- Page Heading --- */		
		$output .= "<h2>All Wikis</h2>\n".			
			//"<form>\n".
			"<div align=right id='viewallradio'>\n".
				"\t<input type='radio' id='viewallyes' name='viewallradio' checked='checked' /><label for='viewallyes'>View All</label>\n".
				"\t<input type='radio' id='viewreadable' name='viewallradio' /><label for='viewreadable'>View Readable</label>\n".
			"</div>\n".
			"<table id='allwikis' class='ui-widget' >\n<tr class='ui-widget-header'>" .
				"<td class='wikiid ui-corner-tl'>#</td>".
				"<td>Wiki</td>".
				"<td>Owner</td>".
				"<td>Group(s)</td>".
				"<td class=\"ui-corner-tr\">Actions</td>".
			"</tr>\n";
/* --- Each Wiki Listing --- */		
		foreach ($wikiArray as $row) {
			extract ($row);
			if ($id==1) { //hack
				$readable = 0;
				$requested_readable = 1;
			}
			if ($realname == '') $realname = $wikiname;	//hack?  fix the database.
			$output .= "\t<tr class=\"ui-widget-content" . (!$readable ? " nonreadable" : "") . "\">".
				"<td class=\"wikiid\">$wikiid</td>".
				"<td>".($readable ? "<a href=\"/$wikiid/\">$realname</a>" : $realname)."</td>".
				"<td>$owner_realname</td>".
				"<td>&nbsp;".(implode(", ", $groups))."</td>".
				"<td class=\"controls\">";
			if ($autologin[0]) {				
				$output .= "<select id=\"loginselect$wikiid\">";				
				foreach ($autologin as $alogin) {
					$output .= "<option>$alogin</option>";
				}
				$output .= "<option>Manual sign-in</option>" .
					"</select>";
				if (!$readable) $output .= "<input type=button class='requestbutton' value='Request Write Access'>";
			} elseif ($readable) {
				$output .= "<input type=button class='linkbutton' link='/$wikiid/' name='$wikiid' value=\"Manual sign-in\">\n";
			} elseif ($requested_readable) {
				$output .= "<input type=button class='requestedbutton' value='View Request Status'>\n";
			} else { 
				$output .= "<input type=button class='requestbutton' value='Request Access'>";
			}
			$output .= "</td></tr>\n";
				
		}
		$output .= "</table>\n";
		$output .= "<div class='ui-helper-hidden'><form name='hiddenform' method='post' action='index.php'></form></div>\n";
		$output .= $this->uglydumpling ($this->getAllWikis());
		return $output;
	}

	function page_mywikis() {
		if (!$this->isActivated()) {
			error_log (__METHOD__.": requested by unactivated user");
			return false;
		}
		$wikiArray = $this->getMyWikis();
		$element = "mywikistabs";
		$output = "<script language=\"JavaScript\">\n\t$(function() {\n\t\t$(\"#$element\").tabs();\n\t});\n</script>" .
			"\n\t" .
//TODO
			"<h2>My Wikis</h2>\n" .
			"<div id=\"$element\">\n\t<ul>\n";
		$content = "";
		foreach ($wikiArray as $row) {
			extract ($row);
			$visible_to = implode(", ", $groups);
			$output .= "\t\t<li><a href=\"#tab_$wikiname\">#$wikiid: $realname</a></li>\n";
			$content .= "
	<div id=\"tab_$wikiname\">
		<p>Wiki #$wikiid: $realname ($wikiname)<br>
		Login now as: ... <br>
		This wiki is visible to these groups: $visible_to<br>
		to do...</p>
	</div>";
		}
		$output .= <<<BLOCK
<li><a href="#newwikitab"><span class="ui-icon ui-icon-arrowreturnthick-1-s" style="float: left; margin-right: .3em;"></span>Create a New Wiki</a></li>
</ul>$content
<div id="newwikitab">
<form id="createwiki">
create wiki stuff...
</form>
</div>
</div>
BLOCK;
		$output .= $this->uglydumpling ($this->getMyWikis());
		return $output;
	}
	
	// some default landing page
	function page_wikis_unactivated() {
		return "page_wikis_unactivated - [join group links]";
	}

	function page_groups() {
		$output = "<h3>Groups</h3>";
	
		return ($output . $this->uglydumpling ($this->getAllGroups()) );
	}


	function page_tools() {
		return <<<BLOCK
<h2>Tools</h2><br>
<ul>
<li><a href="table.php">Excel -> Wiki Table converter</a></li>
</ul>
BLOCK;
	}

	function page_requests() {
		$requests = $this->getAllRequests();
		$num = count($requests);
		$output = "<h2>Requests</h2>What's this page do?";
		if ($num > 0) $output .= $this->textHighlight("You have <strong>$num</strong> pending request". ($num == 1 ? "." : "s.") );
		$output .= "<table class=\"ui-state-default ui-corner-all\" style=\"padding: 0 .7em;\">\n";
		foreach ($requests as $req) {
			extract ($req);
			$output .= "\t<tr><td><span class=\"ui-icon ui-icon-flag\" style=\"float: left; margin-right: .3em;\"></span>" . 
				(!$this->isAdmin() ? "<td>#$requestid</td>" : "") .
				"<td>wiki: $wikiid, mwusername: $mwusername, groupname: $groupname </td>
				</tr>\n";
		}
		$output .= "</table>\n";
		
		return $output . $this->uglydumpling ($this->getAllRequests());
	}
	
	function page_createwiki() {
		return "Use (My Wikis-&gt;Create a New Wiki) instead...";
		
	}

	function uglydumpling ($x) {
		return "<pre>".htmlspecialchars(print_r($x,true))."</pre>";
	}

	// $obj->textHighlight("<strong>Hey!</strong> Sample ui-state-highlight style.");
	function textHighlight($text) {
		return "<div class=\"ui-widget\">
			<div class=\"ui-state-highlight ui-corner-all\" style=\"margin-top: 20px; padding: 0 .7em;\"> 
				<p><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span>
				$text</p>
			</div>";
	}
	// $obj->textError("<strong>Alert:</strong> Sample ui-state-error style.");
	function textError($text) {
		return "<div class=\"ui-widget\">
			<div class=\"ui-state-error ui-corner-all\" style=\"padding: 0 .7em;\"> 
				<p><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span> 
				$text</p>
			</div>
		</div>";
	}
	// Request access to a wiki, best served in a popup.
	function textRequestAccess() {
$output = <<<EOT
<script type="text/javascript">
	$(function() {
		$('.getaccessdialog').dialog({
			autoOpen: false,
			width: 600,
			buttons: {
				"Send Request": function() {					
					$(this).dialog("close"); 
				}, 
				"Cancel": function() { 
					$(this).dialog("close"); 
				}
			}
		});
		$('#requestbutton').click(function(){	
			$('#reqwikiname').html('<strong>'+$(this).attr('wikiname')+'</strong>');
			$('#getaccessdialog').dialog('open');
			return false;
		});
	});
</script>

<p><a href="#" class="getaccessdialog"><span class="ui-icon ui-icon-flag"></span>Request Access</a></p>

<div id="getaccessdialog" title="Request Access To A Wiki">
	<form><table>
	<tr><td align=right>Wiki name:</td><td id="reqwikiname">&nbsp;</td></tr>
	<tr><td align=right>Write access wanted?</td><td><checkbox name="writeaccess" checked></td></tr>
	<tr><td align=right>Username you want:</td><td><input type="text" name="reqmwusername"></td></tr>
	</table></form>
</div>
EOT;
	return $output;
	}

	// AJAX handlers

	function dispatch_ajax ($post) {
		if (!method_exists ($this, "ajax_" . $post["ga_action"]))
			return array ("success" => false,
				      "alert" => "Invalid request (action=".$post["ga_action"].")");
		return call_user_func (array ($this, "ajax_" . $post["ga_action"]), $post);
	}

	function ajax_test_success ($post) {
		return array ("success" => true,
			      "message" => "Great success, \"$post[sample_id]\"!");
	}
	function ajax_test_failure ($post) {
		return array ("success" => false,
			      "message" => "That totally failed, \"$post[sample_id]\".");
	}
	function ajax_test_alert ($post) {
		return array ("success" => true,
			      "alert" => "I would like to alert you.",
			      "message" => "I alerted you.");
	}
	function ajax_test_alert_redirect ($post) {
		return array ("success" => true,
			      "alert" => "I would like to alert you and then redirect.",
			      "message" => "I alerted you.",
			      "redirect" => "/?tabActive=wikis");
	}
	function ajax_test_activated ($post) {
		if ($this->isActivated()) {
			return array ("success" => true,
				      "message" => "Yeah, your account is activated.");
		} else {
			return array ("success" => false,
				      "message" => "Sorry, your account is not yet activated.");
		}
	}

}  // class ends




// misc functions

function PMRelativeTime($date) {
	if ($date+0 == 0) $date = strtotime($date);
	$diff = time() - $date;
	if ($diff<60) {
		$r = "$diff second";
	} else {
		$diff = round($diff/60);
		if ($diff<60) {
			$r = "$diff minute";
		} else {
			$diff = round($diff/60);
			if ($diff<24) {
				$r = "$diff hour";
			} else {
				$diff = round($diff/24);
				if ($diff<7) {
					$r = "$diff day";
				} else {
					$diff = round($diff/7);
					if ($diff<4) {
						$r = "$diff week";
					} else {
						return date("F j, Y", $date);
					}
				}
			}
		}
	}
	return $r . ($diff !=1 ? 's' : '') . " ago";
}

?>
