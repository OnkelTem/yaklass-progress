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
class TaskSync extends Task {

  protected static $taskId = 'sync';

  /**
   * @throws Exception
   */
  public function run() {

    //
    // Get data from Yaklass
    //
    try {
      $spider = new Spider("credentials.json");
      $html = $spider->getTopPage();
    }
    catch (Exception $e) {
      throw new TaskRunnerException("Unable to fetch data: " . $e->getMessage());
    }
    unset($spider);

    //
    // Parse data with XPath
    //
    try {
      $xp = new XPathReader($html);
      $stats = [];
      $students = $xp->getList("//div[@class='classmates-top']//div[@class='top-list']/div");
      foreach ($students as $i => $student) {
        $stats[] = [
          'id' => preg_replace('~/profile/~', '', $xp->getAttr("div[@class='name']/a/@href", $student)),
          'name' => $xp->getText("div[@class='name']/a", $student),
          'points' => $xp->getText("div[@class='points']", $student),
        ];
      }
    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }

    //
    // Save data to csv
    //
    try {
      $storage = new StatsSqlStorage([
        'driver' => 'pdo_sqlite',
        'path' => 'stats.sqlite',
      ], $this->logger);
      $storage->save($stats);

    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }

  }

}
