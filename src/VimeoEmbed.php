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
              return sprintf("\n\n\n<!-- Network Error: %s (Vimeo embed) -->\n\n", $data->getMessage());
        }

        /**
         * Handle API errors (this happened)
         */
        if (property_exists($data, 'error')) {
            return sprintf("\n\n\n<!-- API Error: %s (Vimeo embed) -->\n\n", (@$data->developer_message2) ?: $data->error);
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
                $feed = \Requests::get("https://api.vimeo.com/me/videos/$videoID", $headers);
                $vimeoInfo = json_decode($feed->body);
                $vimeoInfo->id = $transientID;
                set_transient($transientID, $vimeoInfo, 60 * 60);   // store transient for 1 hour
            } catch (\Requests_Exception $e) {
                $vimeoInfo = $e;
            }
        }
        return $vimeoInfo;
    }
}
