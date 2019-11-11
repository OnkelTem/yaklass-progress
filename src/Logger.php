<?php

namespace Yaklass;

class Logger {

  private $debug;

  function __construct($debug = FALSE) {
    $this->debug = $debug;
  }

  function err($msg, $leading_newline = FALSE, $trailing_newline = TRUE) {
    echo "Error: " . $this->print($msg);
  }

  function msg($msg, $leading_newline = FALSE, $trailing_newline = TRUE) {
    echo $this->print($msg);
  }

  function debug($msg, $leading_newline = FALSE, $trailing_newline = TRUE) {
    if ($this->debug) {
      echo "Debug: " . $this->print($msg);
    }
  }

  protected function print($msg, $leading_newline = FALSE, $trailing_newline = TRUE) {
    return ($leading_newline ? "\n" : "") . $msg . ($trailing_newline ? "\n" : "");
  }
}
