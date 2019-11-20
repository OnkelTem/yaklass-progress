<?php

namespace Yaklass;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use TaskRunner\Task;
use TaskRunner\TaskRunnerException;

/**
 * @property App $app
 */
class TaskTestLoad extends Task {

  protected static $taskId = 'testload';

  const FIRST_NAMES_MALE = [
    'Александр',
    'Максим',
    'Иван',
    'Даниил',
    'Михаил',
    'Егор',
    'Дмитрий',
    'Илья',
    'Кирилл',
    'Матвей',
    'Тимофей',
    'Никита',
    'Роман',
    'Ярослав',
    'Алексей',
    'Артем',
    'Владимир',
    'Марк',
    'Константин',
    'Андрей',
    'Лев',
    'Евгений',
    'Тимур',
    'Николай',
    'Павел',
    'Владислав',
    'Денис',
    'Сергей',
    'Арсений',
    'Степан',
  ];

  const FIRST_NAMES_FEMALE = [
    'Мария',
    'Анна',
    'Софья',
    'Анастасия',
    'Виктория',
    'Алиса',
    'Дарья',
    'Ксения',
    'Екатерина',
    'Полина',
    'Елизавета',
    'Александра',
    'Елена',
    'Варвара',
    'Ульяна',
    'Василиса',
    'Арина',
    'Валерия',
    'Ева',
    'Вероника',
    'Юлия',
    'Милана',
    'Ольга',
    'Маргарита',
    'Таисия',
    'Кира',
    'Диана',
    'Мирослава',
    'Евгения',
    'Ирина',
  ];

  const LAST_NAMES = [
    'Иванов',
    'Смирнов',
    'Кузнецов',
    'Попов',
    'Васильев',
    'Петров',
    'Соколов',
    'Михайлов',
    'Новиков',
    'Федоров',
    'Морозов',
    'Волков',
    'Алексеев',
    'Лебедев',
    'Семенов',
    'Егоров',
    'Павлов',
    'Козлов',
    'Степанов',
    'Николаев',
    'Орлов',
    'Андреев',
    'Макаров',
    'Никитин',
    'Захаров',
    'Зайцев',
    'Соловьев',
    'Борисов',
    'Яковлев',
    'Григорьев'
  ];

  const UUID_SYMBOLS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];

  const STUDENTS_COUNT = 30;
  const MAX_POINTS = 50;

  /**
   * @throws Exception
   */
  public function run() {
    try {
      $storage = new Storage([
        'driver' => 'pdo_sqlite',
        'path' => 'stats.sqlite',
      ], $this->logger);

      // Check for existing data
      $data = $storage->get(TRUE);
      if (count($data)) {
        throw new TaskRunnerException('The database contains data, cannot proceed.');
      }

      // Build group
      $students = [];
      $commitments = [];
      $smartness = [];
      for($i = 0; $i < self::STUDENTS_COUNT; $i++) {
        // random sex
        $sex = ['male', 'female'][rand(0, 1)];
        // select first name
        $first_names = $sex == 'male' ? self::FIRST_NAMES_MALE : self::FIRST_NAMES_FEMALE;
        $first_name = $this->getRandItem($first_names);
        // select last name
        $last_name = $this->getRandItem(self::LAST_NAMES);
        if ($sex == 'female') {
          $last_name .= 'а';
        }
        $students[] = [
          'name' => "$first_name $last_name",
          'uuid' => $this->fakeUuid(),
         ];
        $commitments[] = 10 / rand(15, 35);
        $smartness[] = 10 / rand(15, 25);
      }

      // Activities
      $max_points = self::MAX_POINTS;
      $start_date = isset($this->options['start']) ? new DateTime($this->options['start']) : (new DateTime())->sub(new DateInterval('P50D'));
      $interval = $start_date->diff(new DateTime())->days;
      // We should include now, so add one more day
      $interval++;
      $date = clone $start_date;
      $old_points = array_fill(0, count($students), 0);
      for ($i = 0; $i < $interval; $i++) {
        $stats = [];
        foreach($students as $id => $student) {
          // If this student studied this day?
          if ($this->probSuccess($commitments[$id])) {
            $points = $old_points[$id] + round(rand(1, $max_points) * $smartness[$id]);
            $old_points[$id] = $points;
            $stats[] = $student + [
              'points' => $points,
              'date' => clone $date,
            ];
          }
        }
        $date->add(new DateInterval('P1D'));
        $storage->save($stats);
      }
    } catch (Exception $e) {
      throw new TaskRunnerException("Error(s): " . $e->getMessage());
    }
  }

  protected function probSuccess($probability) {
    $results_volume = round(1 / $probability);
    return rand(1, $results_volume) == 1;
  }

  /**
   * @return string
   * Fakes ID like: 7af3de50-0532-4a7c-8d94-323c878691f7
   */
  protected function fakeUuid() {
    $result = [];
    foreach ([8, 4, 4, 4, 12] as $count) {
      $str = '';
      for ($i = 0; $i < $count; $i++) {
        $str .= $this->getRandItem(self::UUID_SYMBOLS);
      }
      $result[] = $str;
    }
    return implode('-', $result);
  }

  protected function getRandItem($array) {
    return $array[rand(0, count($array) - 1)];
  }

}
