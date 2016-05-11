<?php
require_once (INCLUDE_DIR . 'class.plugin.php');
require_once (INCLUDE_DIR . 'class.file.php');
require_once (INCLUDE_DIR . 'class.format.php');
require_once ('config.php');

class AttachmentPreviewPlugin extends Plugin
{

    var $config_class = 'AttachmentPreviewPluginConfig';

    /**
     * Required stub.
     *
     * {@inheritDoc}
     *
     * @see Plugin::uninstall()
     */
    function uninstall()
    {
        $errors = array();
        parent::uninstall($errors);
    }

    function bootstrap()
    {
        // I'm assuming this won't get called if the plugin is disabled.
        $this->checkPermissionsAndRun();
    }

    /**
     * Plugin seems to want this.
     */
    public function getForm()
    {
        return array();
    }

    /**
     * ********************************************************** Private Class Functions *******************
     */
    private function checkPermissionsAndRun()
    {

        // Load our Admin defined settings..
        $config = $this->getConfig();

        // See if the functionality has been enabled for Staff/Agents
        if (in_array($config->get('attachment-enabled'), array(
            'staff',
            'all'
        ))) {
            // Check what our URI is, if acceptable, add to the output.. :-)
            // Looks like there is no central router in osTicket yet, so I'll just parse REQUEST_URI
            // Can't go injecting this into every page.. we only want it for the actual ticket pages
            if (preg_match('/\/tickets\.php/i', $_SERVER['REQUEST_URI'])) {
                // We could hack at core, or we can simply capture the whole page output and modify the HTML then..
                // Not "easier", but less likely to break core.. right?
                // There appears to be a few uses of ob_start in the codebase, but they stack, so it might work!
                ob_start();

                // This will run after everything else, empty the buffer and run our code over the HTML
                // Then we send it to the browser as though nothing changed..
                register_shutdown_function(function () {
                    // Output the buffer
                    // Check for Attachable's and print
                    print $this->findAttachableStuff(ob_get_clean());
                });
            }
        }
    }

    /**
     * Builds a DOMDocument structure representing the HTML, checks the links within for Attachment'ness, then
     * builds inline versions of those attachments and returns the HTML as a string.
     *
     * @param string $html
     * @return string|unknown
     */
    private function findAttachableStuff($html)
    {
        if (! $html) {
            // Something broke.. we can't even really recover from this, hopefully it wasn't our fault.
            return '<html><body><h3>:-(</h3><p>Not sure what happened.. something broke though.</p></body></html>';
        }

        // We'll need this..
        $config = $this->getConfig();

        // decipher what the admin chose, als determines what method to run for each extension type.
        $pdf = array(
            'pdf' => 'addPDF'
        );
        $images = array(
            'bmp' => 'addIMG',
            'svg' => 'addIMG',
            'gif' => 'addIMG',
            'png' => 'addIMG',
            'jpg' => 'addIMG',
            'jpeg' => 'addIMG'
        );
        $higher_risk = array(
            'txt' => 'addTEXT',
            'html' => 'addHTML'
        );
        $allowed_extensions = array();

        // Merge the arrays together as per instruction..
        switch ($config->get('attachment-allowed')) {
            case 'all':
                $allowed_extensions = $higher_risk + $pdf + $images;
                break;
            case 'pdf':
                $allowed_extensions = $pdf;
                break;
            case 'image':
                $allowed_extensions = $images;
                break;
            case 'pdf-image':
                $allowed_extensions = $pdf + $images;
                break;
            default:
                $allowed_extensions = array();
        }

        if (! count($allowed_extensions)) {
            // We've not been granted permission to change anything, so don't... just return original HTML.
            return $html;
        }

        // Let's not get regex happy.. we all have the tendency.. :-)
        // Instead, we'll be using the rather fast: DOMDocument class.
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);

        // Find all URLs: http://stackoverflow.com/a/29272222
        foreach ($doc->getElementsByTagName('a') as $link) {

            // Check that the link even points to our attachment datasource
            if (preg_match('/file\.php/', $link->getAttribute('href'))) {

                // Validate that the link is actually an attachment, it should have "file" class..
                if (strpos($link->getAttribute('class'), 'file') == FALSE) {
                    continue;
                }

                // Luckily, the attachment link contains the filename.. which we can use!
                // Grab the extension of the file from the filename
                $ext = strtolower(substr(strrchr($link->textContent, '.'), 1));

                // See if admin allowed this extension
                if (! array_key_exists($ext, $allowed_extensions)) {
                    continue;
                }
                // Run the associated method to add the attachment.
                $func = $allowed_extensions[$ext];
                if (method_exists($this, $func)) {
                    // I'm generally not a fan of this style dynamic programming..
                    // But it works, so why not.
                    call_user_method($func, $this, $doc, $link);
                }
            } elseif ($config->get('attachment-video')) {
                // This link isn't to /file.php & admin have asked us to check if it is a youtube link.
                // The overhead of checking strpos on every URL is less than the overhead of checking for a youtube ID!
                if (strpos($link->getAttribute('href'), 'youtub') !== FALSE) {
                    $this->addVID($doc, $link);
                }
            }
        }
        return $doc->saveHTML();
    }

    /**
     * Uses an Attached Image link and embeds the image into the DOM
     *
     * @param DOMDocument $doc
     * @param DOMElement $link
     */
    private function addIMG(DOMDocument $doc, DOMElement $link)
    {
        $img = $doc->createElement('img');
        $img->setAttribute('src', $link->getAttribute('href'));
        $img->setAttribute('style', 'max-width: 100%');
        $this->wrap($doc, $link, $img);
    }

    /**
     * Uses an Attached PDF link and embeds the linked document into the DOM
     *
     * @param DOMDocument $doc
     * @param DOMElement $link
     */
    private function addPDF(DOMDocument $doc, DOMElement $link)
    {
        $pdf = $doc->createElement('object');
        $pdf->setAttribute('width', '100%');
        $pdf->setAttribute('height', '1000px');
        $pdf->setAttribute('data', $link->getAttribute('href'));
        $pdf->setAttribute('type', 'application/pdf');
        $message = $doc->createElement('b');
        $message->nodeValue = "Your browser is unable to display this PDF.";
        $pdf->appendChild($message);
        $this->wrap($doc, $link, $pdf);
    }

    /**
     * Converts a link to Youtube player
     *
     * Fully loaded only, ie: <a src="youtube.com/v/12345">Link to youtube</a> only, not just a bare youtube URL.
     *
     * @param DOMDocument $doc
     * @param DOMElement $link
     */
    private function addVID(DOMDocument $doc, DOMElement $link)
    {
        if ($youtube_id = $this->getYoutubeIdFromUrl($link->getAttribute('href')) !== FALSE) {
            // Now we can add an iframe so the video is instanly playable.
            // eg: <iframe width="560" height="349" src="http://www.youtube.com/embed/something?rel=0&hd=1" frameborder="0" allowfullscreen></iframe>
            // TODO: Make responsive.. if required.
            $player = $doc->createElement('iframe');
            $player->setAttribute('width', '560');
            $player->setAttribute('height', '349');
            $player->setAttribute('src', 'http://www.youtube.com/embed/' . $youtube_id . '?rel=0&hd=1');
            $player->setAttribute('frameborder', 0);
            $player->setAttribute('allowfullscreen', 1);
            $this->wrap($doc, $link, $player);
        }
    }

    /**
     * Fetches an HTML attachment entirely, and injects it into the DOM
     *
     * @param DOMDocument $doc
     * @param DOMElement $link
     */
    private function addHTML(DOMDocument $doc, DOMElement $link)
    {
        try {
            $url = $link->getAttribute('href');
            $raw = $this->convertAttachmentUrlToFileContents($url);
            $formatter = new Format();
            // Can't just "throw" html at some DOM, we'll need to construct a new DOM
            // And import the nodes from it into our current DOM. Wooo.
            $html_document = new \DOMDocument();
            $html_document->loadHTML($formatter->safe_html($raw));
            $node = $doc->importNode($html_document->documentElement, true);
            $this->wrap($doc, $link, $node);
        } catch (\Exception $e) {
            // Likely, we'll get some form of "Wrong Document Exception"..
            $error_node = $doc->createElement('span');
            $error_node->nodeValue = 'Unable to import this attachment into the DOM.';
            $this->wrap($doc, $link, $error_node);
        }
    }

    /**
     * Fetches a TEXT attachment entirely, and injects it into the DOM
     *
     * @param DOMDocument $doc
     * @param DOMElement $link
     */
    private function addTEXT(DOMDocument $doc, DOMElement $link)
    {
        $url = $link->getAttribute('href');
        $raw = $this->convertAttachmentUrlToFileContents($url);
        $formatter = new Format();
        $text_element = $doc->createElement('pre');
        $text_element->nodeValue = $formatter->display($raw);
        $this->wrap($doc, $link, $text_element);
    }

    /**
     * Verifies that the URL we have encountered is a valid file..
     * Not sure if this is some security thing or what, but it looks vaugely like it might be.
     *
     * At the very least, it ensures that expirable links haven't expired
     *
     * @param unknown $url
     * @return unknown|string
     */
    private function convertAttachmentUrlToFileContents($url)
    {
        // Pulled from /file.php and adapted..
        // Attempt to validate.. just like file.php would
        $key = array();
        if (preg_match('/^.*\?key=(.+)&expires=(.+)&signature=(.+)$/i', $url, $key)) {
            list (, $key, $expires, $signature) = $key;
            if ($file = AttachmentFile::lookup($key)) {
                if ($file->verifySignature($signature, $expires)) {

                    // Connect to the Storage backend
                    $backend = $file->open();

                    // Load the file entirely (I imagine this is like file_get_contents, but abstracted)
                    return $backend->read();
                }
            }
        }
        return '';
    }

    /**
     * Convenience function
     * Just wanted a DOMElement without having to copy & paste..
     * or repetitively appendChild..
     * Constructs a <div> element to contain the new inlined attachment.
     *
     * @param unknown $doc
     */
    private function wrap(DOMDocument $doc, DOMElement $source, DOMElement $new_child)
    {
        $wrapper = $doc->createElement('div');
        $wrapper->setAttribute('class', 'embedded');
        $wrapper->setAttribute('style', 'max-width: 100%; height: auto; padding: 4px; border: 1px solid #C3D9FF; margin-top: 10px; margin-bottom: 10px !important;');
        $wrapper->appendChild($new_child);
        $source->parentNode->appendChild($wrapper);
    }

    /**
     * Get Youtube video ID from URL
     *
     * http://stackoverflow.com/a/9785191
     *
     * Looks painful, haven't tesetd the performance impact, however Admin can disable checking.
     *
     * @param string $url
     * @return mixed Youtube video ID or FALSE if not found
     */
    private function getYoutubeIdFromUrl($url)
    {
        $youtube_id = FALSE;
        $id = array();
        if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $id)) {
            $youtube_id = $id[1];
        } elseif (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $id)) {
            $youtube_id = $id[1];
        } elseif (preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $url, $id)) {
            $youtube_id = $id[1];
        } elseif (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $id)) {
            $youtube_id = $id[1];
        } elseif (preg_match('/youtube\.com\/verify_age\?next_url=\/watch%3Fv%3D([^\&\?\/]+)/', $url, $id)) {
            $youtube_id = $id[1];
        } else {
            // not a youtube video
        }
        return $youtube_id;
    }
}
