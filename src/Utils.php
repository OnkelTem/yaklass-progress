<?php

namespace Yaklass;

use Exception;

class Utils {

  /**
   * @param $file
   * @return mixed
   * @throws Exception
   */
  public static function readJSON($file) {
    if (!file_exists($file)) {
      throw new Exception('File not found: ' . $file);
    }
    $data = file_get_contents($file);
    $json = json_decode($data, true);
    if (is_null($json)) {
      throw new Exception('Cannot parse JSON: ' . $file);
    }
    return $json;
  }


}
