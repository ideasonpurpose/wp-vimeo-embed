<?php

namespace ideasonpurpose;

class VimeoEmbed
{
    /**
     * Initialize the library
     * @param [type] $token Auth token from https://developer.vimeo.com/apps/89792#authentication
     */
    public function __construct($auth_token)
    {
        $this->token = $auth_token;
        add_shortcode('vimeo', [$this, 'vimeoEmbed']);
        add_action('wp_enqueue_scripts',  [$this, 'loadLightbox']);

    }

    /**
     * simple wrapper, throws errors if WP_DEBUG, otherwise prints an html comment
     * @param  string $err the error to maybe throw
     */


    public function loadLightbox()
    {
        wp_enqueue_style('ekko-lightbox-styles', 'https://cdnjs.cloudflare.com/ajax/libs/ekko-lightbox/5.1.1/ekko-lightbox.min.css');
        // wp_enqueue_script('ekko-lightbox', 'https://cdnjs.cloudflare.com/ajax/libs/ekko-lightbox/5.1.1/ekko-lightbox.min.js', array('jquery'), '20120206', true);
        // wp_enqueue_script('ekko-lightbox', '/node_modules/ekko-lightbox/dist/ekko-lightbox.js', array('jquery'), '20120206', true);
    }

    private function throwError($err)
    {
        if (WP_DEBUG) {
            throw new \Error($err);
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
     * Output a thumbnail linked srcset img tag for use with the ekko-lightbox bootstrap plugin
     * @param  number $vID Vimeo id
     * @return string     html code
     */
    public function lightbox($vID, $force16x9 = true)
    {
        $data = $this->getVimeoData($vID);
        return sprintf('<a href="http://vimeo.com/%1$s" data-remote="http://player.vimeo.com/video/%1$s" data-toggle="lightbox" data-width="1280" >', $data->id) .
        // return sprintf('<a href="/wp-content/uploads/2017/01/MCB_1729-e1485526480348.jpg" data-toggle="lightbox" data-width="sm">', $data->id) .
        // return sprintf('<a href="http://vimeo.com/%1$s" data-remote="https://www.youtube.com/watch?v=ussCHoQttyQ" data-toggle="lightbox" data-width="1280" >', $data->id) .
        $this->getImgSrcSetTag($data) .
        '<div class="play-button"></div>' .
        '</a>';
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
        $vID = $atts[0];
        $data = $this->getVimeoData($vID);
        // Example API error response:
        // $data = unserialize('O:8:"stdClass":5:{s:5:"error";s:21:"You have been banned.";s:4:"link";N;s:17:"developer_message";s:65:"You have been banned. Contact vimeo support for more information.";s:10:"error_code";i:3500;s:2:"id";s:15:"vimeo_180818116";}');

        /**
         * Handle total network failure
         */
        if ($data instanceof \Requests_Exception) {
            return $this->throwError(sprintf("VimeoEmbed Network Error: %s", $data->getMessage()));
              // return sprintf("\n\n\n<!-- Network Error: %s (Vimeo embed) -->\n\n", $data->getMessage());
        }

        /**
         * Handle API errors (this happened)
         */
        if (property_exists($data, 'error')) {
            return $this->throwError(sprintf("VimeoEmbed API Error: %s", (@$data->developer_message2) ?: $data->error));
            // return sprintf("\n\n\n<!-- API Error: %s (Vimeo embed) -->\n\n", (@$data->developer_message2) ?: $data->error);
        }

        $output = sprintf(
            '<div class="embed-container" style="padding-bottom: %.5f%%;">',
            $data->height/$data->width * 100
        );
        $output .= sprintf(
            '<video autoplay muted playsinline id="%s" data-pictures="%s" data-files="%s"></video>',
            $data->id,
            htmlentities(json_encode($data->pictures->sizes), ENT_QUOTES, 'UTF-8'),
            htmlentities(json_encode($data->files), ENT_QUOTES, 'UTF-8')
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

        $transientID = "vimeo_$videoID";

        // don't store anything if debugging
        if (WP_DEBUG) {
            delete_transient($transientID);
        }

        $vimeoInfo = get_transient($transientID);
        if ($vimeoInfo === false) {
            $headers = [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ];
            try {
                $feed = \Requests::get("https://api.vimeo.com/videos/$videoID", $headers);
                $vimeoInfo = json_decode($feed->body);
                $vimeoInfo->id = $videoID;
                $vimeoInfo->transient = $transientID;
                set_transient($transientID, $vimeoInfo, 60 * 60);   // store transient for 1 hour
            } catch (\Requests_Exception $e) {
                $vimeoInfo = $e;
            }
        }
        return $vimeoInfo;
    }
}
