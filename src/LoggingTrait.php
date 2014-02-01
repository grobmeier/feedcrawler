<?php

namespace Dartosphere\FeedCrawler;

trait LoggingTrait
{
    protected $log;

    protected function log()
    {
        return $this->getLogger();
    }

    protected function getLogger()
    {
        if (!isset($this->log)) {
            $this->log = \Logger::getLogger(get_class($this));
        }
        return $this->log;
    }
}