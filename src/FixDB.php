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

  public $dbConn;

  //-------------------------------------------------------------------------

  function __construct(MySQLiExt $c)
  {
    $this->dbConn = $c;
  }

  //-------------------------------------------------------------------------

  function add($def)
  {
    switch ($def["type"])
    {
      case "table":
        $sql = $this->getFixTableSql($def);
        if ($sql) $this->tableQueries[] = $sql;
        $this->tablesDefined[] = $def["name"];
        break;
      case "view":
        $this->viewQueries[] = "drop view if exists `{$def["name"]}`";
        $this->viewQueries[] = $this->getViewSql($def);
        break;
      case "function":
        $this->functionQueries[] = "drop function if exists `{$def["name"]}`";
        $this->functionQueries[] = $this->getFunctionSql($def);
        break;
    }
  }

  //-------------------------------------------------------------------------

  function addClean()
  {
    $tables = $this->dbConn->queryColumn("show tables");
    foreach($tables as $t)
      if (!in_array($t, $this->tablesDefined))
        $this->tableQueries[] = "drop table `$t`";
  }

  //-------------------------------------------------------------------------

  function show()
  {
    echo "---------------------------------------------------------------------\n";
    echo "TABLES\n";
    echo "---------------------------------------------------------------------\n";
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

    echo "---------------------------------------------------------------------\n";
    echo "VIEWS\n";
    echo "---------------------------------------------------------------------\n";
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

    echo "---------------------------------------------------------------------\n";
    echo "FUNCTIONS\n";
    echo "---------------------------------------------------------------------\n";
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
      $this->dbConn->q($q);    
    }
    foreach ($this->viewQueries as $q)
    {
      echo "--executing--------------------------------------------------------------\n";
      print_r($q);
      echo "\n";
      $this->dbConn->q($q);    
    }
    foreach ($this->functionQueries as $q)
    {
      echo "--executing--------------------------------------------------------------\n";
      print_r($q);
      echo "\n";
      $this->dbConn->q($q);    
    }
    echo "--finished---------------------------------------------------------------\n";
  }

  //-------------------------------------------------------------------------

  function needToCreateTable($def)
  {
    if ($this->dbConn->tableExists($def["name"]))
    {
      return false;
    }
    else
    {
      if (isset($def["old_name"]))
      {
        if ($this->dbConn->tableExists($def["old_name"]))
          return false; // Table is set to be renamed.
      }
      return true;
    }
  }

  function fixTable($def)
  {
    $this->dbConn->q($this->getFixTableSql($def));
  }

  function getTableSql($def)
  {
    return getFixTableSql($def);
  }
  
  function getFixTableSql($def)
  {
    $this->tablesDefined[] = $def["name"];
    if ($this->needToCreateTable($def))
      return $this->getCreateTableSql($def);
    else
      return $this->getAlterTableSql($def);
  }

  //-------------------------------------------------------------------------
  // No longer used
  
  function extractTable(string $name)
  {
    $def = [ "name" => $name, "columns" => [], "indicies" => [] ];
    $data = $this->dbConn->queryData("show columns from `$name`");

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
    
    $data = $this->dbConn->queryData("show index from `$name`");

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

  function extractColumnInfo($fieldInfo)
  {
    return $this->dbConn->queryData("show columns from `$tableName`", "Field");
  }

  function extractIndexInfo($tableName)
  {
    $keys = [];
    $data = $this->dbConn->queryData("show index from `$tableName`");
    foreach ($data as $row)
    {
      $iname = $row["Key_name"];
      if ($iname == "PRIMARY") $iname = "primary";
      if (!array_key_exists($iname, $keys))
      {
        $keys[$iname] = $row;
        $keys[$iname]["Column_name"] = [];
      }  
      $keys[$iname]["Column_name"][$row["Seq_in_index"]-1] = $row["Column_name"];
    }
    return $keys;
  }

  //-------------------------------------------------------------------------

  function getAlterTableSql($tableDef)
  {
    $drops = [];
    $names = [];
    $adds = [];
    $tableName = $tableDef["name"];

    if (!($tableDef["noid"] ?? false))
    {
      $tableDef["columns"]["id"] = self::idType();
      $tableDef["indicies"]["primary"] = [ "columns" => ["id"]];
    }

    if (isset($tableDef["old_name"]))
    {
      if ($this->dbConn->tableExists($tableDef["old_name"]))
      {
        if ($this->dbConn->tableExists($tableDef["name"]))
          throw new \Exception("Both tables '{$tableDef["old_name"]}' to '{$tableDef["name"]}' exist.");
        $tableName = $tableDef["old_name"];
        $adds[] = "rename to `".$tableDef["name"]."`";
      }
    }
 
    // Columns
    
    $colData = $this->dbConn->queryData("show columns from `$tableName`", "Field");
    
    foreach ($tableDef["columns"] as $name => $colDef)
    {
      if (isset($colDef["old_name"]) && array_key_exists($colDef["old_name"],$colData))
      {
        if (array_key_exists($name,$colData))
          throw new Exception("Both columns '{$colDef["old_name"]}' and '$name' are defined in table '$tableName'");
        $adds[] = "change `{$colDef["old_name"]}` ".$this->getColumnSql($name, $colDef);
        $names[] = $colDef["old_name"];
      }
      else
      {
        if (array_key_exists($name,$colData))
        {
          if (!$this->areDefsSame($colDef, $colData[$name]))
            $adds[] = "modify ".$this->getColumnSql($name, $colDef);
        }
        else
          $adds[] = "add column ".$this->getColumnSql($name, $colDef);
        $names[] = $name;
      }
    }

    // Go through existing columns and remove columns not in the new definition.
    foreach (array_keys($colData) as $name)
      if (!in_array($name, $names))
        $drops[] = "drop column `$name`";

    // Indicies

    $idxData = $this->extractIndexInfo($tableName);

    foreach (array_keys($idxData) as $name)
    {
      if (!array_key_exists($name, $tableDef["indicies"]))
        $drops[] = "drop ".$this->getIndexName($name);
    }

    foreach ($tableDef["indicies"] as $name => $idxDef)
    {
      if (!array_key_exists("columns", $idxDef))
        $idxDef["columns"] = [ $name ];

      if (!array_key_exists($name, $idxData))
        $adds[]  = "add ".$this->getIndexSql($name, $idxDef);
      else if (!$this->areIndexDefsSame($idxDef, $idxData[$name]))
      {
        $drops[] = "drop ".$this->getIndexName($name);
        $adds[]  = "add ".$this->getIndexSql($name, $idxDef);
      }
    }

    $items = implode(",",array_merge($drops, $adds));

    if (strlen($items))
      return "alter table `$tableName` $items";
    else
    {
      return null;
    }
  }
  
  //-------------------------------------------------------

  function areDefsSame($colDef, $fieldInfo)
  {
    return (
      ($this->getTypeSql($colDef) == $fieldInfo["Type"])
      && (empty($colDef["auto_increment"]) !=
          ($fieldInfo["Extra"] == "auto_increment"))
      && (empty($colDef["nullable"]) !=
          ($fieldInfo["Null"] == "YES"))
      && (isset($colDef["default"]) ?
          ($fieldInfo["Default"] !== null && $fieldInfo["Default"] == $colDef["default"]) :
          ($fieldInfo["Default"] === null))
    );
  }

  //-------------------------------------------------------

  function areIndexDefsSame($idxDef, $indexInfo)
  {
    if (($idxDef["columns"] ?? [$indexInfo["Key_name"]]) != $indexInfo["Column_name"])
      return false;
    if ($indexInfo["Key_name"] != "PRIMARY")
      if ( ($idxDef["unique"] ?? false) != ($indexInfo["Non_unique"] == "0") )
        return false;
    if (($idx["fulltext"] ?? false) != ($indexInfo["Index_type"] == "FULLTEXT"))
      return false;

    return true;
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

  //-------------------------------------------------------

  function createTable($def)
  {
    $this->dbConn->q($this->getCreateTableSql($def));
  }

  //-------------------------------------------------------

  function getCreateTableSql($def)
  {
    if (isset($def["temporary"]))
      $query = "create temporary table {$def['name']} (";
    else
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

  //-------------------------------------------------------

  function getColumnSql($name, $field)
  {
    $sql = "`$name` ";
    $sql .= $this->getTypeSql($field);

    if (!empty($field["nullable"]))
      $sql .= " null";
    else
      $sql .= " not null";

    if ($field["auto_increment"] ?? false) 
      $sql .= " auto_increment";

    else if (array_key_exists("default", $field) && $field['default'] !== NULL)
    {
      if (self::isStringType($field["type"]) && $field['default'] != "CURRENT_TIMESTAMP")
        $sql .= " default '{$field['default']}'";
      else
        $sql .= " default {$field['default']}";
    }
    else if (!empty($field["nullable"])) // Nullable fields always default.
      $sql .= " default null";

    if ($field["type"] == "timestamp" || $field["type"] == "datetime")
    {
      if (!empty($field["update"]))
        $sql .= " on update CURRENT_TIMESTAMP";
    }
        
    return $sql;
  }

  //-------------------------------------------------------

  function getIndexName($name)
  {
    if ($name == "primary")
      return "primary key";
    else
      return "index `$name`";
  }

  function getIndexSql($name, $fields)
  {
    if (!array_key_exists("columns", $fields))
      $fields["columns"] = [ $name ];

    if ($name == "primary")
      $sql = "";
    else if ($fields['fulltext'] ?? false)
      $sql = "fulltext ";
    else if ($fields['unique'] ?? false)
      $sql = "unique ";
    else
      $sql = "";
    $sql .= $this->getIndexName($name)." (`".implode("`,`",$fields["columns"])."`)";
    
    return $sql;
  }

  //-------------------------------------------------------

  function getTypeSql(array $field)
  {
    if (!isset($field["type"]))
      $field["type"] = "string";

    switch ($field['type'])
    {
      case "string":
        if (empty($field['size']))
          $type = "varchar(255)";
        else
          $type = "varchar({$field['size']})";
        break;
      case "text":
        $type = "text";
        break;
      case "int":
        if (!isset($field['size']))
          $type = 'int(11)';
        else if ($field['size']<=2)
          $type = "tinyint({$field['size']})";
        else if ($field['size']<=5)
          $type = "smallint({$field['size']})";
        else
          $type = "int({$field['size']})";
        break;
      case "decimal":
        $type = "decimal({$field['size'][0]},{$field['size'][1]})";
        break;
      case "boolean":
      case "bool":
        $type = "tinyint(1)";
        break;
      case "foreign":
        $type = "int(11) unsigned";
        break;
      case "enum":
        foreach ($field['values'] as $label)
          $values[] = $this->dbConn->real_escape_string($label);
        $type = "enum('".implode("','",$values)."')";
        break;
      case "timestamp":
        $type = "timestamp";
        break;
        
      default:
        $type = $field['type'];
    }

    if (!empty($field['unsigned']))
      $type .= ' unsigned';

    return $type;
  }

  //-------------------------------------------------------

  static function isStringType($type)
  {
    switch($type)
    {
      case "enum":
      case "password":
      case "datetime":
      case "timestamp":
      case "date":
      case "time":
      case "string":
      case "text":
        return true;
      default:
        return false;
    }
  }

  //-------------------------------------------------------
  // Type definition shortcuts
  // Under strict rules, any field that is not null, cannot have an implied
  // default.

  static function idType()
  {
    return [
      "type" => "int",
      "unsigned" => true,
      "auto_increment" => true,
      "nullable" => false,
    ];
  }

  static function stringType($length = 255, $default = "")
  {
    assert($default !== NULL);
    $typeDef = ["type"=>"string"];
    if ($length) $typeDef["size"] = $length;
    $typeDef["default"] = $default;
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

  static function enumType(array $values, $default = -1, $nullable = false)
  {
    assert(count($values) != 0);
    if ($default === -1) $default = $values[0];
    assert($nullable || $default !== NULL);
    assert($default === NULL || in_array($default, $values));
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

  static function dateType($default, $nullable = false)
  {
    assert($nullable || $default !== NULL);
    if ($default == NULL)
      return [ "type" => "date", "nullable" => true ];
    else
      return [ "type" => "date", "nullable" => $nullable, "default" => $default ];
  }

  static function timestampType($update = false, $default = "CURRENT_TIMESTAMP", $nullable = false)
  {
    assert($nullable || $default !== NULL);
    if ($default !== NULL)
      return [ "type"=>"timestamp", "default" => $default, "nullable"=>$nullable, "update" => $update ];
    else
      return [ "type"=>"timestamp", "nullable"=>$nullable, "update" => $update ];
  }
}

//----------------------------------------------------------------------------

