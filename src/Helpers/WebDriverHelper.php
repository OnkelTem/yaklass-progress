<?php

namespace Yaklass\Helpers;

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
   */
  function __construct($headless = FALSE) {
    $this->runBrowser($headless);
  }

  public function getDriver() {
    return $this->driver;
  }

  /**
   * @param $headless
   */
  function runBrowser($headless) {
    $options = new ChromeOptions();
    if ($headless) {
      $options->addArguments(['headless']);
    }
    $capabilities = DesiredCapabilities::chrome();
    /** @noinspection PhpDeprecationInspection */
    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
    $this->driver = RemoteWebDriver::create(self::CONNECT_LINE, $capabilities);
    //$this->driver->manage()->window()->maximize();
  }

  function __destruct() {
    if ($this->driver) {
      $this->driver->quit();
    }
  }
}

