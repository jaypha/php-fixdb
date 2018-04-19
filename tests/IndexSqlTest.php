<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\FixDB;

class IndexSqlTest extends TestCase
{
  protected static $FixDB;

  public static function setUpBeforeClass()
  {
    $mysqli = new MySQLiExt
    (
      $GLOBALS["DB_HOST"],
      $GLOBALS["DB_USER"],
      $GLOBALS["DB_PASSWD"],
      $GLOBALS["DB_DBNAME"]
    );
    self::$FixDB = new FixDB($mysqli);
  }

  function testIndex()
  {
    $def = [];
    $sql = self::$FixDB->getIndexSql("someCol",$def);
    $this->assertEquals($sql,"index `someCol` (`someCol`)");

    $def["columns"] = [ "abc", "def" ];
    $sql = self::$FixDB->getIndexSql("someCol",$def);
    $this->assertEquals($sql,"index `someCol` (`abc`,`def`)");

    $def["unique"] = true;
    $sql = self::$FixDB->getIndexSql("someCol",$def);
    $this->assertEquals($sql,"unique index `someCol` (`abc`,`def`)");

    $def["fulltext"] = true ;
    $sql = self::$FixDB->getIndexSql("someCol",$def);
    $this->assertEquals($sql,"fulltext index `someCol` (`abc`,`def`)");

    $sql = self::$FixDB->getIndexSql("primary",$def);
    $this->assertEquals($sql,"primary key (`abc`,`def`)");
  }

  public static function tearDownAfterClass()
  {
    self::$FixDB->dbConn->close();
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2006-18 Prima Health Solutions Pty Ltd. All rights reserved.
// Author: Jason den Dulk
//
