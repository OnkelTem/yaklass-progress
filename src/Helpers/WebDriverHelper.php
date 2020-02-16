<?php

namespace Yaklass\Helpers;

use Exception;
use Facebook\WebDriver\Exception\WebDriverCurlException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;

class WebDriverHelper {

  const CONNECT_LINE = 'http://localhost:4444/wd/hub';

  /** @var RemoteWebDriver */
  private $driver;

  /**
   * Yaklass Spider constructor.
   * @param bool $headless
   * @throws Exception
   */
  function __construct($headless = FALSE) {
    $this->runBrowser($headless);
  }

  public function getDriver() {
    return $this->driver;
  }

  /**
   * @param $headless
   * @throws Exception
   */
  function runBrowser($headless) {
    $options = new ChromeOptions();
    if ($headless) {
      $options->addArguments(['headless']);
    }
    $capabilities = DesiredCapabilities::chrome();
    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
    try {
      $this->driver = RemoteWebDriver::create(self::CONNECT_LINE, $capabilities);
    }
    /** @noinspection PhpRedundantCatchClauseInspection */
    catch (WebDriverCurlException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect') !== 0) {
        throw new Exception($msg . "\n\n" . "Make sure Selenium is running.");
      }
    }
    //$this->driver->manage()->window()->maximize();
  }

  function __destruct() {
    if ($this->driver) {
      $this->driver->quit();
    }
  }
}

