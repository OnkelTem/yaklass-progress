<?php

namespace Yaklass;

use DateTime;
use Exception;
use TaskRunner\Task;
use TaskRunner\TaskRunnerException;

/**
 * @property App $app
 */
class TaskTestLoad extends Task {

  protected static $taskId = 'testload';

  /**
   * @throws Exception
   */
  public function run() {
    try {
      $storage = new Storage([
        'driver' => 'pdo_sqlite',
        'path' => 'stats.sqlite',
      ], $this->logger);
      $activities = [];
      if (($handle = fopen("testload.csv", "r")) !== FALSE) {
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
          if (!array_filter($row)) {
            continue;
          }
          $activities[] = [
            'date' => (new DateTime())->setTimestamp(mktime($row[3], 0, 0, $row[1], $row[0], $row[2])),
            'points' => $row[4],
          ];
        }
        fclose($handle);
      }
      $storage->addActivities($activities);
    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }

  }

}
