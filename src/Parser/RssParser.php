<?php

namespace Dartosphere\FeedCrawler\Parser;

use DateTime;

use SimpleXMLElement;

use Dartosphere\FeedCrawler\Content\Feed;
use Dartosphere\FeedCrawler\Content\Item;
use Dartosphere\FeedCrawler\Content\Author;

/**
 * Extracts items from an RSS feed.
 */
class RssParser extends Parser
{
    public function parse(SimpleXMLElement $xml)
    {
        $rootName = $xml->getName();
        if ($rootName !== 'rss') {
            throw new \Exception("Given XML root node is \"$rootName\". Expected \"rss\".");
        }

        $feed = new Feed();
        $feed->description = (string) $xml->channel->description;
        $feed->language = (string) $xml->channel->language;
        $feed->lastBuildDate = (string) $xml->channel->lastBuildDate;
        $feed->title = (string) $xml->channel->title;
        $feed->slug = $this->getSlug($xml->channel->title);

        $items = [];
        foreach($xml->channel->item as $entry) {
            $pub = new DateTime($entry->pubDate);

            $item = new Item();
            $item->author = $this->parseAuthor($entry);
            $item->categories = $this->parseCategories($entry);
            $item->content = $this->parseContent($entry);
            $item->description = (string) $entry->description;
            $item->link = (string) $entry->link;
            $item->slug = $this->getSlug($entry->title);
            $item->time = new DateTime($entry->pubDate);
            $item->title = (string) $entry->title;

            if (isset($entry->id)) {
                $item->id = (string) $entry->id;
            }

            $feed->items[] = $item;
        }

        return $feed;
    }

    /**
     * Extracts the Author from a given entry.
     * @param SimpleXMLElement $entry
     * @return Author
     */
    private function parseAuthor(SimpleXMLElement $entry)
    {
        // Try parsing <author>
        // The pattern is either:
        //   - email@site.com
        //   - email@site.com (Name Surname)
        if (!empty($entry->author)) {
            $string = trim($entry->author);
            $pattern = implode('', [
                '/^',
                '([^(]+)',  // Everything from the beginning of string to open parenthesis is the email
                '(\((.+)\))?', // Name within parenthesis - optional
                '/'
            ]);

            $author = new Author();
            if (preg_match($pattern, $string, $matches)) {
                $author->email = trim($matches[1]);
                $author->name = isset($matches[3]) ? trim($matches[3]) : null;
            } else {
                $this->log()->warn("Failed parsing RSS author \"$string\"");
           }
            return $author;
        }

        // Try <dc:creator>
        $namespaces = $entry->getNamespaces(true);
        if (isset($namespaces['dc'])) {
            $dc = $entry->children($namespaces['dc']);

            $author = new Author();
            $author->name = (string) $dc->creator;
            return $author;
        }
    }

    private function parseCategories(SimpleXMLElement $entry)
    {
        $categories = [];
        foreach($entry->category as $category) {
            $categories[] = (string) $category;
        }
        return $categories;
    }

    private function parseContent(SimpleXMLElement $entry)
    {
        // Try <content>
        if (isset($entry->content)) {
            return (string) $entry->content;
        }

        // Try <content:encoded>
        $namespaces = $entry->getNamespaces(true);
        if (isset($namespaces['content'])) {
            $content = $entry->children($namespaces['content']);
            if (isset($content->encoded)) {
                return (string) $content->encoded;
            }
        }
    }
}
