<?php

namespace ideasonpurpose;

use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

// use Requests;

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
    protected function setUp(): void
    {
        global $stub, $req;
        $stub = $this->getMockBuilder('ideasonpurpose\VimeoEmbed')
            ->disableOriginalConstructor()
            ->setMethods(['apiGet'])
            ->getMock();

        $stub->method('apiGet')->willReturn((object) ['name' => 'vimeo']);

        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('add_action')->justReturn(true);
        parent::setUp();
    }

    /**
     * Make sure tests are working
     */
    public function test()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
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
        $oEmbedBlob =
            '<iframe src="https://player.vimeo.com/video/216711407" width="519" height="390" frameborder="0" title="Navigators 2016 Digital Annual Report" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
        $this->assertEquals('216711407', $stub->getVimeoData($oEmbedBlob)->id);

        $oEmbedBlob =
            '<iframe src="https://player.vimeo.com/video/2822787?color=ffffff&title=0&portrait=0" width="640" height="360" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
        $this->assertEquals('2822787', $stub->getVimeoData($oEmbedBlob)->id);
    }

    /**
     * It should throw an exception with bad data and WP_DEBUG set
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetVimeoIdFailWithException()
    {
        define('WP_DEBUG', true);
        global $stub;
        $this->expectException(\Exception::class);
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
        $this->assertMatchesRegularExpression('/<!--/', $stub->getVimeoData(null));
        $this->assertMatchesRegularExpression('/<!--/', $stub->getVimeoData(''));
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
        define('WP_DEBUG', true);

        $stub = $this->getMockBuilder('ideasonpurpose\VimeoEmbed')
            ->disableOriginalConstructor()
            ->setMethodsExcept(['apiGet', 'throwError'])
            ->getMock();

        $mock = new MockHandler([
            new Response(200, [], '{"id": 123, "name":"test video"}'),
            new Response(500, [], 'testing'),
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ]);
        $handler = HandlerStack::create($mock);
        $stub->client = new Client(['handler' => $handler]);
        $stub->token = '123456789abcdefg';
        $this->assertIsObject($stub->apiGet('returns an object'));

        $this->expectException(\Exception::class);
        $stub->apiGet('500 error');

        $this->expectException(\Exception::class);
        $stub->apiGet('throw exception');
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
            // ->setMethods(['getVimeoData', 'divStart'])
            ->onlyMethods(['getVimeoData', 'divStart'])
            ->getMock();

        $stub->method('getVimeoData')->willReturn(
            (object) [
                'files' => 'files array',
                'pictures' => (object) ['sizes' => 'pictures array'],
            ]
        );

        $this->assertMatchesRegularExpression(
            '/autoplay/',
            $stub->embed(1234, ['autoplay' => true])
        );
        $this->assertDoesNotMatchRegularExpression(
            '/autoplay/',
            $stub->embed(1234, ['autoplay' => false])
        );
        $this->assertMatchesRegularExpression('/muted/', $stub->embed(1234, ['autoplay' => true]));
        $this->assertMatchesRegularExpression(
            '/playsinline/',
            $stub->embed(1234, ['autoplay' => true])
        );

        $this->assertMatchesRegularExpression('/loop/', $stub->embed(1234, ['loop' => true]));
        $this->assertDoesNotMatchRegularExpression('/loop/', $stub->embed(1234, ['loop' => false]));
    }

    public function testDivStart()
    {
        global $stub;
        $fakeData = (object) [
            'width' => 16,
            'height' => 9,
            'embed' => (object) ['html' => 'html body'],
        ];
        $div = $stub->divStart($fakeData);
        $this->assertMatchesRegularExpression('/<style>/', $div);
        $this->assertMatchesRegularExpression('/<div id="vimeo-embed/', $div);
    }

    public function testDivStartNoStyle()
    {
        global $stub;
        $fakeData = (object) [
            'width' => 16,
            'height' => 9,
            'embed' => (object) ['html' => 'html body'],
        ];
        $div = $stub->divStart($fakeData, false);
        $this->assertDoesNotMatchRegularExpression('/<style>/', $div);
        $this->assertDoesNotMatchRegularExpression('/<div id="vimeo-embed/', $div);
        $this->assertMatchesRegularExpression('/<div class="embed-container/', $div);
    }
}
