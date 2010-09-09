/* original tab code */

function generic_ajax_success(data, textStatus, req)
{
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

function generic_ajax_error(req, textStatus, errorThrown)
{
    alert (textStatus);
}

function generic_ajax_submit()
{
    var postme = $('#'+$(this).attr('ga_form_id')).serializeArray();
    postme.push({name: 'ga_message_id', value: $(this).attr('ga_message_id')},
		{name: 'ga_button_id', value: $(this).attr('id')},
		{name: 'ga_action', value: $(this).attr('ga_action')});
    if ($(this).attr('ga_message_id') &&
	$('#'+$(this).attr('ga_message_id'))) {
	$('#'+$(this).attr('ga_message_id')).hide();
    }
    $.ajax({
	    url: '/',
	    type: 'POST',
	    dataType: 'json',
	    data: postme,
	    success: generic_ajax_success,
	    error: generic_ajax_error,
	    cache: false,
	    beforeSend: function(xhr) {
		xhr.setRequestHeader('Accept','application/json');
	    }
	});
}

$('.generic_ajax').live('click', generic_ajax_submit);
