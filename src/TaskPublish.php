<?php

namespace Yaklass;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use IntlCalendar;
use IntlDateFormatter;
use TaskRunner\Task;
use TaskRunner\TaskRunnerException;
use Yaklass\Helpers\GoogleSheetsHelper;

/** @noinspection PhpUnused */

/**
 * @property App $app
 */
class TaskPublish extends Task {

  const MAX_VISIBLE_BODY_COLS_DEFAULT = 50;
  protected static $taskId = 'publish';
  /**
   * @var array
   */
  private $namesSeen = [];

  /**
   * @throws Exception
   */
  public function run() {
    if (is_null($this->options['config']['publish'])) {
      throw new TaskRunnerException("Section is missed in the config: 'publish'");
    }
    try {
      $storage = new Storage([
        'driver' => 'pdo_sqlite',
        'path' => 'stats.sqlite',
      ], $this->logger);
      $period = $storage->getPeriod();
      $students = $storage->getStudentsWithTotals();
      $activities = $storage->getActivitiesTree();
      $view = $this->buildView($period, $activities, $students);
      $gsh = new GoogleSheetsHelper([
        'appName' => 'Yaklass',
        'credentials' => Utils::readJSON($this->options['config']['publish']['credentials']),
        'spreadsheetId' => $this->options['config']['publish']['spreadsheetId'],
      ]);
      $this->publish($gsh, $this->options['config']['publish']['sheetTitle'], $view);
    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }
  }

  /**
   * @param GoogleSheetsHelper $gsh
   * @param $sheet_title
   * @param $view
   * @throws Exception
   */
  protected function publish($gsh, $sheet_title, $view) {
    // Shifting top header right
    $header_top = $this->tableShiftIndexHoriz($view['header_top']['values'], $view['header_left']['cols']);
    // Shifting body right
    $body = $this->tableShiftIndexHoriz($view['body']['values'], $view['header_left']['cols']);
    // Shifting left header down
    $header_left = $this->tableShiftIndexVert($view['header_left']['values'], $view['header_top']['rows']);
    // Shifting body down
    $body = $this->tableShiftIndexVert($body, $view['header_top']['rows']);
    $table = [];
    for ($i = 0; $i < $view['header_top']['rows']; $i++) {
      $cols = [];
      for ($j = 0; $j < $view['header_left']['cols']; $j++) {
        if (array_key_exists($i, $header_left)) {
          $cols[] = $header_left[$i][$j];
        }
        else {
          $cols[] = new Cell();
        }
      }
      for ($k = 0; $k < $view['header_top']['cols']; $k++) {
        if (array_key_exists($i, $header_top) && array_key_exists($k + $j, $header_top[$i])) {
          $cols[] = $header_top[$i][$k + $j];
        }
        else {
          $cols[] = new Cell();
        }
      }
      // Span similar values
      $c = 0;
      foreach ($this->countSpans($cols) as list($value, $count)) {
        if ($count > 1) {
          $gsh->queueUpdate($gsh->requestMerge($sheet_title, $i, $c, $i, $c + $count - 1));
          $c += $count;
        }
      };
      $table[] = $cols;
    }
    for ($n = 0; $n < $view['body']['rows']; $n++) {
      $cols = [];
      for ($j = 0; $j < $view['header_left']['cols']; $j++) {
        if (array_key_exists($n + $i, $header_left)) {
          $cols[] = $header_left[$n + $i][$j];
        }
        else {
          $cols[] = new Cell();
        }
      }
      for ($k = 0; $k < $view['body']['cols']; $k++) {
        if (array_key_exists($n + $i, $body) && array_key_exists($k + $j, $body[$n + $i])) {
          $cols[] = $body[$n + $i][$k + $j];
        }
        else {
          $cols[] = new Cell();
        }
      }
      $table[] = $cols;
    }
    // Clear old data, formatting, merges and etc
    $gsh->resetSheet($sheet_title);
    // Write new data
    $gsh->write($sheet_title, array_map(function ($row) {
      return array_map(function ($cell) {
        /** @var Cell $cell */
        return $cell->data();
      }, $row);
    }, $table));
    // Add formatting
    $gsh->queueUpdate($gsh->requestFormat($sheet_title, [
      'horizontalAlignment' => 'CENTER',
      'verticalAlignment' => 'MIDDLE',
      'textFormat' => [
        'fontSize' => 8,
        'fontFamily' => 'Calibri',
      ],
    ]));

    // Auto resize
    $gsh->queueUpdate($gsh->requestDimensionsAutoResize($sheet_title, 'COLUMNS'));

    // Add frozen row
    $gsh->queueUpdate($gsh->requestFrozenRow($sheet_title, $view['header_top']['rows']));
    // Add frozen col
    $gsh->queueUpdate($gsh->requestFrozenColumn($sheet_title, $view['header_left']['cols']));

    $max_cols = intval($this->options['config']['publish']['maxVisibleBodyCols'] ?: self::MAX_VISIBLE_BODY_COLS_DEFAULT);
    if ($view['body']['cols'] > $max_cols) {
      $gsh->queueUpdate($gsh->requestDimensionsHide($sheet_title, 'COLUMNS', TRUE, $view['header_left']['cols'], $view['body']['cols'] - $max_cols));
    }

    // Apply class-based formatting
    $class_clusters = $this->classClusters($table);
    $this->addFormatByClass(
      $sheet_title,
      'weekend',
      [
        'backgroundColor' => [
          'red' => 1,
          'green' => 0.95,
          'blue' => 0.95
        ],
      ],
      $class_clusters,
      $gsh);
    // Apply updates
    $gsh->applyUpdates();
  }

  /**
   * @param $sheet_title
   * @param $class
   * @param $format
   * @param $clusters
   * @param GoogleSheetsHelper $gsh
   */
  protected function addFormatByClass($sheet_title, $class, $format, $clusters, $gsh) {
    foreach ($clusters[$class] as $cluster) {
      $gsh->queueUpdate($gsh->requestFormat($sheet_title, $format, $cluster));
    }
  }

  protected function classClusters($table) {
    $nodes_by_class = [];
    foreach($table as $row_id => $row) {
      /** @var Cell $cell */
      foreach($row as $col_id => $cell) {
        if ($cell->isEmpty()) {
          continue;
        }
        foreach($cell->classes() as $class) {
          $nodes_by_class[$class][] = [$row_id, $col_id];
        }
      }
    }
    $node_sets_by_class = [];
    foreach ($nodes_by_class as $class => $nodes) {
      foreach ($nodes as $node) {
        $added = FALSE;
        // Add to the nearest set...
        if (array_key_exists($class, $node_sets_by_class)) {
          foreach ($node_sets_by_class[$class] as $index => $cluster) {
            foreach($cluster as $cluster_node) {
              if ($node[0] == $cluster_node[0] && abs($node[1] - $cluster_node[1]) == 1 ||
                $node[1] == $cluster_node[1] && abs($node[0] - $cluster_node[0]) == 1) {
                // Only only coordinate differs by 1
                $node_sets_by_class[$class][$index][] = $node;
                $added = TRUE;
                break;
              }
            }
            if ($added) {
              break;
            }
          }
        }
        // ...or if it's failed - create new set
        if (!$added) {
          $node_sets_by_class[$class][] = [$node];
        }
      }
    }
    // Merge node sets into areas (clusters)
    // Note: clusters can only be rectangular
    $clusters_by_class = [];
    foreach ($node_sets_by_class as $class => $node_sets) {
      foreach ($node_sets as $set_id => $nodes) {
        foreach ($nodes as $node) {
          $added = FALSE;
          // Add to the nearest cluster...
          if (array_key_exists($class, $clusters_by_class)) {
            foreach ($clusters_by_class[$class] as $cluster_id => $cluster) {
              // Optimistic
              $added = TRUE;
              $r_contained = $node[0] >= $cluster['r1'] && $node[0] <= $cluster['r2'];
              $c_contained = $node[1] >= $cluster['c1'] && $node[1] <= $cluster['c2'];
              $top_of_r1 = $cluster['r1'] - $node[0] == 1;
              $bottom_of_r2 = $node[0] - $cluster['r2'] == 1;
              $left_of_c1 = $cluster['c1'] - $node[1]  == 1;
              $right_of_c2 = $node[1] - $cluster['c2'] == 1;
              if ($r_contained && $c_contained) {
                // Doesn't change the cluster shape
                break;
              }
              if ($r_contained && $left_of_c1) {
                $clusters_by_class[$class][$cluster_id]['c1']--;
                break;
              }
              if ($r_contained && $right_of_c2) {
                $clusters_by_class[$class][$cluster_id]['c2']++;
                break;
              }
              if ($c_contained && $top_of_r1) {
                $clusters_by_class[$class][$cluster_id]['r1']--;
                break;
              }
              if ($c_contained && $bottom_of_r2) {
                $clusters_by_class[$class][$cluster_id]['r2']++;
                break;
              }
              $added = FALSE;
            }
          }
          // ...or if it's failed - create new cluster
          if (!$added) {
            $clusters_by_class[$class][$set_id] = [
              'r1' => $node[0],
              'c1' => $node[1],
              'r2' => $node[0],
              'c2' => $node[1],
            ];
          }
        }
      }
    }
    return $clusters_by_class;
  }

  /**
   * @param Cell[] $array
   * @return array
   */
  protected function countSpans($array) {
    $last_value = NULL;
    $first_row = TRUE;
    $result = [];
    $counter = 0;
    foreach ($array as $item) {
      if (!$first_row && $last_value !== $item->data()) {
        $result[] = [$last_value, $counter];
        $counter = 0;
        $last_value = $item->data();
      }
      if ($first_row) {
        $first_row = FALSE;
        $last_value = $item->data();
      }
      $counter++;
    }
    if ($counter) {
      $result[] = [$last_value, $counter];
    }
    return $result;
  }

  protected function tableShiftIndexHoriz($array, $delta) {
    $result = [];
    foreach ($array as $index_row => $row) {
      $new_row = [];
      foreach ($row as $index_col => $col) {
        $new_row[$index_col + $delta] = $col;
      }
      $result[$index_row] = $new_row;
    }
    return $result;
  }

  protected function tableShiftIndexVert($array, $delta) {
    $result = [];
    foreach ($array as $index => $row) {
      $result[$index + $delta] = $row;
    }
    return $result;
  }

  /**
   * @param DateTimeInterface $date
   * @return bool
   */
  protected function isWeekend($date) {
    $cal = IntlCalendar::fromDateTime($date->format('r'));
    return $cal->isWeekend();
  }

  /**
   * @param DateTimeInterface $date
   * @return array
   * @see http://userguide.icu-project.org/formatparse/datetime
   */
  protected function datePartCells($date) {
    $formatter = new IntlDateFormatter(
      $this->options['config']['publish']['locale'],
      IntlDateFormatter::SHORT,
      IntlDateFormatter::SHORT,
      $date->getTimezone()->getName(),
      IntlDateFormatter::GREGORIAN,
      'yyyy|LLL|dd|EEEEEE'
    );
    $parts = explode('|', $formatter->format($date));
    $parts[1] = mb_strtoupper(mb_substr($parts[1], 0, 1)) . mb_substr($parts[1], 1);
    return [
      new Cell($parts[0], ['year']),
      new Cell($parts[1], ['month']),
      new Cell($parts[2], $this->isWeekend($date) ? ['day', 'weekend'] : ['day']),
      new Cell($parts[3], $this->isWeekend($date) ? ['dayOfWeek', 'weekend'] : ['dayOfWeek']),
    ];
  }

  /**
   * @param $name
   * @return string|null
   */
  protected function uniqueName($name) {
    if (!in_array($name, $this->namesSeen)) {
      $this->namesSeen[] = $name;
      return $name;
    }
    else {
      $i = 1;
      $new_name = NULL;
      while (TRUE) {
        $i++;
        $new_name = $name . "($i)";
        if (!in_array($new_name, $this->namesSeen)) {
          $this->namesSeen[] = $new_name;
          return $new_name;
        }
      }
      return NULL;
    }
  }

  /**
   * @param $period
   * @param $activities
   * @param $students
   * @return array
   * @throws Exception
   */
  protected function buildView($period, $activities, $students) {
    $start_date = new DateTimeImmutable($period[0]);
    $end_date = new DateTimeImmutable($period[1]);
    $interval = $start_date->diff($end_date)->days;

    // Get dates array
    /** @var DateTime[] $dates */
    $dates = [];
    for ($offset = 0; $offset <= $interval; $offset++) {
      $dates[] = $start_date->add(new DateInterval('P' . $offset . 'D'))->setTime(0, 0);
    }

    // Build TOP table header
    $header_top_cols = [];
    foreach ($dates as $date) {
      $header_top_cols[] = $this->datePartCells($date);
    }
    $header_top = $this->transpose($header_top_cols);

    // Build LEFT table header
    $header_left_rows = [];
    foreach ($students as $student_id => $student_info) {
      $header_left_rows[] = [
        new Cell($this->uniqueName($this->obfuscateName($student_info['first_name'], $student_info['last_name'])), ['name']),
        new Cell($student_info['total'], ['total']),
      ];
    }
    $header_left_cols = $this->transpose($header_left_rows);
    $header_left = $header_left_rows;

    // Build table body
    $body_cols = [];
    $offset = 0;
    foreach ($dates as $date) {
      $timestamp = $date->getTimestamp();
      $row = [];
      foreach (array_keys($students) as $student_id) {
        $classes = $this->isWeekend($date) ? ['weekend'] : [];
        if (array_key_exists($timestamp, $activities) && array_key_exists($student_id, $activities[$timestamp])) {
          $row[] = new Cell($activities[$timestamp][$student_id], $classes);
        }
        else {
          $row[] = new Cell('', $classes);
        }
        $offset++;
      }
      $body_cols[] = $row;
    }
    $body = $this->transpose($body_cols);

    return [
      'header_top' => [
        'cols' => count($header_top_cols),
        'rows' => count($header_top),
        'values' => $header_top,
      ],
      'header_left' => [
        'cols' => count($header_left_cols),
        'rows' => count($header_left),
        'values' => $header_left,
      ],
      'body' => [
        'cols' => count($body_cols),
        'rows' => count($body),
        'values' => $body,
      ],
    ];
  }

  protected function obfuscateName($first_name, $last_name) {
    $a = mb_substr($last_name, 0, 1);
    $b = mb_substr($first_name, 0, 1);
    return vsprintf('%s%s', [$a, $b]);
//    $a = mb_substr($last_name, 0, 2);
//    $b = mb_substr($last_name, -1, 1);
//    $c = mb_substr($first_name, 0, 1);
//    return vsprintf('%s—%s %s.', [$a, $b, $c]);
  }

  protected function transpose($array) {
    return array_map(null, ...$array);
  }
}

class Cell {

  private $data;
  private $classes;

  public function __construct($data = NULL, $classes = []) {
    $this->data = $data;
    $this->classes = $classes;
  }

  public function data() {
    return !is_null($this->data) ? $this->data : "";
  }

  public function classes() {
    return $this->classes;
  }

  public function isEmpty() {
    return is_null($this->data);
  }
}
