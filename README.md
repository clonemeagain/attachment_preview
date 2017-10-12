# Attachment Preview
An [osTicket](https://github.com/osTicket/osTicket) plugin allowing inlining of Attachments

[![Build Status](https://travis-ci.org/clonemeagain/attachment_preview.svg?branch=master)](https://travis-ci.org/clonemeagain/attachment_preview)

#How it looks:
![agent_view](https://cloud.githubusercontent.com/assets/5077391/15166401/bedd01fc-1761-11e6-8814-178c7d4efc03.png)

## Current features:
- PDF Files attachments are embedded as full PDF `<object>` in the entry.
- Images inserted as normal `<img>` tags. Supported by most browsers: `png,jpg,gif,svg,bmp`
- Text files attachments are inserted into using `<pre>` (If enabled). 
- HTML files are filtered and inserted (If enabled). 
- Youtube links in comments can be used to create embedded `<iframe>` players. (if enabled)
- HTML5 Compatible video formats attached are embedded as `<video>` players. (if enabled)
- All modifications to the DOM are now performed on the server.
- Admin can choose the types of attachments to inline, and who can inline them.
- Default admin options are embed "PDF's & Images" only for "Agents".
- Plugin API: allows other plugins to manipulate the DOM without adding race conditions or multiple re-parses.

## To Install:
1. Simply `git clone https://github.com/clonemeagain/attachment_preview.git /includes/plugins/attachment_preview` Or extract [latest-zip](https://github.com/clonemeagain/attachment_preview/archive/master.zip) into /includes/plugins/attachment_preview
1. Navigate to: https://your.domain/support/scp/plugins.php?a=add to add a new plugin.
1. Click "Install" next to "Attachment Inline Plugin"
1. Now the plugin needs to be enabled & configured, so you should be seeing the list of currently installed plugins, pick the checkbox next to "Attachment Inline Plugin" and select the "Enable" button.
1. Now you can configure the plugin, click the link "Attachment Inline Plugin" and choose who the plugin should be activated for, or keep the default.

## To Remove:
Navigate to admin plugins view, click the checkbox and push the "Delete" button.

The plugin will still be available, you have deleted the config only at this point, to remove after deleting, remove the /plugins/attachment_preview folder.


# How it works:
Latest in [Wiki](https://github.com/clonemeagain/attachment_preview/wiki)

* Essentially it's simple, when enabled, and a ticket page is viewed, an output buffer is created on osTicket BootStrap which waits for the page to be finished rendering by osTicket. (Using php's register_shutdown_function & ob_start)
* When that's done, we fetch the output buffer and convert the HTML structure into a DOMDocument, pretty standard PHP so far.
* The plugin then runs through the link elements of the Document, to find all Attachments.
* It then adds a new DOMElement after the attachments section, inlining each attachment. PDF's become `<object>`'s, PNG's become `<img>` etc. 
Tested and works on 1.8-git. SHOULD work on future versions, depending on how the files are attached.. haven't tested on anything else though, let me know! 

The plugin is self-contained, so ZERO MODS to core are required. You simply clone the repo or download the zip from github and extract into /includes/plugins/ which should make a folder: "attachment_preview", but it could be called anything. 


# TODO:
- ??
- You tell me!
