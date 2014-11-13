<?php
namespace Dartosphere\FeedCrawler;

use Dartosphere\FeedCrawler\Content\Feed;
use Dartosphere\FeedCrawler\Content\Item;

interface PageBuilder
{
    public function build(Feed $feed, Item $item, $targetDir);
}