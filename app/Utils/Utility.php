<?php

namespace FluentEvent\App\Utils;

class Utility
{
    public static function mdToHtml($markdown)
    {
        // Milkdown keeps a line's edge spaces as "&#x20;", which safe mode would print as text; fcom drops them too.
        $markdown = str_replace('&#x20;', '', (string) $markdown);

        // Safe mode escapes HTML typed into the markdown; kses then holds the rest to post HTML.
        $html = (new Parsedown())->setSafeMode(true)->setBreaksEnabled(true)->text($markdown);

        // The editor's image block keeps its size ratio in the alt ("0.43") and its caption in the title.
        $html = preg_replace_callback('/<img\b[^>]*>/', function ($match) {
            if (!preg_match('/ alt="(\d+(?:\.\d+)?)"/', $match[0], $alt)) {
                return $match[0];
            }

            $caption = preg_match('/ title="([^"]*)"/', $match[0], $title) ? $title[1] : '';
            // The editor centres the block at that share of the column; it reads 0 as 1 and never widens past the column.
            $width = round(min((float) $alt[1] ?: 1, 1) * 100);

            return str_replace($alt[0], ' alt="' . $caption . '" style="display: block; margin: 0 auto; max-width: ' . $width . '%;"', $match[0]);
        }, $html);

        return wp_kses_post($html);
    }
}
