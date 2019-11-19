<?php

namespace Yaklass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Exception;

class Database {

  /** @var Connection */
  private $connection;

  protected $db_params = [];

  function __construct($params) {
    $this->db_params = $params;
  }

  /**
   * @return Connection
   * @throws Exception
   */
  public function getConnection() {
    if (empty($this->connection)) {
      try {
        $connection_params = [
            'wrapperClass' => 'Yaklass\\Connection',
          ] + $this->db_params;
        $this->connection = DriverManager::getConnection($connection_params);
      }
      catch (DBALException $e) {
        throw new DBALException('Connection failed: ' . $e->getMessage());
      }
    }
    return $this->connection;
  }

  /**
   * Creates database tables using definitions from config
   * @param array $schema
   * @throws Exception
   */
  public function createDatabaseSchema($schema) {
    $sm = $this->getConnection()->getSchemaManager();
    try {
      foreach ($schema ?? [] as $table_name => $table_info) {
        if (!$sm->tablesExist($table_name)) {
          $table = new Table($table_name);
          foreach ($table_info['columns'] as $column_name => $column_info) {
            $column = $table->addColumn($column_name, $column_info['type'], $column_info['options'] ?? []);
            if (!empty($column_info['autoinc']) && $column_info['autoinc']) {
              $column->setAutoincrement(TRUE);
            }
          }
          if (!empty($table_info['primary'])) {
            foreach ($table_info['primary'] as $key_name => $column_list) {
              $table->setPrimaryKey($column_list, $key_name);
            }
          }
          if (!empty($table_info['unique keys'])) {
            foreach ($table_info['unique keys'] as $key_name => $column_list) {
              $table->addUniqueIndex($column_list, $key_name);
            }
          }
          $sm->createTable($table);
        }
      }
    } catch (DBALException $e) {
      throw new Exception("Cannot create database: " . $e->getMessage());
    }
  }

  /**
   * @param $fields
   * @param $from
   * @param null $where
   * @param bool $execute
   * @return Statement|QueryBuilder|int
   * @throws Exception
   */
  public function select($fields, $from, $where = NULL, $execute = FALSE) {
    try {
      $qb = $this->getConnection()->createQueryBuilder();
      $qb->select($fields);
      $qb->from($from);
      if ($where) {
        $qb->where($where);
      }
      if ($execute) {
        return $qb->execute();
      }
      else {
        return $qb;
      }
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

}
