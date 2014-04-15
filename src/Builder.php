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

    /** Configuration options. */
    private $config;

    /** Path to the folder where feeds are generated. */
    private $target;

    public function __construct(array $config, $target)
    {
        $this->config = $config;
        $this->target = rtrim($target, DIRECTORY_SEPARATOR);

        if (isset($this->config['timeout'])) {
            $timeout = $this->config['timeout'];
            $this->log()->info("Setting socket timeout to $timeout seconds");
            ini_set('default_socket_timeout', $timeout);
        }
    }

    public function build()
    {
        foreach($this->config['feeds'] as $config) {
            $url = $config['url'];
            $name = isset($config['name']) ? $config['name'] : null;
            $categories = isset($config['categories']) ? $config['categories'] : null;

            $this->processFeed($url, $name, $categories);
        }
    }

    public function processFeed($url, $name, $categories)
    {
        try {
            $this->log()->info("Loading feed $name from: $url");
            $data = $this->loadFeed($url);

            $this->log()->info("Parsing feed.");
            $feed = $this->parseFeed($data);

            // Override name if specified
            if (!empty($name)) {
                $feed->name = $name;
            }

            $count = count($feed->items);
            $this->log()->info("Loaded $count items. Creating posts.");

            foreach($feed->items as $item) {
                if (empty($item->title)) {
                    $this->log()->debug("Skipping item (no title)");
                    continue;
                }

                if (!$this->inCategory($item, $categories)) {
                    $this->log()->debug("Skipping item \"$item->slug\" (not in requested category)");
                    continue;
                }

                $this->log()->debug("Adding item \"$item->slug\"");
                $this->buildPage($feed, $item);
            }
        } catch (\Exception $ex) {
            $this->log()->error($ex->getMessage());
        }
    }

    private function inCategory(Item $item, $categories)
    {
        // If no categories are configured process the item
        if (!isset($categories)) {
            return true;
        }

        $intersect = array_intersect($item->categories, $categories);
        return !empty($intersect);
    }

    private function buildPage(Feed $feed, Item $item)
    {
        $meta = [
            'title' => $item->title,
            'layout' => 'post',
            'published' => $item->time->format('c'),
            'feed' => $feed->title,
            'link' => $item->link,
            'author' => (array) $item->author
        ];

        if (!empty($item->categories)) {
            $meta['tags'] = $item->categories;
        }

        $meta = Yaml::dump($meta);

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
        for($retries = 3; $retries > 0; $retries -= 1) {
            $data = @file_get_contents($url);
            if ($data !== false) {
                return $data;
            }
        }

        $error = error_get_last();
        $error = trim($error['message']);
        throw new \Exception("Failed loading feed: $error");
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
