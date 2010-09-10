/* original tab code */

function generic_ajax_success(data, textStatus, req)
{
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
	window.location.replace (data.redirect);
}

function generic_ajax_error(req, textStatus, errorThrown, loader_id)
{
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
	$.ajax({
		url: '/',
		    type: 'POST',
		    dataType: 'json',
		    data: postme,
		    success: generic_ajax_success,
		    error: function(r,t,e) { return generic_ajax_error(r,t,e,ga_loader_id); },
		    cache: false,
		    beforeSend: function(xhr) {
		    xhr.setRequestHeader('Accept','application/json');
		}
	    });
    } catch(e) {
	alert ("Browser compatibility problem: " + e.name + " (" + e.message + ")");
    }
    return false;
}

$('.generic_ajax').live('click', generic_ajax_submit);
