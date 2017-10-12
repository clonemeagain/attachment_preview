<?php

/**
 * I've started testing.. yay. 
 */
use PHPUnit\Framework\TestCase;

// Get some mocks
include_once 'setup.inc';

//TODO: Migrate this to phpunit!
// Now, we can test things!
// 
final class AttachmentPreviewPluginTest extends TestCase {

    private $plugin;

    protected function setUp() {
        // reset the mock
        $this->plugin = new AttachmentPreviewPlugin(1);
    }

// Let's start with the bottom, and work our way up.. phew!
    //public function testBootstrap(){
    //how to test? 
    //}
    //
    //skip 10 other private methods..  for now.
    //public function wrap()// fuck.

    /**
     * Test Youtube fetcher
     * @param type $url
     * @dataProvider getYoutubeUrls
     */
    public function testGetYoutubeIdFromUrl($url, $expectation) {
        $this->assertEquals($this->plugin->getYoutubeIdFromUrl($url), $expectation);
    }

    public function getYoutubeUrls() {
        return [
            ['https://www.youtube.com/watch?v=53nCql7VEXA', '53nCql7VEXA'],
            ['m.youtube.com/watch?v=53nCql7VEXA&app=desktop', '53nCql7VEXA']
        ];
    }

    //public function testGetDom()// who named this shit?
    //public function testPrintDom() // needs a dom..

    public function fakeApiUser() {
        // Need a remote structure to test with.. christ. 
        $dom                       = new DOMDocument();
        $script_element            = $dom->createElement('b');
        $script_element->nodeValue = 'FINDME';

        // Connect to the attachment_previews API wrapper and save the structure:
        Signal::send('attachments.wrapper', 'test', (object) [
                    'locator'    => 'tag', // References an HTML tag, in this case <body>
                    'expression' => 'body', // Append to the end of the body (persists through pjax loads of the container)
                    'element'    => $script_element
                ]
        );

        $html = '<!DOCTYPE html><html><head><title>Title!</title></head><body><p>Body!</p></body></html>';
    }

    // public function testAddArbitraryHtml(){} // really? 
    public function testAddArbitraryScript() {
        $script = 'var variable = 1;';
        AttachmentPreviewPlugin::add_arbitrary_script($script);

        $this->assertArrayHasKey('raw-script-1', AttachmentPreviewPlugin::$foreign_elements);
        $obj = reset(AttachmentPreviewPlugin::$foreign_elements['raw-script-1']);
        $this->assertInstanceOf(DOMElement::class, $obj->element);
    }

    // public function testAppendHtml(){} // pointless
    // public function testProcessRemoteElements(){} // christ
    //public function testUpdateStructure(){
    // hmm.. we'll need a DOMDocument structure, something to add to it
    // and a way of testing that it's updated.. fuck. 
    //}

    /** Test for getExtension($string)
     * @dataProvider getExtensionData
     */
    public function testGetExtension($filename, $expectation) {
        $this->assertEquals(AttachmentPreviewPlugin::getExtension($filename), $expectation);
    }

    public function getExtensionData() {
        return [
            ['test.php', 'php'],
            ['something.jpg', 'jpg'],
            ['ANtyHingsdf.asdfadf.234m,345,gdfd.F', 'f'],
            ['swear.words', 'words']
        ];
    }

    /**
     * Test for isTicketsView()
     * @dataProvider getUrls
     */
    public function testIsTicketsView($url, $expected) {
        $_SERVER['REQUEST_URI'] = $url;
        $this->assertSame(AttachmentPreviewPlugin::isTicketsView(), $expected);
    }

    /**
     * 
     * @return type
     * @dataProvider postUrls
     */
    public function testIsTicketsViewPost($url, $postdata, $expected) {
        global $_SERVER, $_POST;
        $_SERVER['REQUEST_URI'] = $url;
        $_POST                  = $postdata;
        $this->assertSame(AttachmentPreviewPlugin::isTicketsView(), $expected);
    }

    public function getUrls() {
        $b = 'https://tickets.dev/support/';

        return [
            [$b . 'index.php', FALSE],
            [$b . 'tickets.php', FALSE],
            [$b . 'scp/index.php', TRUE],
            [$b . 'scp/tickets.php', TRUE],
            [$b . 'scp/tickets.php?a=edit', FALSE],
            [$b . 'scp/tickets.php?a=print', FALSE],
            ['http://crazylongdomainnamethatreallyprobablyhopefullyisntinusebutactuallyyouknowwhatitjustmightbe.longasstld/someidiotpainfullylong/series/of/folders/threatening/to/make/the/url/longer/than/the/maximum/well/lets/be/honest/its/already/longer/than/anyone/would/want/to/type/support/scp/tickets.php?id=158279',
                TRUE]
        ];
    }

    public function postUrls() {
        $b = 'https://tickets.dev/support/';

        return [
            [$b . 'scp/tickets.php', ['a' => 'open'], TRUE],
            [$b . 'scp/anything.php', ['a' => 'anything'], FALSE],
        ];
    }

    /**
     *  Test for isPjax
     */
    public function testIsNotPjax() {
        $_SERVER['HTTP_X_PJAX'] = 'false';
        $this->assertSame(AttachmentPreviewPlugin::isPjax(), FALSE);
    }

    /**
     *  Test for isPjax
     */
    public function testIsPjax() {

        $_SERVER['HTTP_X_PJAX'] = 'true';
        $this->assertSame(AttachmentPreviewPlugin::isPjax(), TRUE);
    }

}
