<?php

namespace Yaklass\Task;

use Exception;
use TaskRunner\Task;
use Yaklass\App;
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
      echo "Unable to fetch data:\n\t" . $e->getMessage();
      exit(1);
    }
    unset($spider);

    //
    // Parse data with XPath
    //
    $xp = new XPathReader($html);
    try {
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
      echo "Error(s): " . $e->getMessage();
      exit(1);
    }

    //
    // Save data to csv
    //
    try {
      $storage = new StatsSqlStorage([
        'driver' => 'pdo_sqlite',
        'path' => 'stats.sqlite',
      ], new Logger());
      $storage->save($stats);

    } catch (Exception $e) {
      echo "Error(s): " . $e->getMessage();
      exit(1);
    }

  }

}
