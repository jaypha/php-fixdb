<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\FixDB;

class AlterTableSqlTest extends TestCase
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

  function testNeedToCreate()
  {
    $newDef = self::$tableDef;
    $this->assertFalse(self::$fixdb->needToCreateTable($newDef));
    $newDef["name"] = "newTable";
    $this->assertTrue(self::$fixdb->needToCreateTable($newDef));
    $newDef["old_name"] = "testTable";
    $this->assertFalse(self::$fixdb->needToCreateTable($newDef));
  }

  function testNoAlterations()
  {
    $newDef = self::$tableDef;
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertNull($sql);
  }

  function testRenameTable()
  {
    $newDef = self::$tableDef;
    $newDef["name"] = "newTable";
    $newDef["old_name"] = "testTable";
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` rename to `newTable`");
  }

  function testDropColumn()
  {
    $newDef = self::$tableDef;
    unset($newDef["columns"]["id"]);
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` drop column `id`");
    unset($newDef["columns"]["col2"]);
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` drop column `id`,drop column `col2`");
  }

  function testAddColumn()
  {
    $newDef = self::$tableDef;
    $newDef["columns"]["col3"] = FixDB::dateType(null,true);
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` add column `col3` date null default null");
  }

  function testRenameColumn()
  {
    $newDef = self::$tableDef;
    $newDef["columns"]["col3"] = $newDef["columns"]["col2"];
    $newDef["columns"]["col3"]["old_name"] = "col2";
    unset($newDef["columns"]["col2"]);
    
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` change `col2` `col3` varchar(132) not null default 'abc'");
  }

  function testAlterColumn()
  {
    $newDef = self::$tableDef;
    $newDef["columns"]["col2"]["size"] = 250;
    
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` modify `col2` varchar(250) not null default 'abc'");
  }

  function testAddIndex()
  {
    $newDef = self::$tableDef;
    $newDef["indicies"]["mix2"] = [ "fulltext" => true, "columns" => ["col2"] ];
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` add fulltext index `mix2` (`col2`)");
  }

  function testDropIndex()
  {
    $newDef = self::$tableDef;
    unset($newDef["indicies"]["primary"]);
    unset($newDef["indicies"]["mix1"]);
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` drop primary key,drop index `mix1`");
  }

  function testAlterIndex()
  {
    $newDef = self::$tableDef;
    $newDef["indicies"]["mix1"]["columns"][] = "id";
    $sql = self::$fixdb->getAlterTableSql($newDef);
    $this->assertEquals($sql, "alter table `testTable` drop index `mix1`,add unique index `mix1` (`col1`,`col2`,`id`)");
  }

  public static function tearDownAfterClass()
  {
    self::$fixdb->dbConn->query("drop table  if exists `testTable`");
    self::$fixdb->dbConn->close();
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
