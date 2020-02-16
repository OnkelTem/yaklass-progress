<?php

namespace Yaklass;

use PDO;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;

class Storage {

  CONST TABLE_STUDENT = 'student';
  CONST TABLE_ACTIVITY = 'activity';

  /** @var Database */
  private $db;
  private $logger;

  /**
   * StatsSqlStorage constructor.
   * @param $options
   * @param LoggerInterface $logger
   * @throws Exception
   */
  function __construct($options, $logger) {
    $this->logger = $logger;
    $this->db = new Database($options);
    $this->db->createDatabaseSchema([
      self::TABLE_STUDENT => [
        'columns' => [
          'student_id' => ['type' => 'integer', 'autoinc' => 1],
          'uuid' => ['type' => 'string', 'autoinc' => 1, 'options' => ['length' => 36]],
          'first_name' => ['type' => 'string'],
          'last_name' => ['type' => 'string'],
        ],
        'primary' => [
          'pk' => ['student_id'],
        ],
      ],
      self::TABLE_ACTIVITY => [
        'columns' => [
          'activity_id' => ['type' => 'integer', 'autoinc' => 1],
          'student_id' => ['type' => 'integer'],
          'points' => ['type' => 'integer'],
          'date' => ['type' => 'string'],
        ],
        'primary' => [
          'pk' => ['activity_id'],
        ],
      ],
    ]);
  }

  /**
   * Saves data into DB
   * @param $data
   * @throws Exception
   */
  function save($data) {
    $students = $this->group($this->prepare($data));
    $counters = [
      'new' => 0,
      'updated' => 0,
    ];
    // Цикл по юзерам
    $studentSelect = $this->db->getConnection()->createQueryBuilder()
      ->select('student_id')
      ->from(self::TABLE_STUDENT)
      ->where('uuid = ?');
    $activityTotalSelect = $this->db->getConnection()->createQueryBuilder()
      ->select('sum(points)')
      ->from(self::TABLE_ACTIVITY)
      ->where('student_id = ?');
    foreach($students as $uuid => $student) {
      $this->logger->debug("Student: $uuid, $student[first_name] $student[last_name]");
      // Find user
      $student_id = $studentSelect->setParameter(0, $uuid)->execute()->fetchColumn(0);
      if ($student_id === FALSE) {
        // Create new student
        $this->db->getConnection()->insert(self::TABLE_STUDENT, [
          'uuid' => $uuid,
          'first_name' => $student['first_name'],
          'last_name' => $student['last_name'],
        ]);
        $student_id = $this->db->getConnection()->lastInsertId();
        $this->logger->debug("\tDB: false, creating");
        $counters['new']++;
      }
      else {
        // TODO: Update user name
        $this->logger->debug("\tDB: true");
      }
      // Find all activities of this student
      $points = intval($activityTotalSelect->setParameter(0, $student_id)->execute()->fetchColumn(0));
      if ($points != $student['points']) {
        $points_diff = $student['points'] - $points;
        /** @var DateTime $datetime */
        $datetime = $student['date'];
        $this->db->getConnection()->insert(self::TABLE_ACTIVITY, [
          'student_id' => $student_id,
          'points' => $points_diff,
          'date' => $datetime->format('Y-m-d H:i:s'),
        ], [
          PDO::PARAM_INT,
          PDO::PARAM_INT,
          PDO::PARAM_STR,
        ]);
        $this->logger->debug("\tUpdated: " . ($points_diff >= 0 ? '+' : '-') . abs($points_diff) . " points");
        $counters['updated']++;
      }
      else {
        $this->logger->debug("\tNot updated: no diff");
      }
    }
    $this->logger->info('New users: {new}, New activities: {updated}', $counters);
  }

  /**
   * Displays data from DB
   * @param bool $ignore_empty
   * @return array
   * @throws Exception
   */
  public function get($ignore_empty = FALSE) {
    $studentSelect = $this->db->getConnection()->createQueryBuilder()
      ->select('student_id', 'uuid', 'first_name', 'last_name')
      ->from(self::TABLE_STUDENT);
    $activitiesSelect = $this->db->getConnection()->createQueryBuilder()
      ->select('activity_id, date, points')
      ->from(self::TABLE_ACTIVITY)
      ->where('student_id = ?');
    $students = $studentSelect->execute()->fetchAll(PDO::FETCH_ASSOC);
    if ($students === FALSE) {
      throw new Exception("Can't select students data.");
    }
    if (!count($students) && !$ignore_empty) {
      throw new Exception("Database is empty. Run `sync` first.");
    }
    $result = [];
    foreach($students as $student) {
      $activitiesSelect->setParameter(0, $student['student_id']);
      $activities = $activitiesSelect->execute()->fetchAll(PDO::FETCH_ASSOC);
      if ($students === FALSE) {
        throw new Exception("Can't select activities, student: $student[student_id] ($student[first_name] $student[first_name])");
      }
      $student_data = $student;
      foreach($activities as $activity) {
        $student_data['activities'][$activity['activity_id']] = [
          'date' => $activity['date'],
          'points' => $activity['points'],
        ];
      }
      $result[] = $student_data;
    }
    return $result;
  }

  protected function prepare($data) {
    $result = [];
    foreach ($data as $row) {
      $full_name = $this->parseName($row['name']);
      unset($row['name']);
      $result[] = [
        'uuid' => $row['uuid'],
        'first_name' => $full_name[0],
        'last_name' => $full_name[1],
        'points' => $row['points'],
        'date' => $row['date']
      ];
    }
    return $result;
  }

  protected function group($users) {
    $result = [];
    foreach ($users as $user) {
      $uuid = array_shift($user);
      $result[$uuid] = $user;
    }
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

//  /**
//   * @param array $activities
//   * @throws Exception
//   */
//  public function addActivities(array $activities) {
//    $student_id = 1;
//    foreach($activities as $activity) {
//      $this->db->getConnection()->insert(self::TABLE_ACTIVITY, [
//        'student_id' => $student_id,
//        'points' => $activity['points'],
//        'date' => $activity['date'],
//      ], [
//        PDO::PARAM_INT,
//        PDO::PARAM_INT,
//        'datetime'
//      ]);
//    }
//  }

  /**
   * @throws Exception
   */
  public function getPeriod() {
    $stmt = $this->db->select('min(date), max(date)', self::TABLE_ACTIVITY, NULL, TRUE);
    $res = $stmt->fetch();
    return array_values($res);
  }

  /**
   * @throws Exception
   */
  public function getStudentsWithTotals() {
    $total_query = $this->db->select('sum(points)', self::TABLE_ACTIVITY, 'student_id = ?');
    $stmt = $this->db->select('student_id, first_name, last_name', self::TABLE_STUDENT)->execute();
    $result = [];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $student_id = array_shift($row);
      $row['total'] = intval($total_query->setParameter(0, $student_id)->execute()->fetchColumn() ?: 0);
      $result[$student_id] = $row;
    }
    return $result;
  }

  /**
   * @throws Exception
   */
  public function getActivitiesTree() {
    $stmt = $this->db->select('date, student_id, points', self::TABLE_ACTIVITY, FALSE)->orderBy('date')->execute();
    $result = [];
    $last_date = NULL;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $date = array_shift($row);
      $d = new DateTime($date);
      $d->setTime(0, 0);
      if (!array_key_exists($d->getTimestamp(), $result)) {
        $result[$d->getTimestamp()] = [];
      }
      if (!array_key_exists($row['student_id'], $result[$d->getTimestamp()])) {
        $result[$d->getTimestamp()][$row['student_id']] = 0;
      }
      $result[$d->getTimestamp()][$row['student_id']] += $row['points'];
    }
    return $result;
  }

}
