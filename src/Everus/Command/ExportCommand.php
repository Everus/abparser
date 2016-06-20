<?php

namespace Everus\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class ExportCommand extends Command
{
  private $repo;

  public function setRepo($repo)
  {
    $this->repo = $repo;
    $this;
  }

  protected function configure()
  {
    $this
      ->setName('ab:export')
      ->setDescription('Exports db date in file')
      ->addArgument('file', InputArgument::REQUIRED, 'Path for file');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $contacts = $this->repo->findAll();
    $csvlines = array_map(function($contact) {
      $category = $contact->getCategory();
      return [
        $category->getName(),
        $category->getUrl(),
        $contact->getName(),
        $contact->getUrl(),
        $contact->getEmail(),
        $contact->getPhone()
      ];
    }, $contacts);

    $file = new \SplFileObject($input->getArgument('file'), 'w');

    $file->fputcsv(['Категория', 'Ссылка на категорию', 'Название', 'Ссылка', 'email', 'Телефон']);

    foreach ($csvlines as $line) {
      $file->fputcsv($line);
    }
  }
}
