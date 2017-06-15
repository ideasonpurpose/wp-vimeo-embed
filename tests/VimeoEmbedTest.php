<?php

namespace ideasonpurpose;

use PHPUnit\Framework\TestCase;

require_once(realpath(__DIR__ . '/../src/VimeoEmbed.php'));
require_once(realpath(__DIR__ . '/../vendor/rmccue/requests/library/Requests.php'));


function set_transient($id, $time)
{
    return true;
}
function delete_transient($id)
{
    return true;
}

// It should wrap Vimeo's embed code

// It should Create a video tag embed
// it should ignore case of loop and autoplay attributes
//
// it should embed a lightbox link
// t should include javascript files with lightbox
//
// it should make a Vimeo API request
// it should store the API response in a transient
// it should fail on network errors


class VimeoEmbedTest extends TestCase
{
    /**
     * Make sure tests are working
     */
    public function test()
    {
        $this->assertTrue(true);
    }

    /**
     * @before
     */
    public function setupStub()
    {
        global $stub;
        $stub = $this->getMockBuilder('ideasonpurpose\VimeoEmbed')
            ->disableOriginalConstructor()
            ->setMethods(['apiGet'])
            ->getMock();

        $stub->method('apiGet')
            ->willReturn((object)['name' => 'vimeo' ]);
    }

    /**
     * It should return strings that might be Vimeo IDs
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetVimeoIdFromVimeoID()
    {
        global $stub;
        function get_transient($id)
        {
            return false;
        }

        $this->assertEquals('123456', $stub->getVimeoData(123456)->id);
        $this->assertEquals('123456', $stub->getVimeoData('123456')->id);
        $this->assertEquals('notAnId', $stub->getVimeoData('notAnId')->id);
    }

    /**
     * It should extract a Vimeo ID from oEmbed code blobs
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetVimeoIdFromEmbedCode()
    {
        global $stub;
        function get_transient($id)
        {
            return false;
        }
        $oEmbedBlob = '<iframe src="https://player.vimeo.com/video/216711407" width="519" height="390" frameborder="0" title="Navigators 2016 Digital Annual Report" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
        $this->assertEquals('216711407', $stub->getVimeoData($oEmbedBlob)->id);

        $oEmbedBlob = '<iframe src="https://player.vimeo.com/video/2822787?color=ffffff&title=0&portrait=0" width="640" height="360" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
        $this->assertEquals('2822787', $stub->getVimeoData($oEmbedBlob)->id);
    }

    /**
     * It should throw an exception with bad data and WP_DEBUG set
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedException \Exception
     */
    public function testGetVimeoIdFailWithException()
    {
        define('WP_DEBUG', true);
        global $stub;
        $stub->getVimeoData(null);
        $stub->getVimeoData('');
    }

    /**
     * It should return the error wrapped in a comment
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
          */
    public function testGetVimeoIdFailWithComment()
    {
        global $stub;
        $this->assertRegExp('/<!--/', $stub->getVimeoData(null));
        $this->assertRegExp('/<!--/', $stub->getVimeoData(''));
        // TODO: Test for a malformed data set with a missing Vimeo->Pictures->sizes array
    }

    /**
     * It should catch Vimeo API Errors
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testVimeoAPIError()
    {
        function get_transient($id)
        {
            return false;
        }

        $stub = $this->getMockBuilder('ideasonpurpose\VimeoEmbed')
            ->disableOriginalConstructor()
            ->setMethods(['apiGet'])
            ->getMock();

        $stub->method('apiGet')
            ->willReturn((object)['pictures' => 'vimeo', 'error' => 'API Error' ]);

        $this->assertRegExp('/API Error/', $stub->getVimeoData(123));
    }


    /**
     * it should create a video tag embed with the loop attribute
     * it should create a video tag embed with the autoplay attribute
     * it should include muted and playsinline with the autoplay attribute
     */
    public function testHTML5Attributes()
    {
        $stub = $this->getMockBuilder('ideasonpurpose\VimeoEmbed')
            ->disableOriginalConstructor()
            ->setMethods(['getVimeoData', 'divStart'])
            ->getMock();

        $stub->method('getVimeoData')
            ->willReturn((object)['files' => 'files array', 'pictures' => (object) ['sizes' => 'pictures array'] ]);

        $this->assertRegExp('/autoplay/', $stub->embed(1234, ['autoplay' => true]));
        $this->assertNotRegExp('/autoplay/', $stub->embed(1234, ['autoplay' => false]));
        $this->assertRegExp('/muted/', $stub->embed(1234, ['autoplay' => true]));
        $this->assertRegExp('/playsinline/', $stub->embed(1234, ['autoplay' => true]));

        $this->assertRegExp('/loop/', $stub->embed(1234, ['loop' => true]));
        $this->assertNotRegExp('/loop/', $stub->embed(1234, ['loop' => false]));
    }

    public function testDivStart()
    {
        global $stub;
        $fakeData = (object)['width' => 16, 'height' => 9, 'embed' => (object) ['html' => 'html body']];
        $div = $stub->divStart($fakeData);
        $this->assertRegExp('/<style>/', $div);
        $this->assertRegExp('/<div id="vimeo-embed/', $div);
    }

    public function testDivStartNoStyle()
    {
        global $stub;
        $fakeData = (object)['width' => 16, 'height' => 9, 'embed' => (object) ['html' => 'html body']];
        $div = $stub->divStart($fakeData, false);
        $this->assertNotRegExp('/<style>/', $div);
        $this->assertNotRegExp('/<div id="vimeo-embed/', $div);
        $this->assertRegExp('/<div class="embed-container/', $div);
    }
}
