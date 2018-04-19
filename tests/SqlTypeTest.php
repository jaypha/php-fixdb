<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\FixDB;

class SqlTypeTest extends TestCase
{
  protected static $fixdb;

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
  }

  function testEmptyType()
  {
    $sql = self::$fixdb->getTypeSql([]);
    $this->assertEquals($sql, "varchar(255)");
  }

  function testStringType()
  {
    $sql = self::$fixdb->getTypeSql(FixDB::stringType());
    $this->assertEquals($sql, "varchar(255)");

    $sql = self::$fixdb->getTypeSql(FixDB::stringType(100));
    $this->assertEquals($sql, "varchar(100)");
  }

  function testEnumType()
  {
    $sql = self::$fixdb->getTypeSql(FixDB::enumType(["a","b","c"]));
    $this->assertEquals($sql, "enum('a','b','c')");
  }

  function testDecimalType()
  {
    $sql = self::$fixdb->getTypeSql(FixDB::decimalType(2,4));
    $this->assertEquals($sql, "decimal(2,4)");
  }

  public static function tearDownAfterClass()
  {
    self::$fixdb->dbConn->close();
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2006-18 Prima Health Solutions Pty Ltd. All rights reserved.
// Author: Jason den Dulk
//
