<?php

namespace Yaklass;

use Exception;

class App extends \TaskRunner\App {

  const CONFIG_FILE = 'config.json';

  protected static $paramsMap = [
    'sync' => 'sync',
    'list' => 'list',
    'publish' => 'publish',
    'test-load' => 'test-load',
    'headless' => '--headless',
    'checkpoint' => '--checkpoint',
    'db' => '--db',
    'sort' => '--sort',
    'start' => '--start',
    'debug' => '--debug',
    'help' => '--help',
  ];

  public function __construct($params = []) {
    parent::__construct($params);
    try {
      $this->options['config'] = Utils::readJSON(self::CONFIG_FILE);
    }
    catch(Exception $e) {
      $this->logger->error($e->getMessage());
      die(1);
    }
  }

  protected function getUsageDefinition() {
    return <<<TXT
Yaklass TOP sql data fetcher

Usage:
  yaklass-progress (sync [--headless] |
                    publish [--checkpoint=WEEKDAY] [--sort=FIELD] |
                    list |
                    test-load [--start=DATE]) [--db=PATH] [--debug] [--help] 

Commands:
  sync                     Synchronize data with Yaklass TOP rating page.
  list                     List stored information in JSON format.
  publish                  Publish statistics in a Google spreadsheet.
  test-load                Generate random test data. 

Options:
  --headless               Suppress opening the web browser. Use for invocations from cron.
  --db=PATH                Path to the database file. [Default: progress.sqlite] 
  --checkpoint=WEEKDAY     The number of weekday used for checkpoints. [Default: 7]
  --sort=FIELD             Defines the sorting strategy. Accepted values are: 
                             'name'       - sorts alphabetically
                             'total'      - sorts by the total result
                             'checkpoint' - sorts by the latest checkpoint's result
                           [Default: total] 
  --start=DATE             Start date and time for test data generation. Uses PHP DateTime format, see: https://www.php.net/manual/en/datetime.formats.php
                           The default value is 50 before now.  
  --debug                  Show debugging information when running some tasks.
  --help                   Show some help.

TXT;
  }

}
