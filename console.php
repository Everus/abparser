<?php

use Cilex\Provider\Console\ConsoleServiceProvider;
use Everus\Silex\GoutteServiceProvider;
use \Dflydev\Provider\DoctrineOrm\DoctrineOrmServiceProvider;
use \Silex\Provider\DoctrineServiceProvider;
use Doctrine\ORM\Tools\Console\ConsoleRunner;

$loader = require __DIR__."/vendor/autoload.php";

$app = new Silex\Application();

$app->register(new GoutteServiceProvider());

$app->register(new ConsoleServiceProvider(), array(
    'console.name'              => 'atyrau-business.com parser',
    'console.version'           => '0.0.1',
));

$db_config = [
    'db.options' => [
      'driver' => 'pdo_sqlite',
      'path' => 'db/sqlite.db'
    ]
  ];
$orm_confgig = [
    'orm.em.options' => [
      'mappings' => [
        [
          'type' => 'simple_yml',
          'namespace' => 'Everus\\Model',
          'path' => __DIR__.'/src/Everus/Resource/Mappings'
        ]
      ]
    ]
  ];
$app->register(new DoctrineServiceProvider(), $db_config);
$app->register(new DoctrineOrmServiceProvider(), $orm_confgig);


$site_service = new Everus\Service\Site();
$site_service
  ->setCrawler($app['browser'])
  ->setEntityManager($app['orm.em']);

$console = $app['console'];

$parseCommand = new Everus\Command\ParseCommand();
$parseCommand->setSite($site_service);

$exportCommand = new Everus\Command\ExportCommand();
$exportCommand->setRepo($app['orm.em']->getRepository('Everus\Model\Contact'));

$console->add($parseCommand);
$console->add($exportCommand);

ConsoleRunner::addCommands($console);
$emHelpers = ConsoleRunner::createHelperSet($app['orm.em']);
$helpers = $console->getHelperSet();
foreach ($emHelpers as $key => $value) {
  $helpers->set($value, $key);
}
$console->setHelperSet($helpers);

$console->run();
