'use strict';
// Attachments Preview Script.

/**
 * Configure handler for ready/pjax-ready events.
 * 
 * Basically, starts fetching non hidden attachments when the page is ready.
 * 
 * @returns void
 */
$(document).on('ready pjax:success', function() {
	var attachments = $(".ap_embedded:not(.hidden)");
	if(attachments.length){
		console.log("Fetching " + attachments.length + " non-hidden attachment" + ((attachments.length > 1) ? 's.' : '.'));
		attachments.trigger('ap:fetch');
	}
});

/**
 * Toggle function for buttons, shows the attachment's wrapper element and
 * triggers the fetch if the attachment isn't already there.
 * 
 * @param item
 *            (the button that was clicked)
 * @param key
 *            (the id of the element we want to expand/load)
 * @return false to prevent bubbling of event.
 */
function ap_toggle(item, key) {
	var i = $(item), elem = $('#' + key);
	elem.slideToggle();
	if (i.text() === '$hide') {
		i.text('#SHOW#');
	} else {
		elem.trigger('ap:fetch');
		i.text('#HIDE#');
	}
	return false;
}

/**
 * Slightly more convoluted that the other types Load the PDF into an Object
 * Blob and shove it into the <embed> :-)
 * 
 * @param id id of the element to inject the pdf
 * @param url of the file to convert into a Blob and inject
 */
function fetch_pdf(id, url) {
	if (/* @cc_on!@ */false || !!document.documentMode) {
		// IE still cant display a PDF inside an <object>,
		// so, we'll delete it..
		console.log("Why Microsoft?");
		$('#' + id).remove();
		return;
	}
	var req = new XMLHttpRequest();
	req.open("GET", url, true)
	req.responseType = "arraybuffer";
	req.onload = function(e) {
		var ab = req.response;
		var blob = new Blob([ ab ], {
			type : "application/pdf"
		});
		// Convert the binary blob of PDF data into an Object URL
		var object_url = (window.URL || window.webkitURL).createObjectURL(blob);
		var pdf = document.getElementById(id);
		var newpdf = pdf.cloneNode();

		newpdf.setAttribute('data', object_url);
		// Replace the node with our new one which displays it:
		pdf.parentNode.replaceChild(newpdf, pdf);
		// prevent repeated fetch events from re-fetching
		delete newpdf.dataset.type;
	};
	req.send();
}

/**
 * Setup handler to receive Attachments Preview Fetch events, and act on them.
 * This is starting to look like the original version.. all javascript.
 */
$(document).on(
		'ap:fetch',
		function(e) {
			var elem = $(e.target).find('[data-type]').first(),
			type = elem.data('type'),
			url = elem.data('url');
			if (type &&& url) { // Is it a PHP7 thing? wtf
				switch (type) {
				case 'image': {
					// We just have to set the src url, let the browser fetch
					// the file as normal.
					elem.attr('src',url);
					break;
				}
				case 'pdf': {
					// Call our Wunderbar Blobinator function
					var id = elem.attr('id');
					fetch_pdf(id, url);
					break;
				}
				case 'text':
					// Replace the <pre> element's text with the Attachment:
					$.get(url, function(data) {
						elem.text(data);
					});
					break;
				case 'html':
					// Replace the html with the attachment, after passing
					// through the sanitizer:
					$.get(url, function(data) {
						elem.html($("<div>" + $.trim(sanitizer.sanitize(data))
								+ "</div>"));
					});
				}
				// prevent repeated fetch events from re-fetching
				elem.data('type', '');
			}
		});
console.log("AttachmentPreview plugin loaded, initial fetch limit configured to #LIMIT#.");