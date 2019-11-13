<?php

namespace Yaklass\TaskRunner;

use TaskRunner\LoggerInterface;

class App extends \TaskRunner\App {

  protected static $paramsMap = [
    'sync' => 'sync',
    'show' => 'show',
    'debug' => '--debug',
    'help' => '--help',
  ];

  /** @var LoggerInterface */
  protected $logger;

  public function __construct($params = []) {
    parent::__construct($params);
  }

  protected function getUsageDefinition() {
    return <<<TXT
Yaklass TOP sql data fetcher

Usage:
  yaklass_top_sql (sync | show) [--debug] [--help] 

Commands:
  sync                   Synchronize data with Yaklass TOP rating page.
  show                   Show stored information

Options:
  --debug                Show debugging information when running some tasks.
  --help                 Show some help.

TXT;
  }

}
