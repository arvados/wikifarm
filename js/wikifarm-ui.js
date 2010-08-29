/* original tab code */
var initialTab = "wikis";

$(document).ready(function() {
	$("#tabmenu li").each(function() {
		var thisAnchor = $(this).find('a');
		$(this).click(function() {
			$("#tabmenu li").each(function() { $(this).find('a').removeClass("active"); });
			thisAnchor.addClass("active");
			$('div#content').load("index2.php?tab="+thisAnchor.attr("id"));
		});
	});
	$('a#'.initialTab).addClass("active");  // doesn't seem to be working
	$('div#content').load("index.php?tab="+initialTab);
});

/* jQuery ui */


