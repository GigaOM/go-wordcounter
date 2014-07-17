function updateWordCount() {
	var content, excerpt = null
	var counter = function(obj) {
		if(typeof(obj) != 'undefined') {
			// Replace tags, things that look like shortcodes, and words that are entirely non-alpha-numeric characters.
			obj   = obj.replace(/\[.*?\]|<\S.*?>|[\^\s][^\s\da-z]+\s/gi, ' ');
			words = obj.match(/(\S+)/g);
			if(null != words) {
				return words.length;
			}
		}
		return 0;
	};

	content = counter(jQuery('#content').val());
	excerpt = counter(jQuery('#excerpt').val());
	jQuery("#wordcount").html(addCommas(content))
	jQuery('.excerpt_wordcount').html(addCommas(excerpt));

	setTimeout("updateWordCount()", 2000);
}

// Add commas to numbers
function addCommas(nStr) {
	return nStr.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
}

// tell the browser to call our function every 2 seconds
jQuery(document).ready(function(){
	jQuery("#misc-publishing-actions").append('<div class="misc-pub-section giga-word-count">' + go_wordcounter.text + '<br/>' + go_wordcounter.excerpt + '</div>').css('display', 'block')
	jQuery('#excerpt').after('<p class="giga-word-count">' + go_wordcounter.excerpt +'</p>');
	setTimeout("updateWordCount()", 2000);
})
