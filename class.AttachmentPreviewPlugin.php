<?php
require_once (INCLUDE_DIR . 'class.plugin.php');
require_once (INCLUDE_DIR . 'class.file.php');
require_once (INCLUDE_DIR . 'class.format.php');
require_once ('config.php');

/**
 * Provides in-line attachments, and an interface to the DOM wrapper.
 *
 * Even when you don't give anyone permission to use inline attachments it will still provide
 * a back-end to the DOM, allowing you to write plugins that don't have to wrap the whole DOM yourself. :-)
 *
 * This is probably a good thing, so there isn't multiple competing DOM parsers & editors running sequentially..
 * If that occurred, then the DOM could conceivably be created from HTML, converted to a DOMDocument, back to HTML
 * repeatedly for every plugin that used this technique. Wanting to build several private plugins, I needed something
 * to do this, without compromising the main functionality of this plugin.
 *
 * In retrospect, perhaps this should be more of a "Library" plugin.. dunno. See what people say.
 *
 * // NOTE: by enforcing DOMElement objects only, I don't have to do any fancy conversions on my end.
 *
 *
 * It's great for injecting simple things, like a script, stylesheet, single widget, element etc..
 *
 * EG: To inject a script to the tickets pages for both agents and customers:
 * <?php
 *
 * // Create the new element to inject/replace existing
 * $dom = new DOMDocument();
 * $element = $dom->createElement('script');
 * $element->nodeContent = "var something = {type: 'Thing',}; alert('I can\'t believe that you\'ve done this!?');"; // The contents of the script.
 *
 * // Build a structure to send to the plugin with everything we should need.
 * $structure = (object)[
 * 'element' => $element, // The DOMElement to replace/inject etc.
 * 'locator' => 'tag', // EG: tag/id/xpath
 * 'replace_found' => FALSE, // default
 * 'setAtribute' => array('attribute_name' => 'attribute_value'), // not set by default.
 * 'expression' => 'body' // which tag/id/xpath etc.
 * ];
 * // Which pages do we operate on?
 * $sendable = array($structure); // You might wish to send multiple structures. You can send many changes in one go
 * Signal::send('attachments.wrapper', $this, $sendable);
 * ?>
 * *
 * Where $this is from your class (It's what class.signal.php says anyway.. not sure what I would do with it)
 * Where /REGEX/ is a valid SERVER_URI regex for which pages you want the structure appended.
 * Where 'TAG' is the type of match, either "tag" or "id", where "tag" matches ALL TAGS.. so, 'a' would append 'structure' to all <a> elements!
 * Obviously "id" is preferred. Put here so you can use <head> as a selector.
 * use it wisely. Essentially, if there is only ONE tag, that works. If there are many, I have to do them all.. it's only fair.
 *
 * For complicated queries, like those requiring XPath, you can also use "xpath" in your structure!
 * eg:
 * $structure = new STdClas'/some_regex/' => array('xpath' => '"//*[contains(@class, MySecretSauceClassName)]"', 'structure' => $element));
 * Signal::send('attachments.wrapper',$this,$structure);
 *
 * @return string
 */
class AttachmentPreviewPlugin extends Plugin {
	var $config_class = 'AttachmentPreviewPluginConfig';
	
	/**
	 * What signal are we going to connect to?
	 *
	 * @var unknown
	 */
	const signal_id = 'attachments.wrapper';
	static $foreign_elements;
	const DEBUG = FALSE;
	private $kb = FALSE;
	
	/**
	 * Required stub.
	 *
	 * {@inheritdoc}
	 *
	 * @see Plugin::uninstall()
	 */
	function uninstall() {
		$errors = array ();
		parent::uninstall ( $errors );
	}
	function bootstrap() {
		$config = $this->getConfig ();
		
		// Assuming that other plugins want to inject an element or two..
		// Provide a connection point to the attachments.wrapper
		Signal::connect ( self::signal_id, function ($object, $data) {
			if (self::DEBUG)
				error_log ( "Received connection from " . get_class ( $object ) );
			
			// We assume you've already checked the URI/permissions etc and want to edit the DOM with your structure.
			// ie: if($regex && preg_match($regex, $_SERVER['REQUEST_URI'])) {
			self::$foreign_elements [get_class ( $object )] = $data;
		} );
		
		// I'm assuming this won't get called if the plugin is disabled.
		$this->checkPermissionsAndRun ();
		if (self::DEBUG)
			error_log ( "Attachments Plugin Active." );
		
		if ($config->get ( 'coerce-pdf' )) {
			// Need to ensure attachments are saved with the correct mime-type. For some reason, a lot of my PDF's are coming through as octet-streams.. not useful.
			Signal::connect ( 'ticket.created', function ($ticket, $data) {
				// Find any attachments for this ticket
				// See if any of them are PDF's
				// Change the mimetype saved for that attachment..
				db_query ( "update " . FILE_TABLE . " SET type = 'application/pdf' WHERE name like '%.pdf' and type like '%octet-stream'" );
				// Well, it's a bit brutal the first time, but after that, should be quick.. :-)
			} );
		}
	}
	
	/**
	 * Plugin seems to want this.
	 */
	public function getForm() {
		return array ();
	}
	public function getSignalID() {
		return self::signal_id;
	}
	
	/**
	 * ********************************************************** Private Class Functions *******************
	 */
	private function checkPermissionsAndRun() {
		
		// Load our Admin defined settings..
		$config = $this->getConfig ();
		
		// See if the functionality has been enabled for Staff/Agents
		if (in_array ( $config->get ( 'attachment-enabled' ), array (
				'staff',
				'all' 
		) )) {
			$this->kb = $config->get ( 'attachment-enabled-kb' ) && $this->isKBView ();
			
			if ($this->kb) {
				print "<h1>YAY: KB page</h1>";
			}
			
			// Check what our URI is, if acceptable, add to the output.. :-)
			// Looks like there is no central router in osTicket yet, so I'll just parse REQUEST_URI
			// Can't go injecting this into every page.. we only want it for the actual ticket pages & Knowledgebase Pages
			if ($this->isTicketsView () || $this->kb) {
				// We could hack at core, or we can simply capture the whole page output and modify the HTML then..
				// Not "easier", but less likely to break core.. right?
				// There appears to be a few uses of ob_start in the codebase, but they stack, so it might work!
				ob_start ();
				
				// This will run after everything else, empty the buffer and run our code over the HTML
				// Then we send it to the browser as though nothing changed..
				register_shutdown_function ( function () {
					// Output the buffer
					// Check for Attachable's and print
					print $this->findAttachableStuff ( ob_get_clean () );
				} );
			} elseif (count ( self::$foreign_elements )) {
				// There appears to work to do as signalled.. This would otherwise be ignored as the shutdown handler
				// is nominally only initiated when enabled.. This allows other plugins to send it jobs. ;-)
				ob_start ();
				register_shutdown_function ( function () {
					print $this->doRemoteWork ( ob_get_clean () );
				} );
			}
		} elseif (count ( self::$foreign_elements )) {
			// There appears to work to do as signalled.. This would otherwise be ignored as the shutdown handler
			// is nominally only initiated when enabled.. This allows other plugins to send it jobs. ;-)
			ob_start ();
			register_shutdown_function ( function () {
				print $this->doRemoteWork ( ob_get_clean () );
			} );
		}
	}
	
	/**
	 * Builds a DOMDocument structure representing the HTML, checks the links within for Attachment'ness, then
	 * builds inline versions of those attachments and returns the HTML as a string.
	 *
	 * @param string $html        	
	 * @return string|unknown
	 */
	private function findAttachableStuff($html) {
		if (! $html) {
			// Something broke.. we can't even really recover from this, hopefully it wasn't our fault.
			return ''; // '<html><body><h3>:-(</h3><p>Not sure what happened.. something broke though.</p></body></html>';
		}
		
		// We'll need this..
		$config = $this->getConfig ();
		
		// decipher what the admin chose, also determines what method to run for each extension type.
		$pdf = array (
				'pdf' => 'addPDF' 
		);
		$office = array (
				'doc' => 'addGoogleDocsViewer',
				'docx' => 'addGoogleDocsViewer',
				// 'csv' => 'addGoogleDocsViewer',
				'xls' => 'addGoogleDocsViewer',
				'xlsx' => 'addGoogleDocsViewer',
				'ppt' => 'addGoogleDocsViewer',
				'pptx' => 'addGoogleDocsViewer',
				'tiff' => 'addGoogleDocsViewer' 
		);
		$images = array (
				'bmp' => 'addIMG',
				'svg' => 'addIMG',
				'gif' => 'addIMG',
				'png' => 'addIMG',
				'jpg' => 'addIMG',
				'jpeg' => 'addIMG' 
		);
		$higher_risk = array (
				'csv' => 'addTEXT',
				'txt' => 'addTEXT',
				'html' => 'addHTML' 
		);
		
		$audio = array (
				'wav' => 'addAudio',
				'mp3' => 'addAudio' 
		);
		
		$allowed_extensions = array ();
		
		// Merge the arrays together as per instruction..
		switch ($config->get ( 'attachment-allowed' )) {
			case 'all' :
				$allowed_extensions = $higher_risk + $pdf + $images + $office + $audio;
				break;
			case 'pdf' :
				$allowed_extensions = $pdf;
				break;
			case 'image' :
				$allowed_extensions = $images;
				break;
			case 'pdf-image' :
				$allowed_extensions = $pdf + $images;
				break;
			default :
				$allowed_extensions = array ();
		}
		
		// If the box is ticked, add em, if they want em all, assume they want video too..
		// This doesn't mean youtube will be embedded, but attachments will.
		if ($config->get ( 'attachment-video' ) || $config->get ( 'attachment-allowed' ) == 'all') {
			foreach ( array (
					'mp4',
					'ogv',
					'webm',
					'3gp',
					'flv' 
			) as $f ) {
				$allowed_extensions [$f] = 'addVideo';
			}
		}
		
		if (! count ( $allowed_extensions )) {
			// We've not been granted permission to change anything, so don't... just return original HTML.
			return $html;
		}
		
		// Let's not get regex happy.. we all have the tendency.. :-)
		// Instead, we'll be using the rather fast: DOMDocument class.
		$doc = new \DOMDocument ();
		@$doc->loadHTML ( $html );
		
		// Find all URLs: http://stackoverflow.com/a/29272222
		foreach ( $doc->getElementsByTagName ( 'a' ) as $link ) {
			
			// Check that the link even points to our attachment datasource
			if (preg_match ( '/file\.php/', $link->getAttribute ( 'href' ) )) {
				
				// Validate that the link is actually an attachment, it should have "file" class.. or "filename" in 1.10+
				// This doesn't work for knowledgebase articles.
				// if (strpos ( $link->getAttribute ( 'class' ), 'file' ) == FALSE) {
				// continue;
				// }
				
				// Luckily, the attachment link contains the filename.. which we can use!
				// Grab the extension of the file from the filename
				$ext = $this->getExtension ( $link->textContent );
				
				// See if admin allowed this extension
				if (! array_key_exists ( $ext, $allowed_extensions )) {
					continue;
				}
				// Run the associated method to add the attachment.
				$func = $allowed_extensions [$ext];
				if (method_exists ( $this, $func )) {
					// I'm generally not a fan of this style dynamic programming..
					// But it works, so why not.
					call_user_func ( array (
							$this,
							$func 
					), $doc, $link );
				}
			} elseif ($config->get ( 'attachment-video' )) {
				// This link isn't to /file.php & admin have asked us to check if it is a youtube link.
				// The overhead of checking strpos on every URL is less than the overhead of checking for a youtube ID!
				if (strpos ( $link->getAttribute ( 'href' ), 'youtub' ) !== FALSE) {
					$this->addYoutube ( $doc, $link );
				}
			}
		}
		
		// Before we return this, let's see if any foreign_elements have been provided by other plugins, we'll insert them.
		// This allows those plugins to edit this plugin.. seat-of-the-pants stuff!
		if (count ( self::$foreign_elements )) {
			return $this->processRemoteElements ( $doc );
		}
		
		return $doc->saveHTML ();
	}
	private function processRemoteElements(DOMDocument $dom) {
		// $this->foreign_elements should be an array of structures like:
		/**
		 * array('sourceClassName' => array(
		 * (object)[
		 * 'element' => $element, // The DOMElement to replace/inject etc.
		 * 'locator' => 'tag', // EG: tag/id/xpath
		 * 'replace_found' => FALSE, // default
		 * 'setAttribute' => array('attribute_name' => 'attribute_value'), // Not included by default, but great for adding tiny customizations.
		 * 'expression' => 'body' // which tag/id/xpath etc.
		 * ],
		 * (object2)[], etc.
		 * ));
		 */
		foreach ( self::$foreign_elements as $source => $structures ) {
			if (self::DEBUG)
				error_log ( "Loading remote structures from $source" );
			foreach ( $structures as $structure ) {
				// Validate the Structure
				try {
					if (! is_object ( $structure )) {
						continue; // just skip this fail.
					}
					
					if (! property_exists ( $structure, 'setAttribute' ) && (! property_exists ( $structure, 'element' ) || ! is_object ( $structure->element ) || ! $structure->element instanceof DOMElement)) {
						// What?
						throw new \Exception ( "Invalid or missing parameter 'element' from source {$source}." );
					}
					
					// Verify that the sender used a tag/id/xpath
					if (! property_exists ( $structure, 'locator' )) {
						throw new \Exception ( "Invalid or missing locator" );
					}
					if (! property_exists ( $structure, 'replace_found' )) {
						$structure->replace_found = FALSE;
					}
					
					if (! property_exists ( $structure, 'expression' )) {
						throw new \Exception ( "Invalid or missing expression" );
					}
					
					// Load the element(s) into our DOM, we can't insert them until then.
					if (! property_exists ( $structure, 'setAttribute' )) {
						// we aren't just changing an attribute, we are inserting new or replacing.
						$imported_element = $dom->importNode ( $structure->element, true );
					}
					
					// Based on type of DOM Selector, lets insert this imported element.
					switch ($structure->locator) {
						case 'xpath' :
							// TODO: Fix this.. doesn't seem to work
							$finder = new \DOMXPath ( $dom );
							$test = 0;
							foreach ( $finder->query ( $structure->expression ) as $node ) {
								$test ++;
								$this->update ( $node, $structure, $imported_element );
							}
							if (! $test) {
								throw new Exception ( "Nothing matched: {$structure->expression}" );
							}
							break;
						case 'id' :
							// Note, ID doesn't mean jQuery $('#id'); usefulness.. its xml:id="something", which none of our docs will have.
							$finder = new \DOMXPath ( $dom );
							$node = $finder->query ( "//*[@id='{$structure->expression}']" )->item ( 0 );
							if (! $node) {
								$this->log ( 'Unable to find node with expression {$structure->expression}' );
								continue;
							}
							$this->update ( $node, $structure, $imported_element );
							break;
						case 'tag' :
							foreach ( $dom->getElementsByTagName ( $structure->expression ) as $node ) {
								$this->update ( $node, $structure, $imported_element );
							}
							break;
						default :
							$this->log ( 'Invalid Locator specification', "Your locator from {$source} is invalid, {$structure->locator} has not been implemented. Available options are: xpath,id,tag" );
							continue;
					}
				} catch ( \Exception $de ) {
					$msg = $de->getMessage ();
					$this->log ( "{$source} triggered DOM error", $msg );
				}
			}
		}
		return $dom->saveHTML (); // yes, we end here.
	}
	private function update(\DOMElement $node, $structure, \DOMElement $imported_element = null) {
		if ($structure->replace_found) {
			$node->parentNode->replaceChild ( $node, $imported_element );
		} elseif ($structure->setAttribute) {
			foreach ( $structure->setAttribute as $key => $val ) {
				$node->setAttribute ( $key, $val );
			}
		} else {
			$node->appendChild ( $imported_element );
		}
	}
	private function log($title, $text = null) {
		// Log to system.
		global $ost;
		if (! $text) {
			$text = $title;
		}
		$ost->logWarning ( $title, $text );
	}
	private function doRemoteWork($html) {
		// We haven't actually been asked to run our code here, but the plugin users guild want to extend the wrapper
		// So, as a service to other plugins, we'll extend this functionality here.
		$dom = new \DOMDocument ();
		@$dom->loadHTML ( $html );
		return $this->processRemoteElements ( $dom );
	}
	
	/**
	 * Uses an Attached Image link and embeds the image into the DOM
	 *
	 * @param DOMDocument $doc        	
	 * @param DOMElement $link        	
	 */
	private function addIMG(DOMDocument $doc, DOMElement $link) {
		
		// Rebuild the download link as a normal clickable link, for full-size viewing:
		$a = $doc->createElement ( 'a' );
		$a->setAttribute ( 'href', $link->getAttribute ( 'href' ) );
		
		// Build an image of the referenced file, so we can simply preview it:
		$img = $doc->createElement ( 'img' );
		$img->setAttribute ( 'src', $link->getAttribute ( 'href' ) );
		$img->setAttribute ( 'style', 'max-width: 100%' );
		
		// Put the image inside the link, so the image is clickable (opens in new tab):
		$a->appendChild ( $img );
		
		// Add a title attribute to the download link:
		$link->setAttribute ( 'title', 'Download this image.' );
		
		$this->wrap ( $doc, $link, $a );
	}
	
	/**
	 * Uses an Attached PDF link and embeds the linked document into the DOM
	 *
	 * @param DOMDocument $doc        	
	 * @param DOMElement $link        	
	 */
	private function addPDF(DOMDocument $doc, DOMElement $link) {
		$pdf = $doc->createElement ( 'object' );
		$pdf->setAttribute ( 'width', '100%' );
		$pdf->setAttribute ( 'height', '1000px' );
		$pdf->setAttribute ( 'data', $link->getAttribute ( 'href' ) . '&disposition=inline' );
		$pdf->setAttribute ( 'type', 'application/pdf' );
		
		$emb = $doc->createElement ( 'embed' );
		$emb->setAttribute ( 'width', '100%' );
		$emb->setAttribute ( 'height', '1000px' );
		$emb->setAttribute ( 'src', $link->getAttribute ( 'href' ) . '&disposition=inline' );
		$emb->setAttribute ( 'type', 'application/pdf' );
		$pdf->appendChild ( $emb ); // for lower class browsers..
		
		$message = $doc->createElement ( 'b' );
		$message->nodeValue = "Your browser is unable to display this PDF.";
		$pdf->appendChild ( $message ); // For failing browsers.
		
		static $fix_css;
		if (! isset ( $fix_css )) {
			$fix_css = TRUE;
			// Create the new element to inject/replace existing
			$css = $doc->createElement ( 'style' );
			$css->setAttribute ( 'name', 'Plugin: Attachment Preview PDF Stylesheet' );
			$css->nodeValue = '.thread-body span.attachment-info { width: 100%; height: auto; }';
			$pdf->appendChild ( $css );
		}
		
		$this->wrap ( $doc, $link, $pdf );
	}
	
	/**
	 * Converts a link to Youtube player
	 *
	 * Fully loaded only, ie: <a src="youtube.com/v/12345">Link to youtube</a> only, not just a bare youtube URL.
	 *
	 * @param DOMDocument $doc        	
	 * @param DOMElement $link        	
	 */
	private function addYoutube(DOMDocument $doc, DOMElement $link) {
		$youtube_id = $this->getYoutubeIdFromUrl ( $link->getAttribute ( 'href' ) );
		if ($youtube_id !== FALSE) {
			// Now we can add an iframe so the video is instantly playable.
			// eg: <iframe width="560" height="349" src="http://www.youtube.com/embed/something?rel=0&hd=1" frameborder="0" allowfullscreen></iframe>
			// TODO: Make responsive.. if required.
			$player = $doc->createElement ( 'iframe' );
			$player->setAttribute ( 'width', '560' );
			$player->setAttribute ( 'height', '349' );
			$player->setAttribute ( 'src', 'http://www.youtube.com/embed/' . $youtube_id . '?rel=0&hd=1' );
			$player->setAttribute ( 'frameborder', 0 );
			$player->setAttribute ( 'allowfullscreen', 1 );
			$this->wrap ( $doc, $link, $player );
		}
	}
	/**
	 * Converts a linked audio file into an embedded HTML5 player.
	 *
	 * @param DOMDocument $doc        	
	 * @param DOMElement $link        	
	 */
	private function addAudio(DOMDocument $doc, DOMElement $link) {
		$audio = $doc->createElement ( 'audio' );
		// $audio->setAttribute('autoplay','false');
		// $audio->setAttribute('loop','false');
		$audio->setAttribute ( 'preload', 'auto' );
		$audio->setAttribute ( 'controls', 1 );
		$audio->setAttribute ( 'src', $link->getAttribute ( 'href' ) );
		$this->wrap ( $doc, $link, $audio );
	}
	
	/**
	 * Converts a linked video file into an embedded HTML5 player.
	 *
	 * @param DOMDocument $doc        	
	 * @param DOMElement $link        	
	 */
	private function addVideo(DOMDocument $doc, DOMElement $link) {
		$video = $doc->createElement ( 'video' );
		$video->setAttribute ( 'controls', 1 );
		$source = $doc->createElement ( 'source' );
		$source->setAttribute ( 'src', $link->getAttribute ( 'href' ) );
		$source->setAttribute ( 'type', 'video/' . $this->getExtension ( $link->textContent ) );
		$video->appendChild ( $source );
		$this->wrap ( $doc, $link, $video );
	}
	
	/**
	 * Fetches an HTML attachment entirely, and injects it into the DOM
	 *
	 * @param DOMDocument $doc        	
	 * @param DOMElement $link        	
	 */
	private function addHTML(DOMDocument $doc, DOMElement $link) {
		
		// The files are getting complex to parse manually.. and need to be downloaded by the browser to display anyway,
		// let's just try a wee script to pull them?
		// It's a bit messy, but the first script is only included once.. and it is used to prevent some XSS type attacks..
		// wouldn't want an html attachment to break everything..
		static $trim_func;
		if (! $trim_func) {
			$trim_func = TRUE;
			$t = $doc->createElement ( 'script' );
			$t->setAttribute ( 'name', 'Hand Sanitizer' );
			$t->nodeValue = <<<HANDSANITIZER
	// src: https://gist.github.com/ufologist/5a0da51b2b9ef1b861c30254172ac3c9
    var sanitizer = {};
    (function($) {
		var safe = '<a><b><blockquote><dd><div><dl><dt><em><h1><h2><h3><h4><i><img><li><ol><p><pre><s><sup><sub><strong><strike><ul><br><hr><table><th><tr><td><tbody><tfoot>';

	    function trimAttributes(node) {
	        $.each(node.attributes, function() {
	            var attrName = this.name;
	            var attrValue = this.value;
	           //	if (attrName.indexOf('on') == 0 || attrValue.indexOf('javascript:') == 0) {
				// we could filter the "script" attributes, or just purge them all.. 
	                $(node).removeAttr(attrName);
	           // }
	        });
	    }
	    sanitizer.sanitize = function(html) {
			html = strip_tags(html,safe);
	        var output = $($.parseHTML('<div>' + $.trim(html) + '</div>', null, false));	
	        output.find('*').each(function() {
	            trimAttributes(this);
	        });
	        return output.html();
	    }

		//http://locutus.io/php/strings/strip_tags/ filter the html to only those acceptable tags above
		function strip_tags (input, allowed) {
		  allowed = (((allowed || '') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('')
		  var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi
		  var commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi
		  return input.replace(commentsAndPhpTags, '').replace(tags, function ($0, $1) {
		    return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : ''
		  })
		}
    })(jQuery);
HANDSANITIZER;
			$doc->appendChild ( $t );
		}
		
		$url = $link->getAttribute ( 'href' );
		$id = md5 ( $url );
		$s = $doc->createElement ( 'script' );
		$s->setAttribute ( 'name', 'Attachment Fetching Script..' );
		
		// Find the parent of itself and replace it's contents (ie, this script) with the remote HTML file's code, after removing most of the cruft/dangerzone stuff:
		$s->nodeValue = '$(document).ready(function(){$.get("' . $url . '",function(data){$("#' . $id . '").html($("<div>" + $.trim(sanitizer.sanitize(data)) + "</div>"));});});';
		$d = $doc->createElement ( 'div' );
		$d->setAttribute ( 'id', $id );
		
		$d->appendChild ( $s );
		
		$this->wrap ( $doc, $link, $d );
		
		return;
		
		/*
		 *
		 * try {
		 * $url = $link->getAttribute ( 'href' );
		 * // Can't just "throw" html at some DOM, we'll need to construct a new DOM
		 * // And import the nodes from it into our current DOM. Wooo.
		 * $html_document = new \DOMDocument ();
		 * // Grab the data from the file, run it through a filter, then load it into the new DOM
		 * $html_contents = Format::sanitize ( $this->convertAttachmentUrlToFileContents ( $url ) );
		 * if (strlen ( $html_contents )) {
		 * @$html_document->loadHTML ( $html_contents );
		 * $node = $doc->importNode ( $html_document->documentElement, true );
		 * $this->wrap ( $doc, $link, $node );
		 * }
		 * } catch ( \Exception $e ) {
		 * // Likely, we'll get some form of "Wrong Document Exception"..
		 * $error_node = $doc->createElement ( 'span' );
		 * $error_node->nodeValue = 'Unable to import this attachment into the DOM.';
		 * $this->wrap ( $doc, $link, $error_node );
		 * }
		 */
	}
	
	/**
	 * Fetches a TEXT attachment entirely, and injects it into the DOM
	 *
	 * @param DOMDocument $doc        	
	 * @param DOMElement $link        	
	 */
	private function addTEXT(DOMDocument $doc, DOMElement $link) {
		$url = $link->getAttribute ( 'href' );
		$id = md5 ( $url );
		$s = $doc->createElement ( 'script' );
		// Use ajax to fetch the text file, insert it as the plaintext content of the script's parent node <pre>
		$s->nodeValue = '$(document).ready(function(){$.get("' . $url . '",function(data){$("#' . $id . '").text(data);});});';
		$pre = $doc->createElement ( 'pre' );
		$pre->setAttribute ( 'id', $id );
		$pre->appendChild ( $s );
		$this->wrap ( $doc, $link, $pre );
		
		return;
		
		/*
		 * $url = $link->getAttribute ( 'href' );
		 * $text_element = $doc->createElement ( 'pre' );
		 * // Don't even bother filtering the "html", just convert everything into plain text. See how it likes that!
		 * $text_element->nodeValue = htmlentities ( $this->convertAttachmentUrlToFileContents ( $url ), ENT_NOQUOTES );
		 * $this->wrap ( $doc, $link, $text_element );
		 */
	}
	
	/**
	 * Attempts to inject a google-doc-viewer iframe for an attached file
	 *
	 * Doesn't actually work, because the request uses the session to validate the user.. :-(
	 *
	 * Nice idea though
	 *
	 * @param DOMDocument $doc        	
	 * @param DOMElement $link        	
	 */
	private function disabled_addGoogleDocsViewer(DOMDocument $doc, DOMElement $link) {
		// Recreate something like: <iframe style="width: 900px; height: 900px;" src="http://docs.google.com/gview?url=urltoyourworddocument&embedded=true" height="240" width="320" frameborder="0"></iframe>
		$gdoc = $doc->createElement ( 'iframe' );
		$gdoc->setAttribute ( 'style', 'width: 100%; height: 1000px;' );
		$gdoc->setAttribute ( 'src', 'http://docs.google.com/gview?url=' . $link->getAttribute ( 'href' ) . '&embedded=true' );
		$gdoc->setAttribute ( 'height', '1000' );
		$gdoc->setAttribute ( 'width', '100%' );
		$gdoc->setAttribute ( 'frameborder', 0 );
		$this->wrap ( $doc, $link, $gdoc );
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
	private function convertAttachmentUrlToFileContents($url) {
		throw new \Exception ( "He's dead Jim.. :-(" );
		// Pulled from /file.php and adapted..
		// Attempt to validate.. just like /scp/file.php would.. damnit. They've changed it again!
	/**
	 * /scp/file.php
	 * $h=trim($_GET['h']);
	 * //basic checks
	 * if(!$h || strlen($h)!=64 //32*2
	 * || !($file=AttachmentFile::lookup(substr($h,0,32))) //first 32 is the file hash.
	 * || strcasecmp(substr($h,-32),md5($file->getId().session_id().$file->getHash()))) //next 32 is file id + session hash.
	 * die('Unknown or invalid file. #'.Format::htmlchars($_GET['h']));
	 *
	 * /file.php
	 *
	 * //Basic checks
	 * if (!$_GET['key']
	 * || !$_GET['signature']
	 * || !$_GET['expires']
	 * || !($file = AttachmentFile::lookup($_GET['key']))
	 * ) {
	 * Http::response(404, __('Unknown or invalid file'));
	 * }
	 * // Validate session access hash - we want to make sure the link is FRESH!
	 * // and the user has access to the parent ticket!!
	 * if ($file->verifySignature($_GET['signature'], $_GET['expires'])) {
	 * try {
	 * if (($s = @$_GET['s']) && strpos($file->getType(), 'image/') === 0)
	 * return $file->display($s);
	 *
	 * // Download the file..
	 * $file->download(@$_GET['disposition'] ?: false, $_GET['expires']);
	 * }
	 * catch (Exception $ex) {
	 * Http::response(500, 'Unable to find that file: '.$ex->getMessage());
	 * }
	 * }
	 *
	 * @var array $key
	 */
		/*
		 * this used to work..
		 * $key = array ();
		 * if (preg_match ( '/^.*\?key=(.+)&expires=(.+)&signature=(.+)$/i', $url, $key )) {
		 * list ( $key, $expires, $signature ) = $key;
		 * if ($file = AttachmentFile::lookup ( $key )) {
		 * if ($file->verifySignature ( $signature, $expires )) {
		 *
		 * // Hack
		 * return $file->getData(); // only works for small files.. :-(
		 *
		 * // Connect to the Storage backend
		 * $backend = $file->open ();
		 *
		 * // Load the file entirely (I imagine this is like file_get_contents, but abstracted)
		 * return $backend->read ();
		 * }
		 * }
		 * }
		 * return '';
		 */
	}
	
	/**
	 * Convenience function
	 * Just wanted a DOMElement without having to copy & paste..
	 * or repetitively appendChild..
	 * Constructs a <div> element to contain the new inlined attachment.
	 *
	 * @param unknown $doc        	
	 */
	private function wrap(DOMDocument $doc, DOMElement $source, DOMElement $new_child) {
		$wrapper = $doc->createElement ( 'div' );
		$wrapper->setAttribute ( 'class', 'embedded' );
		$wrapper->setAttribute ( 'style', 'max-width: 100%; height: auto; padding: 4px; border: 1px solid #C3D9FF; margin-top: 10px; margin-bottom: 10px !important;' );
		$wrapper->appendChild ( $new_child );
		$source->parentNode->appendChild ( $wrapper );
	}
	
	/**
	 * Get Youtube video ID from URL
	 *
	 * http://stackoverflow.com/a/9785191
	 *
	 * Looks painful, haven't tested the performance impact, however Admin can disable checking.
	 *
	 * @param string $url        	
	 * @return mixed Youtube video ID or FALSE if not found
	 */
	private function getYoutubeIdFromUrl($url) {
		$youtube_id = FALSE;
		$id = array ();
		if (preg_match ( '/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $id )) {
			$youtube_id = $id [1];
		} elseif (preg_match ( '/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $id )) {
			$youtube_id = $id [1];
		} elseif (preg_match ( '/youtube\.com\/v\/([^\&\?\/]+)/', $url, $id )) {
			$youtube_id = $id [1];
		} elseif (preg_match ( '/youtu\.be\/([^\&\?\/]+)/', $url, $id )) {
			$youtube_id = $id [1];
		} elseif (preg_match ( '/youtube\.com\/verify_age\?next_url=\/watch%3Fv%3D([^\&\?\/]+)/', $url, $id )) {
			$youtube_id = $id [1];
		} else {
			// not a youtube video
		}
		return $youtube_id;
	}
	
	/**
	 * Retrieve the file extension from a string in lowercase
	 *
	 * @param DOMElement $link        	
	 * @return string
	 */
	public static function getExtension($string) {
		return strtolower ( pathinfo ( $string, PATHINFO_EXTENSION ) );
	}
	
	/**
	 * We only want to inject when viewing tickets, not when EDITING tickets..
	 * or any other view.
	 *
	 * Available statically via: AttachmentPreviewPlugin::isTicketsView()
	 *
	 * @return bool whether or not current page is viewing a ticket.
	 */
	public static function isTicketsView() {
		// This ensures no matter how many plugins call this function, it only checks it once.
		static $tickets_view;
		
		// Only set the $tickets_view if we've not set it (Will not last beyond each page anyway, but you never know, I call this from my plugins too)
		if (! isset ( $tickets_view )) {
			if (isset ( $_POST ) && count ( $_POST ) > 0) {
				// If something has been POST'd to osTicket, we don't want any part of that.
				// We're obviously not just "Viewing" a ticket if we've posted something, you only post to change!
				// Resolves issue #3 regarding printing of tickets.
				$tickets_view = FALSE;
			} else {
				// Matches /support/scp/tickets.php?id=12345 etc, as well as
				// /support/scp/tickets.php?id=12345#reply
				// BUT NOT /support/scp/tickets.php?id=12345&a=edit
				$tickets_view = (preg_match ( '/scp\/(?:(index|tickets)\.php)?(\?id=[\d]+)?(#[a-z_]+)?(?!&a=edit)?(\?status=[a-z]+)?((\?|\&)p=[1-9]+)?(?:&_pjax.*)?$/i', $_SERVER ['REQUEST_URI'] ));
			}
		}
		return $tickets_view;
	}
	
	/**
	 * Determine if we are viewing a KnowledgeBase article.
	 *
	 * Available statically via: AttachmentPreviewPlugin::isKBView()
	 *
	 * @return bool whether or not current page is viewing a KB.
	 */
	public static function isKBView() {
		// This ensures no matter how many plugins call this function, it only checks it once.
		static $kb_view;
		
		// Only set the $kb_view if we've not set it (Will not last beyond each page anyway, but you never know, I call this from my plugins too)
		if (! isset ( $kb_view )) {
			if (isset ( $_POST ) && count ( $_POST ) > 0) {
				// If something has been POST'd to osTicket, we don't want any part of that.
				// We're obviously not just "Viewing" a kb if we've posted something, you only post to change!
				$kb_view = FALSE;
			} else {
				// Matches /support/scp/faq.php?id=12345 etc & /support/faq.php
				$kb_view = (preg_match ( '/\/faq\.php(\?id=[\d]+)?(?!&_pjax.*)(?!edit)$/i', $_SERVER ['REQUEST_URI'] ));
			}
		}
		return $kb_view;
	}
}
