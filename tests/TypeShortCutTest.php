<?php
//----------------------------------------------------------------------------
// Unit tests for MySQLiExt class
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\FixDB;

class TypeShortCutTest extends TestCase
{
  function testStringType()
  {
    $def = FixDB::stringType();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "string");
    $this->assertEquals($def["default"], "");

    $def = FixDB::stringType(150, "ten");
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "string");
    $this->assertEquals($def["default"], "ten");
    $this->assertEquals($def["size"], 150);
  }

  function testIntType()
  {
    $def = FixDB::intType();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "int");
    $this->assertEquals($def["default"], 0);
    $this->assertFalse($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("unsigned")));
    
    $def = FixDB::intType(17,true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "int");
    $this->assertEquals($def["default"], 17);
    $this->assertTrue($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("unsigned")));

    $def = FixDB::intType(null,true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "int");
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
    $this->assertTrue($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("unsigned")));
  }

  function testUintType()
  {
    $def = FixDB::uintType();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "int");
    $this->assertEquals($def["default"], 0);
    $this->assertFalse($def["nullable"]);
    $this->assertTrue($def["unsigned"]);
    
  }

  function testBoolType()
  {
    $def = FixDB::boolType();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "bool");
    $this->assertFalse($def["nullable"]);
    $this->assertEquals($def["default"], "0");

    $def = FixDB::boolType(true, true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "bool");
    $this->assertTrue($def["nullable"]);
    $this->assertEquals($def["default"], "1");

    $def = FixDB::boolType(null, true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "bool");
    $this->assertTrue($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
  }    

  function testTextType()
  {
    $def = FixDB::textType();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "text");
    $this->assertFalse($def["nullable"]);

    $def = FixDB::textType(true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "text");
    $this->assertTrue($def["nullable"]);
  }

  function testDecimalType()
  {
    $def = FixDB::decimalType(2,4);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "decimal");
    $this->assertFalse($def["nullable"]);
    $this->assertEquals($def["default"], 0);
    $this->assertEquals($def["size"], [2,4]);

    $def = FixDB::decimalType(3,1,null,true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "decimal");
    $this->assertTrue($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
    $this->assertEquals($def["size"], [3,1]);
  }

  function testEnumType()
  {
    $values = ["a","b","c"];
    $def = FixDB::enumType($values);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "enum");
    $this->assertEquals($def["values"], $values);
    $this->assertEquals($def["default"], "a");
    $this->assertFalse($def["nullable"]);

    $def = FixDB::enumType($values,"b");
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "enum");
    $this->assertEquals($def["values"], $values);
    $this->assertEquals($def["default"], "b");
    $this->assertFalse($def["nullable"]);

    $def = FixDB::enumType($values,null,true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "enum");
    $this->assertEquals($def["values"], $values);
    $this->assertTrue($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
  }

  function testDatetimeType()
  {
    $def = FixDB::datetimeType("1980-02-01 12:05:05");
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "datetime");
    $this->assertFalse($def["nullable"]);
    $this->assertEquals($def["default"], "1980-02-01 12:05:05");

    $def = FixDB::datetimeType(null,true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "datetime");
    $this->assertTrue($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
  }

  function testDateType()
  {
    $def = FixDB::dateType("1980-02-01");
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "date");
    $this->assertFalse($def["nullable"]);
    $this->assertEquals($def["default"], "1980-02-01");

    $def = FixDB::dateType(null,true);
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "date");
    $this->assertTrue($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
  }

  function testTimestampType()
  {
    $def = FixDB::timestampType();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "timestamp");
    $this->assertFalse($def["nullable"]);
    $this->assertEquals($def["default"], "CURRENT_TIMESTAMP");
    $this->assertFalse($def["update"]);
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
