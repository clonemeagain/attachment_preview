'use strict';
// Attachment Preview Script.

// Setup default config options
var sanitizer = sanitizer || {}, pluginConfig = {
    open_attachments: 'normal',
    text_hide: 'Hide Attachment',
    text_show: 'Show Attachment',
    limit: 'No limit'
};

// Leave the next line intact, as the plugin will replace it with settings overriding the defaults above.
/* REPLACED_BY_PLUGIN */

/**
 * Configure handler for ready/pjax-ready events.
 * 
 * Basically, starts fetching non hidden attachments when the page is ready.
 * 
 * @returns void
 */
$(document).on('ready pjax:success', function () {
    var attachments = $(".ap_embedded:not(.hidden)");
    if (attachments.length) {
        console.log("Fetching " + attachments.length + " non-hidden attachment[s].");
        attachments.trigger('ap:fetch');
    }
    if (pluginConfig.open_attachments === 'new-tab') {
        $('a.file').prop('target', '_blank');
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
    if (i.text() === pluginConfig.text_hide) {
        i.text(pluginConfig.text_show);
    } else {
        elem.trigger('ap:fetch');
        i.text(pluginConfig.text_hide);
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
    var pdf = document.getElementById(id);
    if (/* @cc_on!@ */false || !!document.documentMode) {
        // IE still cant display a PDF inside an <object>
        console.log("Why Microsoft?");
        
        // Fetch the "you suck IE" element inside the <object> and replace
        // the object with it:
        var b = $(pdf).contents();
        $(pdf).replaceWith(b);
        return;
    }
    var req = new XMLHttpRequest();
    req.open("GET", url, true);
    req.responseType = "arraybuffer";
    req.onload = function () {
        var ab = req.response;
        var blob = new Blob([ab], {
            type: "application/pdf"
        });
        // Convert the binary blob of PDF data into an Object URL
        var object_url = (window.URL || window.webkitURL).createObjectURL(blob);
        if (!object_url) {
            return;
        }

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
 * Events are triggered by the buttons inserted for hidden attachment previews via ap_toggle
 */
$(document).on('ap:fetch', function (e) {
    var elem = $(e.target).find('[data-type]').first(),
            type = elem.data('type'),
            url = elem.data('url');

    if (type && url) {
        switch (type) {
            case 'image':
            {
                // We just have to set the src url, let the browser fetch
                // the file as normal.
                elem.attr('src', url);
                break;
            }
            case 'pdf':
            {
                // Call our Wunderbar Blobinator function
                var id = elem.attr('id');
                fetch_pdf(id, url);
                break;
            }
            case 'text':
                // Replace the <pre> element's text with the Attachment:
                $.get(url, function (data) {
                    elem.text(data);
                });
                break;
            case 'html':
                // Replace the html with the attachment, after passing
                // through the sanitizer:
                $.get(url, function (data) {
                    elem.html($("<div>" + $.trim(sanitizer.sanitize(data))
                            + "</div>"));
                });
        }
        // prevent repeated fetch events from re-fetching
        elem.data('type', '');
    }
});
console.log("AttachmentPreview plugin loaded, initial fetch limit configured to " + pluginConfig.limit + ".");