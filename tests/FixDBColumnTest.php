<?php
//----------------------------------------------------------------------------
// Unit tests for MySQLiExt class
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\FixDB;
use Jaypha\FixDBTypeDef;

class FixDBColumnTest extends TestCase
{
  function testStringType()
  {
    $typeDef = FixDBTypeDef::string();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals("string", $def["type"]);
    $this->assertEquals("", $def["default"]);

    $typeDef = FixDBTypeDef::string()->size(150)->default("ten");
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "string");
    $this->assertEquals($def["default"], "ten");
    $this->assertEquals($def["size"], 150);
  }

  function testIntType()
  {
    $typeDef = FixDBTypeDef::int();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "int");
    $this->assertEquals(0, $def["default"]);
    $this->assertFalse($def["nullable"] ?? false);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("unsigned")));
    
    $typeDef = FixDBTypeDef::int()->default(17)->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals("int", $def["type"]);
    $this->assertEquals(17, $def["default"]);
    $this->assertTrue($def["nullable"] ?? false);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("unsigned")));

    $typeDef = FixDBTypeDef::int()->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "int");
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
    $this->assertTrue($def["nullable"] ?? false);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("unsigned")));
  }

  function testUintType()
  {
    $typeDef = FixDBTypeDef::uint();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "int");
    $this->assertEquals(0, $def["default"]);
    $this->assertFalse($def["nullable"] ?? false);
    $this->assertTrue($def["unsigned"]);
    
  }

  function testBoolType()
  {
    $typeDef = FixDBTypeDef::bool();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "bool");
    $this->assertFalse($def["nullable"] ?? false);

    $typeDef = FixDBTypeDef::bool()->default(true)->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "bool");
    $this->assertTrue($def["nullable"]);
    $this->assertEquals(1, $def["default"]);

    $typeDef = FixDBTypeDef::bool()->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "bool");
    $this->assertTrue($def["nullable"]);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
  }    

  function testTextType()
  {
    $typeDef = FixDBTypeDef::text();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "text");
    $this->assertFalse($def["nullable"]??false);

    $typeDef = FixDBTypeDef::text()->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "text");
    $this->assertTrue($def["nullable"]??false);
  }

  function testDecimalType()
  {
    $typeDef = FixDBTypeDef::decimal()->size(2,4);
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "decimal");
    $this->assertFalse($def["nullable"]??false);
    $this->assertEquals($def["default"], 0);
    $this->assertEquals($def["size"], [2,4]);

    $typeDef = FixDBTypeDef::decimal()->size(3,1)->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "decimal");
    $this->assertTrue($def["nullable"]??false);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
    $this->assertEquals($def["size"], [3,1]);
  }

  function testEnumType()
  {
    $values = ["a","b","c"];

    $typeDef = FixDBTypeDef::enum()->values($values);
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "enum");
    $this->assertEquals($def["values"], $values);
    $this->assertEquals($def["default"], "a");
    $this->assertFalse($def["nullable"]??false);

    $typeDef = FixDBTypeDef::enum()->values($values)->default("b");
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "enum");
    $this->assertEquals($def["values"], $values);
    $this->assertEquals($def["default"], "b");
    $this->assertFalse($def["nullable"]??false);

    $typeDef = FixDBTypeDef::enum()->values($values)->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "enum");
    $this->assertEquals($def["values"], $values);
    $this->assertTrue($def["nullable"]??false);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
  }

  function testDatetimeType()
  {
    $typeDef = FixDBTypeDef::datetime();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "datetime");
    $this->assertFalse($def["nullable"]??false);
    $this->assertEquals($def["default"], "1970-01-01 00:00:00");

    $typeDef = FixDBTypeDef::datetime()->default("1980-02-01 12:05:05");
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "datetime");
    $this->assertFalse($def["nullable"]??false);
    $this->assertEquals($def["default"], "1980-02-01 12:05:05");

    $typeDef = FixDBTypeDef::datetime()->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "datetime");
    $this->assertTrue($def["nullable"]??false);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
  }

  function testDateType()
  {
    $typeDef = FixDBTypeDef::date();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "date");
    $this->assertFalse($def["nullable"]??false);
    $this->assertEquals($def["default"], "1970-01-01");

    $typeDef = FixDBTypeDef::date()->default("1980-02-01");
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "date");
    $this->assertFalse($def["nullable"]??false);
    $this->assertEquals($def["default"], "1980-02-01");

    $typeDef = FixDBTypeDef::date()->nullable();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "date");
    $this->assertTrue($def["nullable"]??false);
    $this->assertThat($def, $this->logicalNot($this->arrayHasKey("default")));
  }

  function testTimestampType()
  {
    $typeDef = FixDBTypeDef::timestamp();
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "timestamp");
    $this->assertFalse($def["nullable"]??false);
    $this->assertEquals($def["default"], "CURRENT_TIMESTAMP");
    $this->assertFalse($def["update"]??false);
  }

  function testForeignType()
  {
    $typeDef = FixDBTypeDef::foreign()->table("jill");
    $def = $typeDef->asArray();
    $this->assertTrue(is_array($def));
    $this->assertEquals($def["type"], "foreign");
    $this->assertEquals($def["table"], "jill");
    $this->assertEquals($def["column"], "id");
    $typeDef = $typeDef->column("name");
    $def = $typeDef->asArray();
    $this->assertEquals($def["column"], "name");
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
