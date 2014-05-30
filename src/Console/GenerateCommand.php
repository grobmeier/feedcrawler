<?php

namespace Dartosphere\FeedCrawler\Console;

use Dartosphere\FeedCrawler\Builder;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class GenerateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('generate')
            ->setDescription('Generates site contents from RSS feeds.')
            ->addOption(
               'config', 'c', InputOption::VALUE_REQUIRED,
               'Path to the configuration file',
               'config.yaml'
            )
            ->addOption(
               'target', 't', InputOption::VALUE_REQUIRED,
               'Target folder where the files will be generated. ' .
               'If not given, defaults to the current working directory.',
               'target'
            )
            ->addOption(
               'use-git', 'g', InputOption::VALUE_NONE,
               'Tries to perform git pull, git commit, git push in target directory ' .
               'Defaults to false.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->loadConfig($input, $output);
        $target = $this->getTarget($input, $output);
        $useGit = ($input->getOption('use-git') == true);

        $builder = new Builder($config, $target, $output, $useGit);
        $builder->build();
    }

    protected function loadConfig(InputInterface $input, OutputInterface $output)
    {
        $config = $input->getOption('config');
        if (!file_exists($config)) {
            throw new \Exception("Configuration file not found at [$config]");
        }

        $config = realpath($config);
        $output->writeln("<info>Config:</info> $config");

        $yaml = file_get_contents($config);
        if ($yaml === false) {
            throw new \Exception("Failed loading configuration.");
        }

        return Yaml::parse($config);
    }

    protected function getTarget(InputInterface $input, OutputInterface $output)
    {
        $target = $input->getOption('target');

        // Default to current working directory
        if (empty($target)) {
            $target = getcwd();
        }

        $fs = new Filesystem();
        if (!$fs->exists($target)) {
            $fs->mkdir($target);
        }

        $target = realpath($target);
        $output->writeln("<info>Target:</info> $target");
        return $target;
    }
}
