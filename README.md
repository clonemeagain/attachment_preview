# Attachment Preview
An [osTicket](https://github.com/osTicket/osTicket) plugin allowing inlining of Attachments

#How it looks:
![agent_view](https://cloud.githubusercontent.com/assets/5077391/15166401/bedd01fc-1761-11e6-8814-178c7d4efc03.png)

## Current features:
- PDF Files attachments are embedded as full PDF `<object>` in the entry.
- Images inserted as normal `<img>` tags. Supported by most browsers: `png,jpg,gif,svg,bmp`
- Text files attachments are inserted into using `<pre>` (If enabled). 
- HTML files are filtered and inserted (If enabled). 
- Youtube links in comments can be used to create embedded `<iframe>` players. (if enabled)
- HTML5 Compatible video formats attached are embedded as `<video>` players. (if enabled)
- XLS/DOC files can be previewed inline using the Google Docs Embedded viewer. [info](http://googlesystem.blogspot.com.au/2009/09/embeddable-google-document-viewer.html)
- All modifications to the DOM are now performed on the server.
- Admin can choose the types of attachments to inline, and who can inline them.
- Default admin options are embed "PDF's & Images" only for "Agents".

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

It's pretty simple, when configured to work, and a tickets page is viewed, an output buffer is created on BootStrap which waits for the page to be finished rendering by osTicket.
When that's done, we flush the buffer and put the structure into a DOMDocument, pretty standard PHP so far.
We run through the link elements of the Document, see which are Attachments.
If we have admin permission to "inline the attachment", we add a new DOMElement after the attachments section, inlining the attachment. PDF's become `<object>`'s, PNG's become `<img>` etc. 
Tested and works on 1.8-git. SHOULD work on future versions, depending on how the files are attached.. haven't tested on anything else though, let me know! 
The plugin is self-contained, so ZERO MODS to core are required. You simply clone the repo or download the zip from github and extract into /includes/plugins/ which should make a folder: "attachment_preview", but it could be called anything. 


# TODO:
- ??