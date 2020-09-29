<?php
//----------------------------------------------------------------------------
// 
//----------------------------------------------------------------------------

namespace Jaypha;

class FixDBTypeDef
{
  static function string() { return (new FixDBTypeDef("string"))->default(""); }
  static function text() { return new FixDBTypeDef("text"); }
  static function bool() { return new FixDBTypeDef("bool"); }
  static function int() { return (new FixDBTypeDef("int")); }
  static function uint() { return (new FixDBTypeDef("int"))->unsigned(true); }
  static function decimal() { return (new FixDBTypeDef("decimal")); }
  static function enum() { return new FixDBTypeDef("enum"); }
  static function date() { return new FixDBTypeDef("date"); }
  static function datetime() { return new FixDBTypeDef("datetime"); }
  static function timestamp() { return new FixDBTypeDef("timestamp"); }

  protected $def = [];
  protected $type;

  function __construct($type) { $this->type = $type; }

  function nullable($isNullable = true)
  {
    $this->def["nullable"] = $isNullable;
    return $this;
  }

  function default($default)
  {
    if ($this->type == "text")
      throw new \LogicException("'text' type cannot have a default value");
    if ($this->type == "bool")
      $default = (int) $default;
    $this->def["default"] = $default;
    return $this;
  }

  function unsigned($isUnsigned = true)
  {
    $this->def["unsigned"] = $isUnsigned;
    return $this;
  }

  function values(array $values)
  {
    $this->def["values"] = $values;
    return $this;
  }

  function size($size, $decimals = 0)
  {
    if ($this->type == "decimal")
      $this->def["size"] = [ $size, $decimals ];
    else
      $this->def["size"] = $size;
    return $this;
  }

  function update($update)
  {
    $this->def["update"] = $update;
    return $this;
  }

  function autoIncrement($autoIncrement = true)
  {
    $this->def["auto_increment"] = $autoIncrement;
    return $this;
  }

  function oldName($oldName) { $this->def["old_name"] = $oldName; return $this; }

  function asArray()
  {
    if ($this->type == "enum")
    {
      if (!isset($this->def["values"]))
        throw new \LogicException("Enum type must have a set of values.");
    }

    if (!($this->def["nullable"] ?? false))
    {
      if (!isset($this->def["default"]))
      {
        if (FixDB::isNumericType($this->type))
          $this->def["default"] = 0;
        else if ($this->type == "enum")
          $this->def["default"] = $this->def["values"][0];
        else if ($this->type == "timestamp")
          $this->def["default"] = "CURRENT_TIMESTAMP";
        else if ($this->type != "text")
          throw new \LogicException("Type must have a default if not nullable.");
      }
    }
    $this->def["type"] = $this->type;
    return $this->def;
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2020 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
