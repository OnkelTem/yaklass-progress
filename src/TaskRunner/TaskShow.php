<?php

namespace Yaklass\TaskRunner;

use Exception;
use TaskRunner\Task;
use TaskRunner\TaskRunnerException;
use Yaklass\Logger;
use Yaklass\Spider;
use Yaklass\XpathReader;
use Yaklass\StatsSqlStorage;

/**
 * @property App $app
 */
class TaskShow extends Task {

  protected static $taskId = 'show';

  /**
   * @throws Exception
   */
  public function run() {
    try {
      $storage = new StatsSqlStorage([
        'driver' => 'pdo_sqlite',
        'path' => 'stats.sqlite',
      ], $this->logger);
      $storage->show();

    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }

  }

}
