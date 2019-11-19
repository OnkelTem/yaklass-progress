<?php

namespace Yaklass\Helpers;

use DOMDocument;
use DOMNodeList;
use DomXPath;
use Exception;

class XpathHelper {

  protected $xpath;

  function __construct($string) {
    libxml_use_internal_errors(true);
    $dom = new DomDocument;
    $dom->loadHTML($string);
    $this->xpath = new DomXPath($dom);
  }

  /**
   * @param $query
   * @param null $context_node
   * @return DOMNodeList|false
   * @throws Exception
   */
  function getList($query, $context_node = NULL) {
    $nodes = $this->xpath->query($query, $context_node);
    return $nodes && $nodes->count() ? $nodes : $this->error();
  }

  /**
   * @param $query
   * @param null $context_node
   * @return bool|string
   * @throws Exception
   */
  function getText($query, $context_node = NULL) {
    $nodes = $this->xpath->query($query, $context_node);
    return $nodes && $nodes->count() ? trim($nodes->item(0)->nodeValue) : $this->error();
  }

  /**
   * @param $query
   * @param null $context_node
   * @return bool|string
   * @throws Exception
   */
  function getAttr($query, $context_node = NULL) {
    $nodes = $this->xpath->query($query, $context_node);
    return $nodes && $nodes->count() ? trim($nodes->item(0)->value) : $this->error();
  }

  /**
   * @throws Exception
   * return FALSE
   */
  function error() {
    $errors = [];
    foreach (libxml_get_errors() as $error) {
      $errors[] = [
        'code' => $error->code,
        'message' => $error->message,
        'position' => '[' . $error->line . ':' . $error->column . ']',
      ];
    }
    libxml_clear_errors();
    throw new Exception("\n" . implode("\n", array_map(function($el) {
        return "\t" . $el['code'] . ' ' . trim($el['message']) . ' ' . $el['position'];
      }, $errors)));
    /** @noinspection PhpUnreachableStatementInspection */
    return FALSE;
  }
}

