<?php

namespace Yaklass;

use PDO;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;

class StatsSqlStorage {

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
          'date' => ['type' => 'datetime'],
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
    $current_date = new DateTime();
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
        $this->db->getConnection()->insert(self::TABLE_ACTIVITY, [
          'student_id' => $student_id,
          'points' => $points_diff,
          'date' => $current_date,
        ], [
          PDO::PARAM_INT,
          PDO::PARAM_INT,
          'datetime'
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
   * @throws Exception
   */
  public function show() {
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
    if (!count($students)) {
      throw new Exception("Database is empty. Run `sync` first.");
    }
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
      echo json_encode($student_data, JSON_UNESCAPED_UNICODE);
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
        'points' => $row['points'],
      ];
    }
    return $result;
  }

  protected function group($users) {
    $result = [];
    foreach ($users as $user) {
      $id = array_shift($user);
      $result[$id] = $user;
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
