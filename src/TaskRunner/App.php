<?php

namespace Yaklass\TaskRunner;

class App extends \TaskRunner\App {

  protected static $paramsMap = [
    'sync' => 'sync',
    'show' => 'show',
    'headless' => '--headless',
    'debug' => '--debug',
    'help' => '--help',
  ];

  public function __construct($params = []) {
    parent::__construct($params);
  }

  protected function getUsageDefinition() {
    return <<<TXT
Yaklass TOP sql data fetcher

Usage:
  yaklass_top_sql (sync | show) [--headless] [--debug] [--help] 

Commands:
  sync                   Synchronize data with Yaklass TOP rating page.
  show                   Show stored information

Options:
  --headless             Suppress opening the web browser. Use for invocations from cron. 
  --debug                Show debugging information when running some tasks.
  --help                 Show some help.

TXT;
  }

}
