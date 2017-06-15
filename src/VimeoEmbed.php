<?php

namespace ideasonpurpose;

/**
 * version: 0.0.0
 *
 * Shortcode takes arguments:
 * [vimeo 1234567]   -- straight embed, stretches to 100% width
 * [vimeo 1234567 loop] -- video tag embed, loops
 * [vimeo 1234567 autoplay] -- video tag embed, autoplay
 * [vimeo 1234567 loop autoplay] -- video tag embed, loops and autoplays
 * [vimeo 1234567 AutoPlay LOoP] -- same as above (order and case don't matter)
 * [vimeo 1234567 lightbox] -- standard embed plays in lightbox
 */
class VimeoEmbed
{
    /**
     * Initialize the library
     * @param [type] $token Auth token from https://developer.vimeo.com/apps/89792#authentication
     */
    public function __construct($auth_token)
    {
        $this->token = $auth_token;
        add_shortcode('vimeo', [$this, 'parseShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'loadLightboxAssets']);
    }

    public function loadLightboxAssets()
    {
        wp_enqueue_style('ekko-lightbox-styles', 'https://cdnjs.cloudflare.com/ajax/libs/ekko-lightbox/5.1.1/ekko-lightbox.min.css', [], null);
        // wp_enqueue_script('ekko-lightbox', 'https://cdnjs.cloudflare.com/ajax/libs/ekko-lightbox/5.1.1/ekko-lightbox.min.js', array('jquery'), '20120206', true);
        // wp_enqueue_script('ekko-lightbox', '/node_modules/ekko-lightbox/dist/ekko-lightbox.js', array('jquery'), '20120206', true);

        /*

        This code snippet should be injected to enable
const $ = window.jQuery;


require('../../vendor/ideasonpurpose/wp-vimeo-embed/src/js/ekko-lightbox');


$(document).on('click', '[data-toggle="lightbox"]', function(event) {
  event.preventDefault();
  $(this).ekkoLightbox({alwaysShowClose: false});
});



         */

    }

    /**
     * simple wrapper, throws errors if WP_DEBUG, otherwise prints an html comment
     * @param  string $err the error to maybe throw
     */
    public function throwError($err)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            throw new \Exception($err);
        } else {
            return sprintf("\n\n\n<!-- %s -->\n\n", $err);
        }
    }

    /**
     * returns a fully formed '<img src="" srcset="">' tag
     * @param  array $sizes the pictures->sizes array as returned from Vimeo
     *                      contains  width, height, link and link_with_play_button
     * @return string        fully formed srcset attribute
     */
    private function getImgSrcSetTag($vimeoInfo)
    {
        $maxWidth = array_reduce($vimeoInfo->pictures->sizes, function ($prev, $curr) {
            return max($prev, $curr->width);
        }, 0);
        $srcset = array_map(function ($i) {
            return sprintf("%s %dw", $i->link, $i->width);
        }, $vimeoInfo->pictures->sizes);
        return sprintf(
            '<img src="%1$s" alt="%2$s" width="%3$d", height="%4$d" srcset="%5$s", sizes="(max-width: %6$dpx) 100vw, %6$dpx">',
            $vimeoInfo->pictures->sizes[3]->link,
            htmlspecialchars($vimeoInfo->name, ENT_COMPAT, null, true),
            $vimeoInfo->pictures->sizes[3]->width,
            $vimeoInfo->pictures->sizes[3]->height,
            implode(', ', $srcset),
            $maxWidth
        );
    }

    /**
     * Returns a style tag and opening div tag
     * The tags look something like this:
     *     <style>
     *         #vimeo-embed-409a91ec3 {position: relative; overflow:hidden; max-width: 100%; height: 0;}
     *         #vimeo-embed-409a91ec3 iframe {position: absolute; top: 0; right: 0; bottom: 0; left: 0;}
     *     </style>
     *     <div id="vimeo-embed-409a91ec3" class="embed-container" style="padding-bottom: 56.25%;">
     */
    public function divStart($vimeoData, $includeStyle = true)
    {
        $id = '';
        $style = '';
        $padding = (is_numeric($vimeoData->width) && $vimeoData->width > 0) ? round($vimeoData->height/$vimeoData->width * 100, 5) : 0;

        if ($includeStyle) {
            $id = 'vimeo-embed-' . substr(md5($vimeoData->embed->html . microtime()), 0, 12);
            $style = "
                <style>
                    #$id {
                        position: relative;
                        overflow: hidden;
                        max-width: 100%%;
                        height: 0;
                        padding-bottom: $padding%;
                    }
                    #$id iframe,
                    #vimeo-embed-$id video {
                        position: absolute;
                        top: 0;
                        right: 0;
                        bottom: 0;
                        left: 0;
                    }
                </style>
            ";
            $id = "id=\"$id\" ";
        }
        $div = "
            <div ${id}class=\"embed-container\">
        ";
        $style = preg_replace(['/\s+/', '/\s?([{<>}])\s?/'], [' ', '$1'], $style);
        return $style . $div;
    }

    /**
     * Wrap Vimeo's oEmbed code snippet in a stretchy div
     */
    public function wrap($video)
    {
        $vimeoData = $this->getVimeoData($video);
        return $this->divStart($vimeoData) . $vimeoData->embed->html . '</div>';
    }

    /**
     * Embed an HTML5 video tag
     *     note: Autoplay also sets muted to true so videos autoplay on mobile devices
     * @param  string $video A blob or vimeo ID
     * @param  array  $args  An array of settings, [autoplay: true, loop: false]
     */
    public function embed($video, $args = [])
    {
        $vimeoData = $this->getVimeoData($video);
        d($vimeoData);
        $defaults = [
            'autoplay' => true,
            'loop' => true
        ];
        $config = array_merge($defaults, $args);

        $config['autoplay'] = ($config['autoplay']) ? 'autoplay muted playsinline' : '';
        $config['loop'] = $config['loop'] ? 'loop' : '';

        $output = sprintf(
            '<video %s %s data-pictures="%s" data-files="%s"></video>',
            $config['autoplay'],
            $config['loop'],
            htmlentities(json_encode($vimeoData->pictures->sizes), ENT_QUOTES, 'UTF-8'),
            htmlentities(json_encode($vimeoData->files), ENT_QUOTES, 'UTF-8')
        );

        return $this->divStart($vimeoData) . $output . '</div>';
    }

    /**
     * Output a thumbnail linked srcset img tag for use with the ekko-lightbox bootstrap plugin
     * @param  number $vID Vimeo id
     * @return string     html code
     */
    public function lightbox($video, $force16x9 = true)
    {
        $vimeoData = $this->getVimeoData($video);

        return sprintf('<a href="https://vimeo.com/%1$s" data-remote="https://player.vimeo.com/video/%1$s" data-toggle="lightbox" data-width="1280" >', $vimeoData->id) .
        // return sprintf('<a href="/wp-content/uploads/2017/01/MCB_1729-e1485526480348.jpg" data-toggle="lightbox" data-width="sm">', $vimeoData->id) .
        // return sprintf('<a href="http://vimeo.com/%1$s" data-remote="https://www.youtube.com/watch?v=ussCHoQttyQ" data-toggle="lightbox" data-width="1280" >', $vimeoData->id) .
        $this->getImgSrcSetTag($vimeoData) .
        '<div class="play-button"></div>' .
        '</a>';
    }

    public function parseShortcode($atts)
    {
        if (!$atts[0]) {
            return;
        }
        $vimeoId = array_shift($atts);  // first item shoud be an ID.
        $vimeoData = $this->getVimeoData($vimeoId);

        $atts = array_map('strtolower', $atts); // normalize attribute case

        $config = ['loop' => in_array('loop', $atts), 'autoplay' => in_array('autoplay', $atts)];
        // d($atts, $config);

        if (count($atts) < 1) {
            return $this->wrap($vimeoId);
        }
        return $this->embed($vimeoId, $config);

        // $loop = (in_array('loop', $atts)) ? 'loop' : '';
        // $autoplay = (in_array('autoplay', $atts)) ? 'autoplay' : '';

    }
    /**
    * Vimeo embed shortcode.
    * This is fluid and will scale with the page width.
    * Overrides the Vimeo embed from JetPack.
    */
    public function vimeoEmbed($atts)
    {
        if (!$atts[0]) {
            return;
        }
        $vID = array_shift($atts);
        $atts = array_map('strtolower', $atts); // normalize attribute case
        $data = $this->getVimeoData($vID);
        // d($data);

        $loop = (in_array('loop', $atts)) ? 'loop' : '';
        $autoplay = (in_array('autoplay', $atts)) ? 'autoplay' : '';

        $output = sprintf(
            '<div class="embed-container" style="padding-bottom: %.5f%%;">',
            $data->height/$data->width * 100
        );
        $output .= sprintf(
            '<video autoplay muted playsinline id="%s" data-pictures="%s" data-files="%s" %s></video>',
            $data->id,
            htmlentities(json_encode($data->pictures->sizes), ENT_QUOTES, 'UTF-8'),
            htmlentities(json_encode($data->files), ENT_QUOTES, 'UTF-8'),
            $loop
        );
        $output .= '</div>';

        return $output;
    }

    /**
     * Fetch and parse data from the Vimeo API
     * @param  numeric string $videoID The Vimeo video id
     * @return Object          parsed and optimized data
     */
    public function getVimeoData($videoID)
    {
        if (!is_numeric($videoID) && preg_match('#(?:https?://)?(?:www.)?(?:player.)?vimeo.com/(?:[a-z]*/)*([0-9]{6,11})[?]?.*#', $videoID, $match)) {
            $videoID = $match[1];
        }

        /**
         * Handle bad input
         */
        if (!$videoID) {
            return $this->throwError("VimeoEmbed Error: Unable to extract Vimeo ID from input");
        }

        /**
         * Set up WordPress Transient
         */
        $transientID = "vimeo_$videoID";
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     delete_transient($transientID);
        // }

        $vimeoInfo = get_transient($transientID);
        if ($vimeoInfo === false) {

            $vimeoInfo = $this->apiGet($videoID);
            $vimeoInfo->id = $videoID;
            $vimeoInfo->transient = $transientID;

            $transientMinutes = (defined('WP_DEBUG') && WP_DEBUG) ? 2 : 60; // 2 minutes for debug, 60 for production

            set_transient($transientID, $vimeoInfo, $transientMinutes * 60);

            /**
             * Handle API errors (this happened)
             * Example API error response:
             * $data = unserialize('O:8:"stdClass":5:{s:5:"error";s:21:"You have been banned.";s:4:"link";N;s:17:"developer_message";s:65:"You have been banned. Contact vimeo support for more information.";s:10:"error_code";i:3500;s:2:"id";s:15:"vimeo_180818116";}');
             */
            if (property_exists($vimeoInfo, 'error')) {
                return $this->throwError(sprintf("VimeoEmbed API Error: %s", (@$vimeoInfo->developer_message2) ?: $vimeoInfo->error));
            }
            /**
             * Handle missing data
             */
            if (!property_exists($vimeoInfo->pictures, 'sizes')) {
                return $this->throwError("VimeoEmbed API Error: Missing Files Array");
            }

        }
        return $vimeoInfo;
    }


    /**
     * encapsulating the API Request so we can mock the static method call
     * @param  string $id The Video ID
     * @return Object       returns decoded JSON blob from the API
     */
    public function apiGet($id)
    {
        $headers = [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $this->token
        ];
        try {
            $request = \Requests::get("https://api.vimeo.com/videos/$id", $headers);
        } catch (\Requests_Exception $e) {
            // Total network failure
            $this->throwError(sprintf("VimeoEmbed Network Error: %s", $vimeoInfo->getMessage()));
            $request = $e;
        }
        return json_decode($request->body);
    }
}
