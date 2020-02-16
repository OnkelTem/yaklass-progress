<?php

namespace Yaklass;

use Exception;
use TaskRunner\Task;
use TaskRunner\TaskRunnerException;

/**
 * @property App $app
 */
class TaskList extends Task {

  protected static $taskId = 'list';

  /**
   * @throws Exception
   */
  public function run() {
    try {
      $storage = new Storage([
        'driver' => 'pdo_sqlite',
        'path' => 'progress.sqlite',
      ], $this->logger);
      $data = $storage->get();
      foreach($data as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
      }
    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }

  }

}
