<?php

namespace Yaklass;

use DateTime;
use Exception;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use TaskRunner\Task;
use TaskRunner\TaskRunnerException;
use Yaklass\Helpers\WebDriverHelper;
use Yaklass\Helpers\XpathHelper;

/**
 * @property App $app
 */
class TaskSync extends Task {

  const PAGE_TOP = 'https://www.yaklass.ru';

  protected static $taskId = 'sync';

  /**
   * @throws Exception
   */
  public function run() {

    //
    // Get data from Yaklass
    //
    if (is_null($this->options['config']['sync'])) {
      throw new TaskRunnerException("Section is missed in the config: 'sync'");
    }
    try {
      $wdh = new WebDriverHelper($this->options['headless']);
      $html = $this->getTopPage($wdh->getDriver(), $this->options['config']['sync']);
    }
    catch (Exception $e) {
      throw new TaskRunnerException("Unable to fetch data: " . $e->getMessage());
    }
    unset($wdh);

    //
    // Parse data with XPath
    //
    $current_date = new DateTime();
    $stats = [];
    try {
      $xph = new XpathHelper($html);
      $students = $xph->getList("//div[@class='classmates-top']//div[@class='top-list']/div");
      foreach ($students as $i => $student) {
        $stats[] = [
          'uuid' => preg_replace('~/profile/~', '', $xph->getAttr("div[@class='name']/a/@href", $student)),
          'name' => $xph->getText("div[@class='name']/a", $student),
          'points' => $xph->getText("div[@class='points']", $student),
          'date' => $current_date,
        ];
      }
    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }

    //
    // Save data to the database
    //
    try {
      $storage = new Storage([
        'driver' => 'pdo_sqlite',
        'path' => $this->options['db'],
      ], $this->logger);
      $storage->save($stats);
    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }
  }

  /**
   * @param RemoteWebDriver $driver
   * @param array $credentials
   * @return string
   * @throws NoSuchElementException
   * @throws TimeOutException
   */
  function getTopPage($driver, $credentials) {
    $driver->get(self::PAGE_TOP);
    $driver->wait()->until(
      WebDriverExpectedCondition::titleContains('ЯКласс')
    );
    $base_url = trim($driver->getCurrentURL(), '/');
    $driver->get($base_url . '/Account/Login');

    // wait until the page is loaded
    $driver->wait()->until(
      WebDriverExpectedCondition::titleContains('Вход')
    );

    // Login form
    $driver->findElement(WebDriverBy::id('UserName'))->sendKeys($credentials['username']);
    $driver->findElement(WebDriverBy::id('Password'))->sendKeys($credentials['password']);
    // submit the whole form
    $driver->findElement(WebDriverBy::id('loginform'))->submit();

    $driver->wait()->until(
      WebDriverExpectedCondition::presenceOfElementLocated(
        WebDriverBy::cssSelector('.logged-in-block')
      )
    );
    // Goto Top page
    $driver->get($base_url . '/Top');
    $driver->wait()->until(
      WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
        WebDriverBy::cssSelector('.classmates-top .top-list>div')
      )
    );
    return $driver->getPageSource();
  }

}
