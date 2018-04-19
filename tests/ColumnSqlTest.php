<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\FixDB;

class ColumnSqlTest extends TestCase
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

  function testIdColumn()
  {
    $def = FixDB::idType();
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` int(11) unsigned not null auto_increment");
  }

  function testStringColumn()
  {
    $def = FixDB::stringType();
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` varchar(255) not null default ''");

    $def = FixDB::stringType(120, "two");
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` varchar(120) not null default 'two'");

    $def["nullable"] = true;
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` varchar(120) null default 'two'");

    unset($def["default"]);
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` varchar(120) null default null");
  }

  function testEnumColumn()
  {
    $def = FixDB::enumType(["a","b","c"]);
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` enum('a','b','c') not null default 'a'");
    
    $def = FixDB::enumType(["x","y","z"],"y");
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` enum('x','y','z') not null default 'y'");

    $def = FixDB::enumType(["a","b","c"],null,true);
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` enum('a','b','c') null default null");
  }

  function testDateColumn()
  {
    $def = FixDB::dateType("2018-01-01");
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` date not null default '2018-01-01'");
  }

  function testTimestampColumn()
  {
    $def = FixDB::timestampType();
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` timestamp not null default CURRENT_TIMESTAMP");

    $def["nullable"] = true;
    unset($def["default"]);
    $def["update"] = true;
    $sql = self::$fixdb->getColumnSql("someCol",$def);
    $this->assertEquals($sql,"`someCol` timestamp null default null on update CURRENT_TIMESTAMP");
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
