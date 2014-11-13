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

    /** Wether or not the feedcrawler should try to perform a git commit */
    private $useGit;

    public function __construct(array $config, $target, $useGit = false)
    {
        $this->config = $config;

        $this->target = rtrim($target, DIRECTORY_SEPARATOR);

        $this->useGit = $useGit;

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
            $builder = isset($config['builder']) ? $config['builder'] : 'Dartosphere\FeedCrawler\StandardPageBuilder';
            $categories = isset($config['categories']) ? $config['categories'] : null;

            $this->processFeed($url, $name, $categories, $builder);
        }

        if ($this->useGit == 1) {
            $this->performGit();
        }
    }

    public function processFeed($url, $name, $categories, $builder)
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

                /** @var PageBuilder $pageBuilder */
                $pageBuilder = new $builder();
                $pageBuilder->build($feed, $item, $this->target);
            }
        } catch (\Exception $ex) {
            $this->log()->error($ex->getMessage());
        }
    }

    private function performGit()
    {
        $shell_output = array();
        $status = null;

        chdir($this->target);
        
        $perform = function($command) {
            $output = exec($command,$shell_output,$status);
            $this->log()->debug("Executing: $command");
            $this->log()->debug($shell_output);
            $this->log()->debug('Exec Status: ' . $status);            
        };

        $perform('pwd');
        $perform('git pull');
        $perform('git add .');
        $perform('git commit -m \'Planet update\'');
        $perform('git push');
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
