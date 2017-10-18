<?php

namespace Axllent\WeblogWPImport\Extension;

use SilverStripe\Core\Extension;

/**
 * Convert embedded YouTube videos to <img class="placeholder" src="' . $link . '" alt="" width="x" height="x" />
 */
class EmbedYouTube extends Extension
{
    public function html_embed_youtube($html, $options = [])
    {
        $img_width = (!empty($options['set_image_width']) && is_numeric($options['set_image_width']))
            ? $options['set_image_width'] : 600;
        $img_height = round($img_width / 16 * 9);

        // [embed]https://www.youtube.com/watch?v=ABCdef[/embed]
        preg_match_all('/\[embed\](.*)\[\/embed\]/U', $html, $matches);
        if ($matches) {
            for ($x = 0; $x < count($matches[0]); $x++) {
                if ($link = $this->owner->extractYouTubeImageURL($matches[1][$x])) {
                    $html = str_replace(
                        $matches[0][$x],
                        '<img class="placeholder" src="' . $link . '" alt="" width="' . $img_width . '" height="' . $img_height . '" />',
                        $html
                    );
                }
            }
        }

        // [media url="https://www.youtube.com/watch?v=ABCdef" width="600" height="400"]
        preg_match_all('/\[media\s+url="(.*)".*\]/U', $html, $matches);
        if ($matches) {
            for ($x = 0; $x < count($matches[0]); $x++) {
                if ($link = $this->owner->extractYouTubeImageURL($matches[1][$x])) {
                    $html = str_replace(
                        $matches[0][$x],
                        '<img class="placeholder" src="' . $link . '" alt="" width="' . $img_width . '" height="' . $img_height . '" />',
                        $html
                    );
                }
            }
        }

        // [youtube id="ABCdef" width="600" height="350" autoplay="no" api_params="" class=""]
        // [fusion_youtube id="ABCdef" width="600" height="350" autoplay="no" api_params="" class=""/]
        preg_match_all('/\[(fusion\_youtube|youtube)\s+id="([a-zA-Z0-9\_\-]+)".*\]/U', $html, $matches);
        if ($matches) {
            for ($x = 0; $x < count($matches[0]); $x++) {
                $link = 'https://i.ytimg.com/vi/' . $matches[2][$x] . '/hqdefault.jpg';
                $html = str_replace(
                    $matches[0][$x],
                    '<img class="placeholder" src="' . $link . '" alt="" width="' . $img_width . '" height="' . $img_height . '" />',
                    $html
                );
            }
        }

        return $html;
    }

    /**
     * Parse string to return YouTube-hosted jpeg location
     * @param String
     * @return String
     */
    public function extractYouTubeImageURL($str)
    {
        $id = false;
        if (preg_match('/https?:\/\/youtu\.be\/([a-z0-9\_\-]+)/i', $str, $matches)) {
            $id = $matches[1];
        } elseif (preg_match('/youtu\.?be/i', $str)) {
            $query_string = array();
            parse_str(parse_url($str, PHP_URL_QUERY), $query_string);
            if (!empty($query_string['v'])) {
                $id = $query_string['v'];
            }
        }
        return $id ? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' : false;
    }
}
