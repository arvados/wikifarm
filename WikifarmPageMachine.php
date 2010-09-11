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
<P>test_success: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_success">Test success</button></P>
<P>test_failure: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_failure">Test failure</button></P>
<P>test_ajax_error: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_ajax_error">Test ajax error</button></P>
<P>test_alert: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_alert">Test alert</button></P>
<P>test_alert_redirect: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_alert_redirect">Test alert-and-redirect</button></P>
<P>test_activated: <button class="generic_ajax" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_activated">Make sure my account is activated</button></P>
<P><div style="height:18px"><span id="fooloader" /><span id="foomessage" /></div></P>
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
				"#allwikis td { padding-right: 20px; }\n".
				"#allwikis td.wikiid { width: 25px; text-align: right; padding-right: 10px; }\n".
				"#allwikis td.controls { padding-right: 0px; }\n".
				".controls a { padding: 0px; margin: 0px; }\n".
			"</style>\n";
			$output .= $this->textRequestAccess();
/* --- Page Heading --- */		
		$output .= "<h2>All Wikis</h2>\n".			
			"<table id='allwikis' class='ui-widget' >\n" .
			"<tr class=\"ui-widget-content\">".
			"\t<td colspan=5><div align=right id='viewallradio'>\n".
				"\t\t<input type='radio' id='viewallyes' name='viewallradio' checked='checked' /><label for='viewallyes'>View All</label>\n".
				"\t\t<input type='radio' id='viewallno' name='viewallradio' /><label for='viewallno'>View Readable</label>\n".
			"\t</div></td></tr>\n".
			"<tr class='ui-widget-header'>\n".
				"<td class='wikiid ui-corner-tl'>#</td>".
				"<td>Wiki</td>".
				"<td>Owner</td>".
				"<td>Group(s)</td>".
				"<td class=\"ui-corner-tr\">Actions</td>".
			"</tr>\n";
/* --- Each Wiki Listing --- */		
		foreach ($wikiArray as $row) {
			extract ($row);
			if ($realname == '') $realname = $wikiname;	//hack?  fix the database.
			$output .= "\t<tr class=\"ui-widget-content" . (!$readable ? " nonreadable" : "") . "\">".
				"<td class=\"wikiid\">$wikiid</td>".
				"<td>".($readable ? "<a href=\"/$wikiname/\">$realname</a>" : $realname)."</td>".
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
				$output .= "<input type=button class='linkbutton' link='/$wikiname/' name='wiki $wikiid' value=\"Manual sign-in\">\n";
			} elseif ($requested_readable) {
				$output .= "<input type=button class='requestedbutton' value='View Request Status'>\n";
			} else { 
				$output .= "<input type=button class='requestbutton' wikiid='$wikiid' wikititle='$realname' value='Request Access'>";
			}
			$output .= "</td></tr>\n";
				
		}
		$output .= "</table>\n";
		// $output .= "<div class='ui-helper-hidden'><form name='hiddenform' method='post' action='index.php'></form></div>\n";
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
			$content .= "<div id=\"tab_$wikiname\">";
			$content .= $this->frag_managewiki ($row);
			$content .= "</div>\n";
		}
		$groups_options = "";
		foreach ($this->getAllGroups() as $g) {
			$groupid = htmlspecialchars($g["groupid"]);
			if ($groupid == "ADMIN")
				continue;
			if ($groupid == "users")
				$groupname = "Everyone";
			else
				$groupname = htmlspecialchars($g["groupname"]);
			$groups_options .= "<option value=\"$groupid\">$groupname</option>";
		}
		$q_mwusername = htmlspecialchars($this->getMWUsername());
		$output .= <<<BLOCK
<li><a href="#newwikitab"><span class="ui-icon ui-icon-arrowreturnthick-1-s" style="float: left; margin-right: .3em;"></span>Create a New Wiki</a></li>
</ul>$content
<div id="newwikitab">
<form id="createwikiform" action="#">
<table>

<tr><td>Wiki title:</td>
<td><input type=text name=realname size=32 value="Lab Notebook"></td>
<td>Full title of your wiki, like "Jane Bobbleson's Lab Notebook"</td>
</tr>

<tr><td>Wiki name: </td>
<td><input type=text name=wikiname size=32 maxlength=12></td>
<td>3 to 12 lower case letters. url of wiki will be http://$_SERVER[HTTP_HOST]/name</td>
</tr>

<tr><td>Your username in the new wiki: </td>
<td><input type=text name=mwusername size=32 value="$q_mwusername"></td>
<td>letters and digits only.  start with an upper case letter.
</tr>

<tr><td>Groups to invite to the new wiki: </td>
<td><select multiple name="groups[]">$groups_options</select></td>
<td>control-click to select and de-select multiple groups
</tr>


<tr><td></td>
<td><button class="generic_ajax" ga_form_id="createwikiform" ga_loader_id="createwiki_loader" ga_message_id="createwiki_message" ga_action="createwiki">Create new wiki</button></td>
<td></td>
</tr>

<tr><td></td>
<td colspan=2><div style="height:18px"><span id="createwiki_loader" /><span id="createwiki_message" /></div></td>
</tr>
</table>
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

	function page_users() {
		$html = <<<BLOCK
<table id="userlist">
<thead>
<tr>
<th>Email</th>
<th>Real Name</th>
<th>Preferred MW Username</th>
<th>ID</th>
</tr>
</thead>
<tbody>
BLOCK;
		foreach ($this->getAllActivatedUsers() as $u) {
			foreach ($u as $k => $v) { $u["q_$k"] = htmlspecialchars($v); }
			extract ($u);
			$html .= <<<BLOCK
<tr>
<td>$q_email</td>
<td>$q_realname</td>
<td>$q_mwusername</td>
<td>$q_userid</td>
</tr>
BLOCK;
		}
		$html .= <<<BLOCK
</tbody>
</table>

<script language="JavaScript">
$("#userlist").dataTable({"iDisplayLength": 25});
</script>
<br clear />
BLOCK;
		return $html;
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
		$html = $this->textHighlight("You have <strong>$num</strong> pending request". ($num == 1 ? "." : "s.") );

		$html .= <<<BLOCK
<table id="myreqs">
<thead>
<tr><th>&nbsp;</th><th>&nbsp;</th><th>Request</th><th>Name</th><th>Email</th><th>OpenID</th></tr>
</thead>
<tbody>
BLOCK;
		foreach ($requests as $req) {
			$email = "";
			$wikiname = null;
			$mwusername = null;
			extract ($req);
			$q_wikiname = htmlspecialchars(isset($wikiname) ? $wikiname : "");
			$q_mwusername = htmlspecialchars(isset($mwusername) ? $mwusername : "");
			$q_groupname = htmlspecialchars(isset($groupname) ? $groupname : "");
			if (!$wikiid && $groupname == "users")
				$request = "Activate account";
			else if (!$wikiid)
				$request = "Join \"$q_groupname\" group";
			else if ($mwusername)
				$request = "Edit <a href=\"/$q_wikiname/\">$q_wikiname</a> as \"$q_mwusername\"";
			else
				$request = "View <a href=\"/$q_wikiname/\">$q_wikiname</a>";

			$q_name = htmlspecialchars($realname);
			$q_email = htmlspecialchars($email);
			$q_openid = htmlspecialchars($userid);
			$html .= <<<BLOCK
<tr id="req_row_$requestid">
<td><button class="req_response_button approve" requestid="$requestid">Approve</button></td>
<td><button class="req_response_button reject" requestid="$requestid">Reject</button></td>
<td requestid="$requestid">$request</td>
<td>$q_name</td>
<td>$q_email</td>
<td>$q_openid</td>
</tr>
BLOCK;
		}
		$html .= <<<BLOCK
</tbody></table>
<script language="JavaScript">
$("#myreqs").dataTable({"bInfo": false, "bPaginate": false, "aaSorting": [[5,"asc"],[2,"asc"]]});
</script>
<br clear />
BLOCK;
		
		return $html . $this->uglydumpling ($this->getAllRequests());
	}
	
	function page_createwiki() {
		return "Use (My Wikis-&gt;Create a New Wiki) instead...";
		
	}

	function uglydumpling ($x) {
		return "<pre>".htmlspecialchars(print_r($x,true))."</pre>";
	}

	function frag_managewiki ($wiki) {
		extract ($wiki);
		$html = "<form id=\"mwf$wikiid\">";
		$html .= "<input type=\"hidden\" name=\"wikiid\" value=\"$wikiid\" />\n";
		$html .= "<table id=\"mwg${wikiid}\">";
		$html .= "<thead><tr><th class=\"minwidth\">View?&nbsp;</th><th>Group</th></tr></thead><tbody>";
		foreach ($this->getAllGroups() as $g) {
			if ($g["groupid"] == "ADMIN") continue;
			$html .= "<tr>";
			$checked = false === array_search ($g["groupid"], $groups) ? "" : "checked";
			$groupid = $g["groupid"];
			$html .= "<td class=\"minwidth\"><input type=\"checkbox\" class=\"generic_ajax\" ga_form_id=\"mwf$wikiid\" ga_action=\"managewiki\" id=\"mw${wikiid}_group_".htmlspecialchars($g["groupid"])."\" name=\"mw${wikiid}_groups[]\" value=\"".htmlspecialchars($g["groupid"])."\" $checked></td>";
			$html .= "<td>".htmlspecialchars($g["groupname"])."</td>";
			$html .= "</tr>";
		}
		$html .= "</tbody></table>";

		$html .= "<br clear=all /><p>&nbsp;</p>";

		$invited_users = $this->getInvitedUsers ($wikiid);
		$invited_userid = array();
		foreach ($invited_users as $u) {
			$invited_userid[] = $u["userid"];
		}
		$html .= "<table id=\"mwu${wikiid}\">";
		$html .= "<thead><tr><th class=\"minwidth\">View?&nbsp;</th><th>User</th></tr></thead><tbody>";
		foreach ($this->getAllActivatedUsers() as $u) {
			$html .= "<tr>";
			$checked = false === array_search ($u["userid"], $invited_userid) ? "" : "checked";
			$html .= "<td class=\"minwidth\"><input type=\"checkbox\" class=\"generic_ajax\" ga_form_id=\"mwf$wikiid\" ga_action=\"managewiki\" id=\"mw${wikiid}_user_".htmlspecialchars($u["userid"])."\" name=\"mw${wikiid}_users[]\" value=\"".htmlspecialchars($u["userid"])."\" $checked></td>";
			$html .= "<td>".htmlspecialchars($u["realname"]." (".$u["userid"].")")."</td>";
			$html .= "</tr>";
		}
		$html .= "</tbody></table>";

		$html .= "<br /><div style=\"min-height: 12px;\" /><br />";

		$html .= "</form>";
		$html .= "<script language=\"JavaScript\">
\$(\"#mwg$wikiid\").dataTable({\"bInfo\": false, \"bSort\": false, \"bFilter\": false, \"bLengthChange\": false});
\$(\"#mwu$wikiid\").dataTable({\"bInfo\": false, \"bSort\": false, \"bLengthChange\": false});
</script>\n";
		return $html;
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
	
	// Request access to a wiki, served in a popup.
	function textRequestAccess() {
		$q_defaultmwusername = htmlspecialchars ($this->getMWUsername());
$output = <<<EOT
<script type="text/javascript">
	$(function() {
			$('#getaccessdialog').dialog({ modal: true, autoOpen: false, width: 400, buttons: { 
			"Send Request": function() { dialog_submit(this, "#getaccess"); }, 
			"Cancel": function() { $(this).dialog("close"); }
		} });
		
		$('.requestbutton').click(function(){	
			$('#reqwikiname').html('<strong>'+$(this).attr('wikititle')+'</strong>');
			$('#reqwriteaccess').attr('checked',true);
			$('#reqwikiid').val($(this).attr('wikiid'));
			$('#getaccessdialog').dialog('open');
			return false;
		});
	});
\$('#reqwriteaccess').live('click', function(){ \$('#reqmwusername').attr('disabled',!\$('#reqwriteaccess').attr('checked')); });
</script>

<div id="getaccessdialog" title="Request Access To A Wiki">
	<form id="getaccess"><table>
	<tr><td align=right>Wiki name:</td><td id="reqwikiname">&nbsp;</td></tr>
	<tr><td align=right>Write access wanted?</td><td><input type=checkbox id="reqwriteaccess" name="writeaccess" value="true" checked="checked">&nbsp;</td></tr>
	<tr><td align=right>Username you want:</td><td><input type="text" id="reqmwusername" name="mwusername" value="$q_defaultmwusername"></td></tr>
	</table>
	<input type="hidden" name="wikiid" id="reqwikiid" value="">
	<input type="hidden" name="ga_action" value="requestwiki"></form>
</div>
EOT;
	return $output;
	}

	// AJAX handlers

	function dispatch_ajax ($post) {
		if (!method_exists ($this, "ajax_" . $post["ga_action"]))
			return array ("success" => false,
				      "alert" => "Invalid request (action=".$post["ga_action"].")");
		try {
			return call_user_func (array ($this, "ajax_" . $post["ga_action"]), $post);
		} catch (Exception $e) {
			return $this->fail ($e->getMessage());
		}
	}

	function ajax_test_success ($post) {
		if (preg_match ('{^\d+$}', $post["sample_id"]))
			sleep ($post["sample_id"]);
		return array ("success" => true,
			      "message" => "Great success, \"$post[sample_id]\"!");
	}
	function ajax_test_failure ($post) {
		return array ("success" => false,
			      "message" => "That totally failed, \"$post[sample_id]\".");
	}
	function ajax_test_ajax_error ($post) {
		print "Unparseable.";
		exit;
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
	function ajax_managewiki ($post) {
		$wikiid = $post["wikiid"];
		$wiki = $this->getWiki($wikiid);
		if (!$this->isAdmin() && $wiki["userid"] != $this->openid)
			return $this->fail ("You are not allowed to do that.");

		$checkus = array();
		$uncheckus = array();

		$want = $post["mw${wikiid}_groups"];
		foreach ($this->getAllGroups() as $g) {
			if ($g["groupid"] == "ADMIN") continue;
			if (!$want || false === array_search ($g["groupid"], $want)) {
				$this->disinviteGroup ($wikiid, $g["groupid"]);
				$uncheckus[] = "mw${wikiid}_group_".$g["groupid"];
			}
			else {
				$this->inviteGroup ($wikiid, $g["groupid"]);
				$checkus[] = "mw${wikiid}_group_".$g["groupid"];
			}
		}

		$want = $post["mw${wikiid}_users"];
		foreach ($this->getAllActivatedUsers() as $u) {
			if (!$want || false === array_search ($u["userid"], $want)) {
				$this->disinviteUser ($wikiid, $u["userid"]);
				$uncheckus[] = "mw${wikiid}_user_".$u["userid"];
			}
			else {
				$this->inviteUser ($wikiid, $u["userid"]);
				$checkus[] = "mw${wikiid}_user_".$u["userid"];
			}
		}

		return array ("success" => true,
			      "check" => $checkus,
			      "uncheck" => $uncheckus);
	}

	function ajax_createwiki ($post) {
		$this->validate_activated();
		if (!$this->canCreateWikis())
			return $this->fail ("You have reached your wiki quota.  Please contact an administrator to increase your quota.");
		$post["realname"] = trim($post["realname"]);
		if ($post["realname"] == "")
			return $this->fail ("You must provide a title for your wiki.");
		if (!preg_match ('{^[-\w\' ]+$}', $post["realname"]))
			return $this->fail ("Your wiki title cannot contain quotation marks, symbols, or special characters.");

		$this->validate_wikiname ($post["wikiname"]);
		$this->validate_mwusername ($post["mwusername"]);

		if (!$this->isWikiNameAvailable ($post["wikiname"]))
			return $this->fail ("The wiki name \"$post[wikiname]\" is already in use.");

		$ok = $this->createWiki ($post["wikiname"],
					 $post["realname"],
					 $post["mwusername"],
					 $post["groups"]);
		if (!$ok)
			return $this->fail ("Something went wrong while setting up your wiki.  Please contact a site administrator before trying again.");
		return array ("success" => true,
			      "alert" => "Your wiki has been created.  You will be logged in to your new wiki now.",
			      "redirect" => "/".$post["wikiname"]."/Main_Page");
		
	}

	function ajax_requestwiki ($post) {
		$this->validate_activated();
		if ($post["writeaccess"]) {
			$this->validate_mwusername ($post["mwusername"]);
			$this->requestWiki ($post["wikiid"]+0, $post["mwusername"]);
		} else
			$this->requestWiki ($post["wikiid"]+0);
		return $this->success();
	}

	function ajax_approve_request ($post) {
		$this->approveRequestId ($post["requestid"]+0);
		return $this->success();
	}

	function ajax_reject_request ($post) {
		$this->rejectRequestId ($post["requestid"]+0);
		return $this->success();
	}

	function validate_wikiname ($x) {
		if (!preg_match ('{^[a-z][a-z0-9]{3,12}$}', $x))
			throw new Exception ("Your wiki name must be 3 to 12 lower case letters and digits, and must start with a letter.");
	}

	function validate_mwusername ($x) {
		if (!preg_match ('{^[a-z][-a-z0-9_\.]*$}i', $x))
			throw new Exception ("A MediaWiki username must contain only letters, digits, underscores, dots, and dashes, and must begin with a letter.");
	}

	function validate_activated () {
		if (!$this->isActivated())
			throw new Exception ("You are not allowed to do that.");
	}

	function fail($message="Server side error.") {
		return array ("success" => false,
			      "message" => $message,
			      "alert" => $message);
	}
	function success($message="OK") {
		return array ("success" => true,
			      "message" => $message);
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
