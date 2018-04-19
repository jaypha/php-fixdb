<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\FixDB;

class IndexDiffTest extends TestCase
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

  function testStringColumn()
  {
    $col1 = FixDB::stringType();
    $col2 = FixDB::stringType(132, "abc");
    $pidx = [ "columns" => [ "id" ] ];
    $idx1 = [];
    $idx2 = [ "unique" => true, "columns" => [ "col1", "col2" ] ];

    $def = [
      "temporary" => true,
      "name" => "indexTest",
      "noid" => true,
      "columns" => [
        "id" => FixDB::idType(),
        "col1" => $col1,
        "col2" => $col2
      ],
      "indicies" => [
        "primary" => $pidx,
        "col1" => $idx1,
        "mix1" => $idx2
      ]
    ];

    self::$fixdb->createTable($def);

    $data = self::$fixdb->extractIndexInfo("indexTest");
    $this->assertTrue(self::$fixdb->areIndexDefsSame($pidx,$data["primary"]));
    $this->assertTrue(self::$fixdb->areIndexDefsSame($idx1,$data["col1"]));
    $this->assertTrue(self::$fixdb->areIndexDefsSame($idx2,$data["mix1"]));

    $pidx["columns"][] = "col1";
    $this->assertFalse(self::$fixdb->areIndexDefsSame($pidx,$data["primary"]));

    $idx1["unique"] = true;
    $this->assertFalse(self::$fixdb->areIndexDefsSame($idx1,$data["col1"]));

    $idx2["unique"] = false;
    $this->assertFalse(self::$fixdb->areIndexDefsSame($idx1,$data["mix1"]));
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
