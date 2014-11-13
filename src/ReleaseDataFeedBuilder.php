<?php
namespace Dartosphere\FeedCrawler;

use Dartosphere\FeedCrawler\Content\Feed;
use Dartosphere\FeedCrawler\Content\Item;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ReleaseDataFeedBuilder implements PageBuilder
{
    use LoggingTrait;

    public function build(Feed $feed, Item $item, $targetDir)
    {
        $exploded = explode(' of ', $item->title);
        $release = [
            'package_name' => $exploded[1],
            'package_version' => $exploded[0],
            'title' => $item->title,
            'published' => $item->time->format('c'),
            'id' => $item->id,
            'link' => $item->link,
            'author' => $item->author->name
        ];

        $target = $this->getTarget($targetDir);

        if (exec('grep '.escapeshellarg($item->id).' '.$target)) {
            return;
        }

        $release = Yaml::dump([$release]);

        $success = file_put_contents($target, $release, FILE_APPEND); //
        if ($success === false) {
            $this->log()->error("Failed saving post.");
        }
    }

    /**
     * Determines the target file for given item.
     * Creates the target folder if it does not exist.
     *
     * @param $targetDir
     * @return string
     */
    private function getTarget($targetDir)
    {
        $filename = "releases.yml";

        $fs = new Filesystem();
        if (!$fs->exists($targetDir)) {
            $fs->mkdir($targetDir);
        }

        return $targetDir . DIRECTORY_SEPARATOR . $filename;
    }

}