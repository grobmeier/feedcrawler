<?php

namespace Dartosphere\FeedCrawler\Parser;

use Dartosphere\FeedCrawler\LoggingTrait;

abstract class Parser
{
    use LoggingTrait;

    public abstract function parse(\SimpleXMLElement $xml);

    public function getSlug($title)
    {
        // Transliterate any non-ascii characters to ascii
        // This changes ö -> o, č -> c, etc.
        $slug = iconv("UTF-8", 'ASCII//TRANSLIT', $title);

        // Make slug lowercase
        $slug = strtolower($slug);

        // Translate unwanted characters
        $slug = strtr($slug, [
            ' ' => '-',
            '_' => '-',
            '&' => 'and',
        ]);

        // Remove any lefover non-url characters
        $slug = preg_replace('/[^a-z0-9_.-]/', '', $slug);

        // Replace multiple underscores with a single one
        $slug = preg_replace('/-{2,}/', '-', $slug);

        return $slug;
    }
}
