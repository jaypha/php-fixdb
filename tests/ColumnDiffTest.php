<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\FixDB;

class ColumnDiffTest extends TestCase
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
    $id = FixDB::idType();
    $def = [
      "temporary" => true,
      "name" => "idColumnTest",
      "noid" => true,
      "columns" => [
        "id" => $id,
      ],
      "indicies" => [
        "primary" => [ "columns" => [ "id" ]]
      ]
    ];

    self::$fixdb->createTable($def);

    $data = self::$fixdb->dbConn->queryData("show columns from `idColumnTest`", "Field");
    $this->assertTrue(self::$fixdb->areDefsSame($id,$data["id"]));
  }

  function testStringColumn()
  {
    $col1 = FixDB::stringType();
    $col2 = FixDB::stringType(132, "abc");

    $def = [
      "temporary" => true,
      "name" => "stringColumnTest",
      "columns" => [
        "col1" => $col1,
        "col2" => $col2
      ]
    ];

    self::$fixdb->createTable($def);

    $data = self::$fixdb->dbConn->queryData("show columns from `stringColumnTest`", "Field");
    $this->assertTrue(self::$fixdb->areDefsSame($col1,$data["col1"]));
    $this->assertTrue(self::$fixdb->areDefsSame($col2,$data["col2"]));

    $unCol1 = $col1;
    $unCol1["size"] = 150;
    $this->assertFalse(self::$fixdb->areDefsSame($unCol1,$data["col1"]));

    $unCol1 = $col1;
    $unCol1["nullable"] = true;
    $this->assertFalse(self::$fixdb->areDefsSame($unCol1,$data["col1"]));

    $unCol1 = $col1;
    unset($unCol1["default"]);
    $this->assertFalse(self::$fixdb->areDefsSame($unCol1,$data["col1"]));
  }

  function testIntColumn()
  {
    $col1 = FixDB::intType();
    $col2 = FixDB::intType(23);
    $col3 = FixDB::intType(null,true);

    $def = [
      "temporary" => true,
      "name" => "intColumnTest",
      "columns" => [
        "col1" => $col1,
        "col2" => $col2,
        "col3" => $col3
      ]
    ];

    self::$fixdb->createTable($def);

    $data = self::$fixdb->dbConn->queryData("show columns from `intColumnTest`", "Field");
    $this->assertTrue(self::$fixdb->areDefsSame($col1,$data["col1"]));
    $this->assertTrue(self::$fixdb->areDefsSame($col2,$data["col2"]));
    $this->assertTrue(self::$fixdb->areDefsSame($col3,$data["col3"]));

    $unCol1 = $col1;
    $unCol1["unsigned"] = true;
    $this->assertFalse(self::$fixdb->areDefsSame($unCol1,$data["col1"]));

    $unCol3 = $col3;
    $unCol3["default"] = 0;
    $this->assertFalse(self::$fixdb->areDefsSame($unCol3,$data["col3"]));
  }

  function testEnumColumn()
  {
    $col1 = FixDB::enumType(["a","b","c"]);
    $col2 = FixDB::enumType(["x","y","z"],"y");
    $col3 = FixDB::enumType(["a","b","c"],null,true);

    $def = [
      "temporary" => true,
      "name" => "enumColumnTest",
      "columns" => [
        "col1" => $col1,
        "col2" => $col2,
        "col3" => $col3
      ]
    ];

    self::$fixdb->createTable($def);

    $data = self::$fixdb->dbConn->queryData("show columns from `enumColumnTest`", "Field");
    $this->assertTrue(self::$fixdb->areDefsSame($col1,$data["col1"]));
    $this->assertTrue(self::$fixdb->areDefsSame($col2,$data["col2"]));
    $this->assertTrue(self::$fixdb->areDefsSame($col3,$data["col3"]));

    $unCol1 = $col1;
    $unCol1["default"] = "x";
    $this->assertFalse(self::$fixdb->areDefsSame($unCol1,$data["col1"]));

    $unCol3 = $col3;
    $unCol3["default"] = "";
    $this->assertFalse(self::$fixdb->areDefsSame($unCol3,$data["col3"]));
  }

  function testTimestampColumn()
  {
    $col1 = FixDB::timestampType();
    $col2 = FixDB::timestampType(true);

    $def = [
      "temporary" => true,
      "name" => "ttColumnTest",
      "columns" => [
        "col1" => $col1,
        "col2" => $col2
      ]
    ];

    self::$fixdb->createTable($def);

    $data = self::$fixdb->dbConn->queryData("show columns from `ttColumnTest`", "Field");
    $this->assertTrue(self::$fixdb->areDefsSame($col1,$data["col1"]));
    $this->assertTrue(self::$fixdb->areDefsSame($col2,$data["col2"]));
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
