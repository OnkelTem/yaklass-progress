<?php

namespace Yaklass;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Exception;

class Spider {

  const CONNECT_LINE = 'http://localhost:4444/wd/hub';
  const PAGE_TOP = 'https://www.yaklass.ru';

  /** @var RemoteWebDriver */
  private $driver;
  private $credentials;

  /**
   * YaklassSpider constructor.
   * @param $credentials_file
   * @throws Exception
   */
  function __construct($credentials_file) {
    if (!file_exists($credentials_file)) {
      throw new Exception('File not found: ' . $credentials_file);
    }
    $data = file_get_contents($credentials_file);
    $credentials = json_decode($data, true);
    if (is_null($credentials)) {
      throw new Exception('Cannot parse credentials file: ' . $credentials_file);
    }
    $this->credentials = $credentials;
    $this->openWebsite();
  }

  function openWebsite() {
    $options = new ChromeOptions();
    //$options->addArguments(['headless']);
    $capabilities = DesiredCapabilities::chrome();
    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
    $this->driver = RemoteWebDriver::create(self::CONNECT_LINE, $capabilities);
    $this->driver->manage()->window()->maximize();
  }

  /**
   * @return string
   * @throws NoSuchElementException
   * @throws TimeOutException
   */
  function getTopPage() {
    $d = $this->driver;
    $d->get(self::PAGE_TOP);
    $d->wait()->until(
      WebDriverExpectedCondition::titleContains('ЯКласс')
    );
    $base_url = trim($d->getCurrentURL(), '/');
    $d->get($base_url . '/Account/Login');

    // wait until the page is loaded
    $d->wait()->until(
      WebDriverExpectedCondition::titleContains('Вход')
    );

    // Login form
    $d->findElement(WebDriverBy::id('UserName'))->sendKeys($this->credentials['username']);
    $d->findElement(WebDriverBy::id('Password'))->sendKeys($this->credentials['password']);
    // submit the whole form
    $d->findElement(WebDriverBy::id('loginform'))->submit();

    $d->wait()->until(
      WebDriverExpectedCondition::presenceOfElementLocated(
        WebDriverBy::cssSelector('.logged-in-block')
      )
    );
    // Goto Top page
    $d->get($base_url . '/Top');
    $d->wait()->until(
      WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
        WebDriverBy::cssSelector('.classmates-top .top-list>div')
      )
    );
    return $d->getPageSource();
  }

  function __destruct() {
    if ($this->driver) {
      $this->driver->quit();
    }
  }
}

