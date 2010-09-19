// adds $('element').exists();
jQuery.fn.exists = function(){return jQuery(this).length>0;}

// returns a number that it's never returned before
function wf_uid()
{
	return (++wf_uid.n);
}
// mutate an id until unique
(function( $ ){
	$.fn.mutateID = function() {
		var id = String($(this).attr('id'));
		var mut=0;
		while ($(id+mut).exists()) mut++;
		return $(this).attr('id',id+mut);
	};
})( jQuery );

// move a dialog div outside of the tab elements (TODO: this might be pointless with ZE AUTODESTRUCTOR)
(function( $ ){
	$.fn.elevateDiv = function() {					
		return $(this).appendTo('#dialog-container');		
	};
})( jQuery );

function wf_tab_select (tabset, selecttab)
{
    $('#'+tabset+'>ul>li>a').each(function(i,e){ if($(e).attr('tab_id')==selecttab) $('#tabs').tabs('select', i); });
}

function tabIndexByName(tab) {
	return $("a[tab_id='"+tab+"']").parent().index();
}

function selectTabByName(tabs, tab) {
	$(tabs).tabs('select', tabIndexByName(tab));
}

function generic_ajax_success(data, textStatus, req, button)
{
    if (button.disabled)
	button.disabled = false;

    if (data.check)
	$.each(data.check, function (i,e) { if ($('#'+e)) $('#'+e).attr('checked', true); });
    if (data.uncheck)
	$.each(data.uncheck, function (i,e) { if ($('#'+e)) $('#'+e).attr('checked', false); });
    if (data.disable)
	$.each(data.disable, function (i,e) { if ($('#'+e)) $('#'+e).attr('disabled', true); });
    if (data.enable)
	$.each(data.enable, function (i,e) { if ($('#'+e)) $('#'+e).attr('disabled', false); });
    if (data.hide)
	$.each(data.hide, function (i,e) { if ($('#'+e)) $('#'+e).hide(); });
    if (data.show)
	$.each(data.show, function (i,e) { if ($('#'+e)) $('#'+e).show(); });

    if (data.request && data.request.ga_loader_id && $('#'+data.request.ga_loader_id))
	$('#'+data.request.ga_loader_id).hide();
    var msg = false;
    if (data.request && data.request.ga_message_id)
	msg = $('#'+data.request.ga_message_id);
    else if ($(button).attr('ga_message_id'))
	msg = $('#'+$(button).attr('ga_message_id'));
    if (data.message && msg) {
	msg.addClass('ui-widget ui-state-highlight ui-corner-all wf-message-box');
	msg.removeClass('ui-state-error ui-state-highlight');
	msg.addClass(data.success ? 'ui-state-highlight' : 'ui-state-error');
	var html = '<P><SPAN class="ui-icon wf-message-icon ';
	if (data.success) html += 'ui-icon-circle-check';
	else html += 'ui-icon-alert';
	html += '" />'+data.message+'</P>';
	msg.html(html).show();
	if (data.alert && (data.redirect || data.refreshtab || data.selecttab))
	    alert (data.alert);
    }
    else if (data.alert)
	alert (data.alert);
    else if (data.message)
	alert (data.message);
    if (data.redirect)
	window.location = data.redirect;
    if (data.refreshtab)
	$('#tabs').tabs('load', $('#tabs').tabs('option', 'selected'));
    if (data.selecttab)
	wf_tab_select('tabs', data.selecttab);
}

function generic_ajax_error(req, textStatus, errorThrown, button)
{
    if (button.disabled)
	button.disabled = false;
    var loader_id = $(button).attr('ga_loader_id');
    if (loader_id && $('#'+loader_id))
	$('#'+loader_id).hide();
    alert (textStatus);
}

function generic_ajax_submit()
{
    try {
	var postme = $('#'+$(this).attr('ga_form_id')).serializeArray();
	var ga_loader_id = $(this).attr('ga_loader_id');
	postme.push({name: 'ga_message_id', value: $(this).attr('ga_message_id')},
		    {name: 'ga_loader_id', value: $(this).attr('ga_loader_id')},
		    {name: 'ga_button_id', value: $(this).attr('id')},
		    {name: 'ga_action', value: $(this).attr('ga_action')});
	if ($(this).attr('ga_message_id') &&
	    $('#'+$(this).attr('ga_message_id'))) {
	    $('#'+$(this).attr('ga_message_id')).hide();
	}
	if ($(this).attr('ga_loader_id') &&
	    $('#'+$(this).attr('ga_loader_id'))) {
	    $('#'+$(this).attr('ga_loader_id')).html('<img src="/js/ajax-loader.gif" width="16" height="16" border="0" />').show();
	}
	var button = this;
	$(':button').each(function(){if(this==button) button.disabled=true;});
	$.ajax({
		url: '/',
		    type: 'POST',
		    dataType: 'json',
		    data: postme,
		    success: function(d,t,r) { return generic_ajax_success(d,t,r,button); },
		    error: function(r,t,e) { return generic_ajax_error(r,t,e,button); },
		    cache: false
	    });
    } catch(e) {
	alert ("Browser compatibility problem: " + e.name + " (" + e.message + ")");
    }
    return false;
}

function dialog_submit(dialog, form)
{
    $.ajax({
	    url: '/',
		type: 'POST',
		dataType: 'json',
		data: (typeof(form)=="object" ? form : $(dialog).find(form)).serializeArray(),
		success: function(d,t,r)
		{
		    if (d && (d.success || d.refreshtab || d.redirect || d.selecttab)) {
			$(dialog).dialog("close");
			d.request.ga_message_id = null;
		    }
		    generic_ajax_success (d,t,r,dialog);
		},
		error: function(r,t,e)
		{
		    generic_ajax_error (r,t,e,dialog);
		},
		cache: false
		});
    return false;
}

function req_response_click ()
{
    var ga_action;
    if ($(this).hasClass('approve')) ga_action = 'approve';
    else if ($(this).hasClass('reject')) ga_action = 'reject';
    else return false;

    var requestid = $(this).attr('requestid');
    if (!requestid) return false;

    $.ajax({
	    url: '/',
		type: 'POST',
		dataType: 'json',
		cache: false,
		data: [{name: 'ga_action', value: ga_action+'_request' },
		       {name: 'requestid', value: requestid }],
		success: function (d,t,r)
		{
		    if (d && d.success) {
			$('#req_row_'+requestid).css('height', $('#req_row_'+requestid).height());
			$('.req_response_button[requestid='+requestid+']').hide();
			if (d.request.ga_action == 'reject_request')
			    $('[requestid='+requestid+']').css('text-decoration','line-through');
		    }
		    else if (d.alert) alert(d.alert);
		    else if (d.message) alert(d.message);
		},
		error: function (r,t,e) { alert(e); }
	});
    return false;
}

function group_request_enable ()
{
    var button = "#group_request_submit";
    if ($(button+"[admin_mode]").attr("disabled", false).length)
	return;
    if ($("form#group_request").serialize())
	$(button).attr("disabled", false);
    else
	$(button).attr("disabled", true);
}

$('.generic_ajax').live('click', generic_ajax_submit);
$('.req_response_button').live('click', req_response_click);
$('input[name^=group_request]').live('click', group_request_enable);
$('form').live('submit',function(){if(!$(this).hasClass('openidloginform')) return false;});
