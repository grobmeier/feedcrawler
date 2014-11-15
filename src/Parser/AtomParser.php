<?php

namespace Dartosphere\FeedCrawler\Parser;

use DateTime;
use SimpleXMLElement;

use Dartosphere\FeedCrawler\Content\Author;
use Dartosphere\FeedCrawler\Content\Feed;
use Dartosphere\FeedCrawler\Content\Item;

/**
 * Extracts items from an Atom feed.
 */
class AtomParser extends Parser
{
    public function parse(SimpleXMLElement $xml)
    {
        $rootName = $xml->getName();
        if ($rootName !== 'feed') {
            throw new \Exception("Given XML root node is \"$rootName\". Expected \"feed\".");
        }

        $feed = new Feed();
        $feed->description = (string) $xml->subtitle;
        $feed->language = (string) $xml->language;
        $feed->lastBuildDate = (string) $xml->lastBuildDate;
        $feed->title = (string) $xml->title;
        $feed->slug = $this->getSlug($feed->title);

        // Author can be defined for the whole feed, or individually per entry
        $feedAuthor = $this->parseAuthor($xml);

        $items = [];
        foreach($xml->entry as $entry) {

            $item = new Item();
            $item->author = $this->parseAuthor($entry, $feedAuthor);
            $item->categories = $this->parseCategories($entry);
            $item->content = (string) $entry->content;
            $item->description = (string) $entry->summary;
            $item->link = $this->getLink($entry);
            $item->slug = $this->getSlug($entry->title);
            $item->time = $this->parseTime($entry);
            $item->title = (string) $entry->title;

            if (isset($entry->id)) {
                $item->id = (string) $entry->id;
            }

            $feed->items[] = $item;
        }

        return $feed;
    }

    private function parseAuthor(SimpleXMLElement $entry, $default = null)
    {
        if (isset($entry->author)) {
            $authors = [];
            foreach ($entry->author as $entryAuthor) {
                $author = new Author();
                $author->name = (string) $entryAuthor->name;
                $author->email = (string) $entryAuthor->email;
                $author->url = (string) $entryAuthor->uri;
                $authors[] = $author;
            }
            if (sizeOf($authors) == 1) {
                return current($authors);
            }
            return $authors;
        }

        return $default;
    }

    private function parseCategories(SimpleXMLElement $entry)
    {
        $categories = [];
        foreach($entry->category as $category) {
            $categories[] = (string) $category['term'];
        }
        return $categories;
    }

    /** Get the publish time for an entry. */
    private function parseTime(SimpleXMLElement $entry)
    {
        // Try publish time
        if (!empty($entry->published)) {
            return new DateTime($entry->published);
        }

        // Fall back to updated time (some feeds don't have published at all)
        if (!empty($entry->updated)) {
            return new DateTime($entry->updated);
        }

        $this->log->warn("Failed parsing time.");
        return null;
    }

    private function getLink($entry)
    {
        // Try <link href=".."> without a "rel" attribute
        foreach($entry->link as $link) {
            if (!isset($link['rel'])) {
                return (string) $link['href'];
            }
        }

        // Try <link href=".."> with rel="alternate"
        foreach($entry->link as $link) {
            if ($link['rel'] == 'alternate') {
                return (string) $link['href'];
            }
        }

        $this->log->warn("Failed parsing link");
        return null;
    }
}
