<?php

namespace Axllent\WeblogWPImport\Extension;

use SilverStripe\Core\Extension;

/**
 * Functions to clean data / remove elements
 */
class RemoveElements extends Extension
{
    public function html_remove_spans($html)
    {
        return preg_replace('/<\/?span[^>]*\>/i', '', $html);
    }

    public function html_remove_divs($html)
    {
        return preg_replace("/<\/?div[^>]*\>/i", '', $html);
    }

    public function html_clean_trim($html)
    {
        // Trim leading paragraphs
        $html = preg_replace('/^(\n?<p>&nbsp;<\/p>)+/', '', $html);
        // Trim trailing paragraphs
        $html = preg_replace('/(\n?<p>&nbsp;<\/p>)+$/', '', $html);
        return $html;
    }

    // Literally stripe out all [] shortcodes except for SS tags
    public function html_remove_shortcodes($html)
    {
        return preg_replace_callback('/\[[^\]]*\]/', function ($match) {
            if (preg_match('/^\[(image|sitetree\_link,id|file\_link,id)\b/', $match[0])) {
                return $match[0];
            }
            return '';
        }, $html);
    }
}
