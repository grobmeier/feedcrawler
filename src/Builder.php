<?php

namespace Dartosphere\FeedCrawler;

use Dartosphere\FeedCrawler\Content\Feed;
use Dartosphere\FeedCrawler\Content\Item;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/** Builds a site from RSS data */
class Builder
{
    use LoggingTrait;

    public function __construct(array $feeds, $target)
    {
        $this->feeds = $feeds;
        $this->target = rtrim($target, DIRECTORY_SEPARATOR);
    }

    public function build()
    {
        foreach($this->feeds as $feed) {
            $url = $feed['url'];
            $name = $feed['name'];
            $this->processFeed($name, $url);
        }
    }

    public function processFeed($name, $url)
    {
        try {
            $this->log()->info("Loading feed: $url");
            $data = $this->loadFeed($url);

            $this->log()->info("Parsing feed.");
            $feed = $this->parseFeed($data);

            $count = count($feed->items);
            $this->log()->info("Loaded $count items. Creating posts.");

            foreach($feed->items as $item) {
                $this->buildPage($feed, $item);
            }
        } catch (\Exception $ex) {
            $this->log()->error($ex->getMessage());
        }
    }

    private function buildPage(Feed $feed, Item $item)
    {
        $meta = Yaml::dump([
            'title' => $item->title,
            'layout' => 'post',
            'tags' => $item->categories,
            'category' => $feed->slug,
            'published' => $item->time->format('c'),
        ]);

        if (!empty($item->content)) {
            $content = $item->content;
        } elseif (!empty($item->description)) {
            $content = $item->description;
        } else {
            $this->log()->warn("No content found for item [$item->title] in feed [$feed->title]. Skipping.");
        }

        // Construct the page
        $post  = "---\n";
        $post .= "$meta\n";
        $post .= "---\n\n";
        $post .= "$content\n";

        $target = $this->getTarget($feed, $item);

        $this->log()->info("Rendering: $target");
        $success = file_put_contents($target, $post);
        if ($success === false) {
            $this->log()->error("Failed saving post.");
        }
    }

    /**
     * Determines the target file for given item.
     * Creates the target folder if it doesn't exist.
     */
    private function getTarget(Feed $feed, Item $item)
    {
        $date = $item->time->format('Y-m-d');
        $slug = $item->slug;
        $filename = "$date-$slug.html";

        $dir = $this->target . DIRECTORY_SEPARATOR . "_posts";
        $fs = new Filesystem();
        if (!$fs->exists($dir)) {
            $fs->mkdir($dir);
        }

        return $dir . DIRECTORY_SEPARATOR . $filename;
    }

    private function loadFeed($url)
    {
        $data = @file_get_contents($url);
        if ($data === false) {
            $error = error_get_last();
            $error = trim($error['message']);
            throw new \Exception("Failed loading feed: $error");
        }

        return $data;
    }

    private function parseFeed($data)
    {
        $xml = simplexml_load_string($data);
        if ($xml === false) {
            throw new \Exception("Failed parsing feed.");
        }

        $root = $xml->getName();
        switch($root) {
            case "rss":
                $parser = new Parser\RssParser();
                break;
            case "feed":
                $parser = new Parser\AtomParser();
                break;
            default:
                throw new \Exception("Unknown root node \"$root\"");
        }

        return $parser->parse($xml);
    }
}
