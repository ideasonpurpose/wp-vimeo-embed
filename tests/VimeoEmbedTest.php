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

}
