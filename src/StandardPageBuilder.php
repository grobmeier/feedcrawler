<?php
namespace Dartosphere\FeedCrawler;

use Dartosphere\FeedCrawler\Content\Feed;
use Dartosphere\FeedCrawler\Content\Item;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class StandardPageBuilder implements PageBuilder
{
    use LoggingTrait;

    public function build(Feed $feed, Item $item, $targetDir)
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

        $target = $this->getTarget($feed, $item, $targetDir);

        $success = file_put_contents($target, $post);
        if ($success === false) {
            $this->log()->error("Failed saving post.");
        }
    }

    /**
     * Determines the target file for given item.
     * Creates the target folder if it does not exist.
     *
     * @param Feed $feed
     * @param Item $item
     * @return string
     */
    private function getTarget(Feed $feed, Item $item, $targetDir)
    {
        $date = $item->time->format('Y-m-d');
        $slug = $item->slug;
        $filename = "$date-$slug.html";

        $fs = new Filesystem();
        if (!$fs->exists($targetDir)) {
            $fs->mkdir($targetDir);
        }

        return $targetDir . DIRECTORY_SEPARATOR . $filename;
    }

}