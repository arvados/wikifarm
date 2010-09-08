/* original tab code */

var initialTab = "wikis";

function generic_ajax_success(data, textStatus, req)
{
    if (data.message && data.request && data.request.ga_message_id) {
	$('#'+data.request.ga_message_id).clearQueue();
	$('#'+data.request.ga_message_id).show();
	$('#'+data.request.ga_message_id).html(data.message);
	$('#'+data.request.ga_message_id).delay(3000);
	$('#'+data.request.ga_message_id).queue(function(){$(this).hide();});
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

/*
$(document).ready(function() {
	$("#tabmenu li").each(function() {
		var thisAnchor = $(this).find('a');
		$(this).click(function() {
			$("#tabmenu li").each(function() { $(this).find('a').removeClass("active"); });
			thisAnchor.addClass("active");
			$('div#content').load("index.php?tab="+thisAnchor.attr("id"));
		});
	});
	// $('a#'.initialTab).addClass("active");  // doesn't seem to be working
	$('div#content').load("/?tab="+initialTab);
});
*/

/* jQuery ui */
/*
$(function() {
	$("#accordion").accordion({ header: "h3" });
});
*/
