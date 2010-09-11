/* original tab code */

function generic_ajax_success(data, textStatus, req, button)
{
    button.disabled = false;

    if (data.check)
	$.each(data.check, function (i,e) { if ($('#'+e)) $('#'+e).attr('checked', true); });
    if (data.uncheck)
	$.each(data.uncheck, function (i,e) { if ($('#'+e)) $('#'+e).attr('checked', false); });

    if (data.request && data.request.ga_loader_id && $('#'+data.request.ga_loader_id))
	$('#'+data.request.ga_loader_id).hide();
    if (data.message && data.request && data.request.ga_message_id) {
	var msg = $('#'+data.request.ga_message_id);
	msg.removeClass('ga_success ga_failure ga_warning');
	msg.addClass(data.success ? 'ga_success' : 'ga_failure');
	if (data.warning) msg.addClass('ga_warning');
	msg.html(data.message).show();
    }
    if (data.alert)
	alert (data.alert);
    if (data.redirect)
	window.location = data.redirect;
}

function generic_ajax_error(req, textStatus, errorThrown, button)
{
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
	button.disabled = true;
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
		data: $(form).serializeArray(),
		success: function(d,t,r)
		{
		    if (d && d.success)
			$(dialog).dialog("close");
		    else if (d.alert) alert(d.alert);
		    else if (d.message) alert(d.message);
		},
		error: function(r,t,e) { alert (e); },
		cache: false
		});
    return false;
}

$('.generic_ajax').live('click', generic_ajax_submit);
