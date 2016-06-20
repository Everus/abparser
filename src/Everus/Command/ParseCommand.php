<?php

namespace Everus\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Everus\Service\Site;

class ParseCommand extends Command
{
  protected $site;

  public function setSite(Site $site)
  {
    $this->site = $site;
  }

  protected function configure()
  {
    $this
      ->setName('ab:parse')
      ->setDescription('Parse atyrau-business.com site');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $site = $this->site;
    foreach ($site->getCategories() as $value) {
      $site->getContacts($value);
    }
  }
}
