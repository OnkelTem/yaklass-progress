<?php

namespace Yaklass;

use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\CannotInsertRecord;

class StatsStorage {

  CONST FILEPATH = 'stats.csv';
  CONST LOCALE = 'ru_RU';
  /**
   * @var void
   */
  private $users;
  /**
   * @var false|string
   */
  private $newDateKey;
  private $dateKeys;

  function __construct() {
    if (file_exists(self::FILEPATH)) {
      $this->users = $this->group($this->fold($this->read()));
    }
  }

  /**
   * @param $data
   * @throws CannotInsertRecord
   */
  function save($data) {
    $users = $this->group($this->prepare($data));
    if ($this->users) {
      $users = $this->getUpdated($users);
    }
    $users = $this->ungroup($users);
    $coll = collator_create(self::LOCALE);
    usort($users, function ($a, $b) use ($coll) {
      return collator_compare($coll, $a['last_name'] . $a['first_name'], $b['last_name'] . $b['first_name']);
    });
    $this->write($this->unfold($users));
  }

  protected function read() {
    $csv = Reader::createFromPath(self::FILEPATH, 'r');
    $csv->setHeaderOffset(0);
    $result = [];
    foreach ($csv->getRecords() as $record) {
      $result[] = $record;
    };
    return $result;
  }

  /**
   * @param $data
   * @throws CannotInsertRecord
   */
  protected function write($data) {
    $csv = Writer::createFromPath(self::FILEPATH, 'w');
    $csv->insertOne(array_keys(current($data)));
    $csv->insertAll($data);
  }

  protected function getUpdated($data) {
    $result = [];

    $common_users = array_intersect_key($this->users, $data);
    foreach (array_keys($common_users) as $id) {
      $user = $this->users[$id];
      $points = array_reduce($user['stats'], function ($carry, $item) {
        $carry += $item;
        return $carry;
      });
      $user_update = $data[$id];
      $date_update = key($user_update['stats']);
      $points_update = current($user_update['stats']);
      $delta = ($points_update - $points);
      $user['stats'] = $user['stats'] + [$date_update => (string)$delta];
      $result[$id] = $user;
    }

    $missed_users = array_diff_key($this->users, $data);
    foreach (array_keys($missed_users) as $id) {
      // Copy data from existing user and set 0 points
      $user = $this->users[$id];
      $user['stats'] = $user['stats'] + [$this->getNewDateKey() => "0"];
      $result[$id] = $user;
    }

    $new_users = array_diff_key($data, $this->users);
    foreach (array_keys($new_users) as $id) {
      // Copy data from new user
      $user = $data[$id];
      $result[$id] = $user;
    }

    return $result;
  }

  protected function getNewDateKey() {
    if (!$this->newDateKey) {
      $this->newDateKey = date('Y-m-d H:i:s');
    }
    return $this->newDateKey;
  }

  protected function getDateKeys() {
    return $this->dateKeys ?: [];
  }

  protected function addDateKeys($keys) {
    $updated = FALSE;
    foreach ($keys as $key) {
      if (!in_array($key, $this->dateKeys ?: [])) {
        $this->dateKeys[] = $key;
        $updated = TRUE;
      }
    }
    if ($updated && count($this->dateKeys) > 1) {
      sort($this->dateKeys, SORT_STRING);
    }
  }

  protected function prepare($data) {
    $result = [];
    foreach ($data as $row) {
      $full_name = $this->parseName($row['name']);
      unset($row['name']);
      $result[] = [
        'id' => $row['id'],
        'first_name' => $full_name[0],
        'last_name' => $full_name[1],
        'stats' => [
          $this->getNewDateKey() => $row['points'],
        ]
      ];
    }
    return $result;
  }

  protected function fold($users) {
    return array_map(function ($user) {
      $props = array_intersect_key($user, array_flip(['id', 'first_name', 'last_name']));
      $stats = array_diff_key($user, $props);
      return $props + ['stats' => $stats];
    }, $users);
  }

  protected function unfold($users) {
    return array_map(function ($user) {
      $stats = [];
      foreach ($this->getDateKeys() as $key) {
        $stats[$key] = isset($user['stats'][$key]) ? $user['stats'][$key] : "0";
      }
      return array_diff_key($user, array_flip(['stats'])) + $stats;
    }, $users);
  }

  protected function group($users) {
    $result = [];
    foreach ($users as $user) {
      $id = array_shift($user);
      $this->addDateKeys(array_keys($user['stats']));
      $result[$id] = $user;
    };
    return $result;
  }

  protected function ungroup($users) {
    $result = [];
    foreach ($users as $id => $user) {
      $result[] = ['id' => $id] + $user;
    };
    return $result;
  }

  protected function parseName($name) {
    $result = [];
    if (($res = preg_split('~\s+~', $name)) !== FALSE) {
      if (count($res) == 1) {
        $res[1] = "";
      }
      $result[] = $res[0];
      $result[] = count($res) == 2 ? $res[1] : $res[2];
    }
    return $result;
  }

}
