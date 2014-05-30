<?php

namespace Dartosphere\FeedCrawler\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;

class Application extends BaseApplication
{
	/** Set project name and version. */
    public function __construct()
    {
        parent::__construct('Dartosphere Feed Crawler');
    }

    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();
        $defaultCommands[] = new GenerateCommand();
        return $defaultCommands;
    }
}
