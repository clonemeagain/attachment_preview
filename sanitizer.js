// src: https://gist.github.com/ufologist/5a0da51b2b9ef1b861c30254172ac3c9
var sanitizer = {};
(function($) {
	var safe = '<a><b><blockquote><dd><div><dl><dt><em><h1><h2><h3><h4><i><img><li><ol><p><pre><s><sup><sub><strong><strike><ul><br><hr><table><th><tr><td><tbody><tfoot>';

	function trimAttributes(node) {
		$.each(node.attributes, function() {
			var attrName = this.name;
			var attrValue = this.value;
			// we could filter the "bad" attributes, or just purge them all..
			$(node).removeAttr(attrName);
		});
	}
	sanitizer.sanitize = function(html) {
		html = strip_tags(html, safe);
		var output = $($.parseHTML('<div>' + $.trim(html) + '</div>', null,
				false));
		output.find('*').each(function() {
			trimAttributes(this);
		});
		return output.html();
	}

	// http://locutus.io/php/strings/strip_tags/ filter the html to only those
	// acceptable tags defined above as safe
	function strip_tags(input, allowed) {
		allowed = (((allowed || '') + '').toLowerCase().match(
				/<[a-z][a-z0-9]*>/g) || []).join('')
		var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi
		var commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi
		return input
				.replace(commentsAndPhpTags, '')
				.replace(
						tags,
						function($0, $1) {
							return allowed
									.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0
									: ''
						})
	}
})(jQuery);