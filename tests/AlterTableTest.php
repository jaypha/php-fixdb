<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\FixDB;

class AlterTableTest extends TestCase
{
  protected static $fixdb;
  protected static $tableDef;

  public static function setUpBeforeClass()
  {
    $mysqli = new MySQLiExt
    (
      $GLOBALS["DB_HOST"],
      $GLOBALS["DB_USER"],
      $GLOBALS["DB_PASSWD"],
      $GLOBALS["DB_DBNAME"]
    );
    self::$fixdb = new FixDB($mysqli);

    self::$tableDef = [
      "name" => "testTable",
      "noid" => true,
      "columns" => [
        "id" => FixDB::idType(),
        "col1" => FixDB::stringType(),
        "col2" => FixDB::stringType(132, "abc")
      ],
      "indicies" => [
        "primary" => [ "columns" => [ "id" ] ],
        "col1" => [],
        "mix1" => [ "unique" => true, "columns" => [ "col1", "col2" ] ]
      ]
    ];
    $mysqli->query("drop table  if exists `testTable`");
    self::$fixdb->createTable(self::$tableDef);
  }

  function testAlterTable()
  {
    $newDef = self::$tableDef;
    $newDef["name"] = "newTable";
    $newDef["old_name"] = "testTable";
    unset($newDef["columns"]["col2"]);
    unset($newDef["indicies"]["mix1"]);
    $newDef["columns"]["col1"]["size"] = 250;
    $newDef["columns"]["col3"] = FixDB::dateType(null,true);

    $sql = self::$fixdb->fixTable($newDef);
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertNull($sql);
  }

  public static function tearDownAfterClass()
  {
    self::$fixdb->dbConn->query("drop table  if exists `testTable`");
    self::$fixdb->dbConn->query("drop table  if exists `newTable`");
    self::$fixdb->dbConn->close();
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
