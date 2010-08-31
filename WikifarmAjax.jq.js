var tabNames = [];
var initialTab = "wikis";

$(document).ready(function() {
	$("#tabmenu li").each(function() {
		var thisAnchor = $(this).find('a');
		$(this).click(function() {
			$("#tabmenu li").each(function() { $(this).find('a').removeClass("active"); });
			thisAnchor.addClass("active");
			$('div#content').load("./?tab="+thisAnchor.attr("id"));
		});
	});
	$('#'.initialTab).addClass("active");  // doesn't seem to be working
	$('div#content').load("./?tab="+initialTab);
});
