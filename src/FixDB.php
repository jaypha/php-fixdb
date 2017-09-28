<?php
/*
 * A database create/update/synchronise tool.
 *
 * Copyright (C) 2017 Jaypha
 *
 * Distributed under the Boost Software License, Version 1.0.
 * (See http://www.boost.org/LICENSE_1_0.txt)
 *
 * Authors: Jason den Dulk
 */

namespace Jaypha;

//------------------------------------------------------------------------------
//
// DBDefinition
//
//------------------------------------------------------------------------------

class FixDB
{
  public $tableQueries = [];
  public $viewQueries = [];
  public $functionQueries = [];

  public $tablesDefined = [];

  public $defaultEngine = "InnoDB";
  public $defaultCharset = "utf8mb4";
  public $defaultCollation = "utf8mb4_general_ci";

  public $verbose = false;

  //-------------------------------------------------------------------------

  private $connection;
  
  function __construct(MySQLiExt $connection)
  {
    $this->connection = $connection;
  }

  //-------------------------------------------------------------------------

  function add($def)
  {
    switch ($def["type"])
    {
      case "table":
        $sql = $this->getTableSql($def);
        if ($sql) $this->tableQueries[] = $sql;
        break;
      case "view":
        $this->viewQueries[] = "drop view if exists `{$def["name"]}`";
        $this->viewQueries[] = $this->getViewSql($def);
        break;
      case "function":
        if ($this->verbose) echo "Function: {$def["name"]}\n";
        $this->functionQueries[] = "drop function if exists `{$def["name"]}`";
        $this->functionQueries[] = $this->getFunctionSql($def);
        break;
    }
  }

  //-------------------------------------------------------------------------

  function addClean()
  {
    $tables = $this->connection->queryColumn("show tables");
    foreach($tables as $t)
      if (!in_array($t, $this->tablesDefined))
        $this->tableQueries[] = "drop table `$t`";
  }

  //-------------------------------------------------------------------------

  function show()
  {
    echo "---------------------------------------------------------------------\n"
    echo "TABLES\n"
    echo "---------------------------------------------------------------------\n"
    if (count($this->tableQueries) != 0)
    {
      foreach ($this->tableQueries as $q)
      {
        print_r($q);
        echo "-------------------------------------------------------------------------\n";
      }
    }
    else
      echo "No alterations\n";

    echo "---------------------------------------------------------------------\n"
    echo "VIEWS\n"
    echo "---------------------------------------------------------------------\n"
    if (count($this->viewQueries) != 0)
    {
      foreach ($this->viewQueries as $q)
      {
        print_r($q);
        echo "-------------------------------------------------------------------------\n";
      }
    }
    else
      echo "No views defined\n";

    echo "---------------------------------------------------------------------\n"
    echo "FUNCTIONS\n"
    echo "---------------------------------------------------------------------\n"
    if (count($this->functionQueries) != 0)
    {
      foreach ($this->functionQueries as $q)
      {
        print_r($q);
        echo "-------------------------------------------------------------------------\n";
      }
    }
    else
      echo "No functions defined\n";

  }

  //-------------------------------------------------------------------------

  function execute()
  {
    foreach ($this->tableQueries as $q)
    {
      echo "--executing--------------------------------------------------------------\n";
      print_r($q);
      echo "\n";
      $this->connection->query($q);    
    }
    foreach ($this->viewQueries as $q)
    {
      echo "--executing--------------------------------------------------------------\n";
      print_r($q);
      echo "\n";
      $this->connection->query($q);    
    }
    foreach ($this->functionQueries as $q)
    {
      echo "--executing--------------------------------------------------------------\n";
      print_r($q);
      echo "\n";
      $this->connection->query($q);    
    }
    echo "--finished---------------------------------------------------------------\n";
  }

  //-------------------------------------------------------------------------

  function getTableSql($def)
  {
    $this->tablesDefined[] = $def["name"];
    if (isset($def["old_name"]) && $this->connection->tableExists($def["old_name"]))
    {
      if ($this->connection->tableExists($def["name"]))
        throw new \Exception("Cannot rename table '{$def["old_name"]}' to '{$def["name"]}', table already exists.");

      if ($this->verbose) echo "Altering '{$def["old_name"]}'\n";
      $oldTable = $this->extractTable($def["old_name"]);
      return $this->getAlterTableSql($def, $oldTable);
    }
    else if (!$this->connection->tableExists($def["name"]))
    {
      if ($this->verbose) echo "Creating table '{$def["name"]}'\n";
        return $this->getCreateTableSql($def);
    }
    else
    {
      if ($this->verbose) echo "Altering '{$def["name"]}'\n";
      $oldTable = $this->extractTable($def["name"]);
      $sql = $this->getAlterTableSql($def, $oldTable);
      if ($sql)
        return $sql;
      else
        return null;
    }
  }

  //-------------------------------------------------------------------------

  function getCreateTableSql($def)
  {
    $query = "create table {$def['name']} (";
    $items = [];

    if (empty($def["noid"]))
      $items[] = "`id` int(11) unsigned not null auto_increment";

    foreach ($def["columns"] as $name => &$item)
      $items[] = $this->getColumnSql($name, $item);

    if (empty($def['noid']))
      $items[] = 'primary key(`id`)';

    if (isset($def["indicies"]))
      foreach ($def["indicies"] as $name => &$item)
        $items[] = $this->getIndexSql($name, $item);

    $query .= implode(", ", $items).") ";

    $engine =  isset($def['engine'])?$def['engine']:$this->defaultEngine;
    $charset = isset($def['charset'])?$def['charset']:$this->defaultCharset;
    $collation = isset($def['collation'])?$def['collation']:$this->defaultCollation;
    $query .= "engine=$engine default charset=$charset collate=$collation";

    return $query;
  }

  //-------------------------------------------------------------------------

  function getTypeSql($field)
  {
    if (!isset($field["type"]))
      $field["type"] = "string";

    switch ($field['type'])
    {
      case "string":
        if (empty($field['size']))
          $def = "varchar(255)";
        else
          $def = "varchar({$field['size']})";
        break;
      case "text":
        $def = "text";
        break;
      case "int":
        if (!isset($field['size']))
          $def = 'int(11)';
        else if ($field['size']<=2)
          $def = "tinyint({$field['size']})";
        else if ($field['size']<=5)
          $def = "smallint({$field['size']})";
        else
          $def = "int({$field['size']})";
        break;
      case "decimal":
        $def = "decimal({$field['size'][0]},{$field['size'][1]})";
        break;
      case "boolean":
      case "bool":
        $def = "tinyint(1)";
        break;
      case "foreign":
        $def = "int(11) unsigned";
        break;
      case "enum":
        foreach ($field['values'] as $label)
          $values[] = $this->connection->real_escape_string($label);
        
        $def = "enum('".implode("','",$values)."')";
        break;
      case "timestamp":
        $def = "timestamp";
        if (isset($field["defaultStamp"]))
          $def .= " default CURRENT_TIMESTAMP";
        if ($field["update"])
          $def .= " ON UPDATE CURRENT_TIMESTAMP";
        break;
        
      default:
        $def = $field['type'];
    }

    if (!empty($field['unsigned']))
      $def .= ' unsigned';

    return $def;
  }
  
  //-------------------------------------------------------------------------

  function getColumnSql($name, &$field)
  {
    $sql = "`$name` ";
    $sql .= $this->getTypeSql($field);

    if (!empty($field["nullable"]))
      $sql .= " null";
    else
      $sql .= " not null";
      
    if (array_key_exists("default", $field) && $field['default'] !== NULL)
    {
      if (self::isStringType($field["type"]))
        $sql .= " default '{$field['default']}'";
      else
        $sql .= " default {$field['default']}";
    }
    else if (!empty($field["nullable"])) // Nullable fields always default.
      $sql .= " default null";
        
    return $sql;
  }

  //-------------------------------------------------------------------------

  function getIndexSql($name, &$fields)
  {
    if (!array_key_exists("columns", $fields))
      $fields["columns"] = [ $name ];

    if (!empty($fields['fulltext']))
      $sql = "fulltext index";
    else if (!empty($fields['unique']))
      $sql = "unique index";
    else
      $sql = "index";
    $sql .= "`$name` (".implode(",",$fields["columns"]).")";
    
    return $sql;
  }
  
  //-------------------------------------------------------------------------

  function extractTable(string $name)
  {
    $def = [ "name" => $name, "columns" => [], "indicies" => [] ];
    $data = $this->connection->queryData("show columns from `$name`");

    foreach ($data as $row)
    {
      $colDef = [
        "default" => $row["Default"],
        "type" => $row["Type"],
        "nullable" => ($row["Null"] == "YES"),
        "auto_increment" => ($row["Extra"] == "auto_increment")
      ];
      $def["columns"][$row["Field"]] = $colDef;
    }
    
    $data = $this->connection->queryData("show index from `$name`");

    foreach ($data as $row)
    {
      $iname = $row["Key_name"];

      if ($iname == "PRIMARY")
        $def["primary"][] = $row["Column_name"];

      else if ($row["Seq_in_index"] == "1")
      {
        $idxDef = [
          "columns" => [ $row["Column_name"] ],
          "unique" => $row["Non_unique"] == "0",
          "fulltext" => $row["Index_type"] == "FULLTEXT"
        ];
        
        $def["indicies"][$iname] = $idxDef;
      }
      else
      {
        $def["indicies"][$iname]["columns"][] = $row["Column_name"];
      }
    }
    
    return $def;
  }

  //-------------------------------------------------------------------------

  function getAlterTableSql($newDef, $oldDef)
  {
    $drops = [];
    $names = [];
    $adds = [];

    $originalName = $oldDef["name"];

    if ($newDef["name"] != $oldDef["name"])
    {
      if ($this->verbose) echo "  Rename {$oldDef["name"]} to {$newDef["name"]}\n";
      $adds[] = "rename to ".$newDef["name"];
    }

    //-----------------------------------
    // Columns

    if (empty($newDef["noid"]))
    {
      $newDef["columns"]["id"] = [
        "type" => "int",
        "unsigned" => true,
        "auto_increment" => true,
        "nullable" => false,
        "default" => NULL,
      ];
      $newDef["primary"][] = "id";
      unset($newDef["noid"]);
    }
    if (!isset($newDef["indicies"])) $newDef["indicies"] = [];

    //-----------------------------------
    // Go through column definitions. Add new columns and alter existing ones if
    // different.

    foreach ($newDef["columns"] as $name => $colDef)
    {
      if (!array_key_exists("default", $colDef))
        $colDef["default"] = NULL;
      if (isset($colDef["old_name"]) && array_key_exists($colDef["old_name"], $oldDef["columns"]))
      {
        // Column is being renamed, must redefine.
        if (array_key_exists($name, $oldDef["columns"]))
          throw new Exception("old name and new name both exist");

        $names[] = $colDef["old_name"];
        if ($this->verbose) echo "  rename column '{$colDef["old_name"]}' to '$name'\n";
        $adds[] = "change `{$colDef["old_name"]}` ".getColumnSql($name, $colDef);
      }
      else if (array_key_exists($name, $oldDef["columns"]))
      {
        // Column of same name exists, so alter if different.

        $names[] = $name;
        $change = "";
        $type = $this->getTypeSql($colDef);
        $oldColDef = $oldDef["columns"][$name];

        if (
          ($type != $oldColDef["type"]) ||
          ($colDef["default"] ?? NULL) != ($oldColDef["default"] ?? NULL) ||
          ($colDef["nullable"] ?? false) != ($oldColDef["nullable"] ?? false) ||
          ($colDef["auto_increment"] ?? false) != ($oldColDef["auto_increment"] ?? false)
        )
        {
          if ($this->verbose) echo "  modify column '$name'\n";
          $adds[] = "modify ".$this->getColumnSql($name, $colDef);
        }
      }
      else
      {
        // New Column

        if ($this->verbose) echo "  adding column '$name'\n";
        $adds[] = "add column ".$this->getColumnSql($name, $colDef);
      }
    }

    // Go through existing columns and remove columns not in the new definition.
    foreach ($oldDef["columns"] as $name =>$colDef)
    {
      if (!in_array($name, $names))
      {
        if ($this->verbose) echo "  removing $name\n";
        $drops[] = "drop column `$name`";
      }
    }

    //-----------------------------------
    // Indicies

    $hasPrimary = false;

    foreach ($oldDef["indicies"] as $name => $index)
    {
      if ($name != "PRIMARY")
      {
        // Drop index if not in new def.

        if (!array_key_exists($name, $newDef["indicies"]))
        {
          if ($this->verbose) echo "  drop index '$name'\n";
          $drops[] = "drop index `$name`";
        }
      }
    }

        
    if (array_diff($oldDef["primary"] ?? [], $newDef["primary"] ?? []) !== [])
    {
      if (isset($oldDdef["primary"]))
      {
        if ($this->verbose) echo "  drop primary key\n";
        $drops[] = "drop primary key";
      }
      if (isset($newDdef["primary"]))
      {
        if ($this->verbose) echo "  add primary key '".implode("','",$newDef["primary"])."'";
        $adds[] = "add primary key (`".implode("`,`",$newDef["primary"])."`)";
      }
    }

    foreach ($newDef["indicies"] as $name => $idx)
    {
      if (!array_key_exists("columns", $idx))
        $idx["columns"] = [ $name ];

      if (!array_key_exists($name, $oldDef["indicies"]))
      {
        if ($this->verbose) echo "  add index '$name'\n";
        $adds[] = "add ".$this->getIndexSql($name, $idx);
      }
      else
      {
        if
        (
          (array_diff($idx["columns"] ?? [$name], $oldDef["indicies"][$name]["columns"]) !== []) ||
          ($idx["unique"] ?? false) != ($oldDef["indicies"][$name]["unique"] ?? false) ||
          ($idx["fulltext"] ?? false) != ($oldDef["indicies"][$name]["fulltext"] ?? false)
        )
        {
          if ($this->verbose) echo "  altering index '$name'\n";
          $drops[] = "drop index `$name`";
          $adds[]  = "add ".$this->getIndexSql($name, $idx);
        }
      }
    }

    $items = implode(",",array_merge($drops, $adds));

    if (strlen($items))
      return "alter table `{$oldDef["name"]}` $items";
    else
    {
      if ($this->verbose) echo "  no alterations\n";
      return null;
    }
  }

  //-------------------------------------------------------------------------

  function getViewSql($def)
  {
    echo "View: {$def["name"]}\n";

    $name = $def["name"];
    
    $query = "create view `$name` as ";
    if (isset($def["sql"]))
      $query .= $def["sql"];
    else
    {
      $query .= "select ";

      $join = NULL;
      $selects = [];
      foreach ($def["tables"] as $tablename => &$columns)
      {
        if ($join == NULL) $join = $tablename;
        foreach ($columns as $viewname => $columnname)
          if (is_int($viewname))
            $selects[] = "`$tablename`.`$columnname`";
          else
            $selects[] = "`$tablename`.`$columnname` as `$viewname`";
      }
      $query .= implode(",", $selects);
      $query .= " from $join";
      foreach ($def["joins"] as $table => $j)
        $query .= " join $table on $j";
      if (isset($def["wheres"]))
        $query .= " where ".implode(",", $def["wheres"]);
    }

    return $query;
  }

  //-------------------------------------------------------------------------

  function getFunctionSql($def)
  {
    $query = "CREATE DEFINER = ";

    if (isset($def["definer"]))
      $query .= $def["definer"];
    else
      $query .= "CURRENT_USER";
    $query .= " FUNCTION `{$def["name"]}`(";

    if (isset($def["parameters"]))
    {
      $args = [];
      foreach ($def["parameters"] as $n => $v)
        $args[] = "`$n` ".$this->getTypeSql($v);
      $query .= implode(",",$args);
    }

    $query .= ") RETURNS ";
    if (isset($def["returns"]))
      $query .= $this->getTypeSql($def["returns"]);
    else
      $query .= $this->getTypeSql(["type"=>"string"]);

    if (isset($def["no_sql"]))
      $query .= " NO SQL";

    if (isset($def["deterministic"]))
      $query .= " DETERMINISTIC";

    $query .= " BEGIN ";
    $query .= $def["body"];
    $query .= " END;";

    return $query;
  }
 
  //-------------------------------------------------------------------------

  static function isStringType($type)
  {
    switch($type)
    {
      case "enum":
      case "password":
      case "datetime":
      case "date":
      case "time":
      case "string":
      case "text":
        return true;
      default:
        return false;
    }
  }

  //----------------------------------------------------------------------------
  // The following functions are shortcut definitions of various database types.
  // Under strict rules, any field that is not null, cannot have an implied
  // default.

  static function stringType($length = 255, $default = "")
  {
    $typeDef = ["type"=>"string"];
    if ($length) $typeDef["size"] = $length;
    if ($default !== NULL) $typeDef["default"] = $default;
    return $typeDef;
  }

  static function foreignType($table, $column = "id", $nullable = false)
  {
    if ($nullable)
      return [ "type"=>"foreign", "table" => $table, "column" => $column, "nullable" => true ];
    else
      return [ "type"=>"foreign", "table" => $table, "column" => $column, "default" => 0 ];
  }

  static function intType($default = 0, $nullable = false)
  {
    assert($nullable || $default !== NULL);
    $typeDef = [ "type"=>"int", "nullable"=>$nullable ];
    if ($default !== NULL)
      $typeDef["default"] = $default;
    return $typeDef;
  }

  static function uintType($default = 0, $nullable = false)
  {
    $typeDef = self::intType($default, $nullable);
    $typeDef["unsigned"] = true;
    return $typeDef;
  }

  static function boolType($default = false, $nullable = false)
  {
    $typeDef = [ "type"=>"bool", "nullable"=>$nullable ];
    if ($default !== NULL)
      $typeDef["default"] = $default?"1":"0";
    return $typeDef;
  }

  static function textType($nullable = false)
  {
    // Text types cannot have default values.
    return [ "type"=>"text", "nullable"=>$nullable ];
  }

  static function decimalType($units, $places, $default = 0, $nullable = false)
  {
    assert($nullable || $default !== NULL);
    $typeDef = [ "type"=>"decimal", "size"=> [ $units, $places ], "nullable"=>$nullable ];
    if ($default !== NULL)
      $typeDef["default"] = $default;
    return $typeDef;
  }

  static function enumType($values, $default = -1, $nullable = false)
  {
    if ($default === -1) $default = $values[0];
    assert($nullable || $default !== NULL);
    $typeDef = [ "type"=>"enum", "values" => $values, "nullable" => $nullable ];
    if ($default !== NULL) $typeDef["default"] = $default;
    return $typeDef;
  }

  static function datetimeType($default = NULL, $nullable = false)
  {
    assert($nullable || $default !== NULL);
    if ($default !== NULL)
      return [ "type"=>"datetime", "default" => $default, "nullable"=>$nullable ];
    else
      return [ "type"=>"datetime", "nullable"=>$nullable ];
  }

  static function dateType($default = NULL, $nullable = false)
  {
    assert($nullable || $default !== NULL);
    if ($default == NULL)
      return [ "type" => "date", "nullable" => true ];
    else
      return [ "type" => "date", "nullable" => $nullable, "default" => $default ];
  }

  static function timestampType($update = false, $default = "CURRENT_TIMESTAMP", $nullable = false)
  {
    if ($default === "CURRENT_TIMESTAMP")
      return [ "type"=>"timestamp", "defaultStamp" => true, "nullable"=>$nullable, "update" => $update ];
    else  if ($default !== NULL)
      return [ "type"=>"timestamp", "default" => $default, "nullable"=>$nullable, "update" => $update ];
    else
      return [ "type"=>"timestamp", "nullable"=>$nullable, "update" => $update ];
  }

}

//----------------------------------------------------------------------------

