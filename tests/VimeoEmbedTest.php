<?php

namespace ideasonpurpose;

use PHPUnit\Framework\TestCase;

require_once(realpath(__DIR__ . '/../src/VimeoEmbed.php'));
// require_once('GoogleAnalyticsMocks.php');

// define('WP_DEBUG', true);

class VimeoEmbedTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWP_DEBUGTrue()
    {
        define('WP_DEBUG', true);
        $this->assertTrue(WP_DEBUG);
    }

    // It should wrap Vimeo's embed code

    // It should Create a video tag embed
    // it should create a video tag embed with the loop attribute
    // it should create a video tag embed with the autoplay attribute
    // it should ignore case of loop and autoplay attributes
    //
    // it should embed a lightbox link
    // it should fail on network errors
    // it should fail on API errors
}
