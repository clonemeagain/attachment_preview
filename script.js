// Setup handler to receive Attachments Preview Fetch events:
$(document).on(
		'ap:fetch',
		function(e) {
			var elem = $(e.target).find('*').first();
			var type = elem.data('type'), id = elem.attr('id'), url = elem
					.data('url');
			if (type && id && url) {
				switch (type) {
				case 'pdf': {
					// Load the PDF into an Object Blob and shove it into the
					// <embed> :-)
					var req = new XMLHttpRequest();
					req.open("GET", url, true)
					req.responseType = "arraybuffer";
					req.onload = function(e) {
						var ab = req.response;
						var blob = new Blob([ ab ], {
							type : "application/pdf"
						});
						var object_url = (window.URL || window.webkitURL)
								.createObjectURL(blob);
						var pdf = document.getElementById(id);
						var newpdf = pdf.cloneNode();
						if (/* @cc_on!@ */false || !!document.documentMode) {
							// IE still cant display a PDF inside an <object>,
							// so, we'll delete it.. because fuck MS
							delete pdf;
							return;
						}
						newpdf.setAttribute('data', object_url);
						// Replace the node with our new one which displays it:
						pdf.parentNode.replaceChild(newpdf, pdf);
						// prevent repeated fetch events from re-fetching
						delete newpdf.dataset.type;
					};
					req.send();
					break;
				}
				case 'text':
					$.get(url, function(data) {
						// Replace the <pre> element's text with the Attachment:
						elem.text(data);
					});
					break;
				case 'html':
					$.get(url, function(data) {
						// Pass the html through the sanitizer then render:
						elem.html($("<div>" + $.trim(sanitizer.sanitize(data))
								+ "</div>"));
					});
				}
				// prevent repeated fetch events from re-fetching
				elem.data('type', '');
			}
		});
// Configure handler for page ready/pjax-ready events:
$(document)
		.on(
				'ready pjax:success',
				function() {
					console
							.log("Triggering AttachmentPreview initial fetch (admin limit set to #LIMIT#).");
					$('.embedded:not(.hidden)').trigger('ap:fetch');
				});

// Toggle function for buttons, shows the attachment's wrapper element and
// triggers the fetch if the attachment isn't already there:
function ap_toggle(item, key) {
	var i = $(item), elem = $('#' + key);
	elem.slideToggle();
	if (i.text() == '$hide') {
		i.text('#SHOW#');
	} else {
		elem.trigger('ap:fetch');
		i.text('#HIDE#');
	}
	return false;
}