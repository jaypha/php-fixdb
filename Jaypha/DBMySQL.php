<?php
/*
 * Convenience extensions to mysqli.
 *
 * Copyright (C) 2017 Jaypha
 *
 * Distributed under the Boost Software License, Version 1.0.
 * (See http://www.boost.org/LICENSE_1_0.txt)
 *
 * Authors: Jason den Dulk
 */

namespace Jaypha;

class DBMySQL extends \mysqli
{
  function __construct($host, $user, $password = NULL, $database = NULL)
  {
    parent::__construct($host, $user, $password, $database);
    if ($this->connect_error)
      throw new \Exception("DBMySQL failed to connect to $host as $user: ($this->connect_errno) '$this->connect_error'");
  }

  function query($query)
  {
    $r = parent::query($query);

    if (!$r)
      throw new \Exception("Query failed: ($this->errno) '$this->error'");
    return $r;
  }

  //-------------------------------------------------------------------------

  public function quote($value)
  {
    if ($value === NULL)
      return 'null';

    if (is_bool($value))
      return (int) $value;

    // Protect digit sequences beginning with '0' (eg phone numbers)
    if (is_string($value) && substr($value, 0,1) == "0")
     return "'".$this->real_escape_string($value)."'";

    if (is_numeric($value))
      return $value;

    return "'".$this->real_escape_string($value)."'";
  }

  //-------------------------------------------------------------------------
  // Convenience methods.
  //-------------------------------------------------------------------------

  public function queryValue($query)
  {
    $res = $this->query($query);
    if (($row = $res->fetch_row()) != NULL)
      $value = $row[0];
    else
      $value = NULL;
    $res->close();
    return $value;
  }

  //-------------------------------------------------------------------------

  public function queryRow($query, $resultType = MYSQLI_ASSOC)
  {
    $res = $this->query($query);
    $row = $res->fetch_array($resultType);
    $res->close();
    return $row;
  }

  //-------------------------------------------------------------------------

  public function queryData($query, $idColumn=NULL, $resultType = MYSQLI_ASSOC)
  {
    $data = [];
    $res = $this->query($query);
    if ($idColumn == NULL)
      $data = $res->fetch_all($resultType);
    else while ($row = $res->fetch_array($resultType))
    {
      if ($idColumn)
        $data[$row[$idColumn]] = $row;
    }
    $res->close();
    return $data;
  }

  //-------------------------------------------------------------------------
  // If two fields are queried, the first is used as an index.

  function queryColumn($query)
  {
    $data = [];
    $res = $this->query($query);
    while ($row = $res->fetch_row())
    {
      if ($res->field_count == 1)
        $data[] = $row[0];
      else
        $data[$row[0]] = $row[1];
    }
    $res->close();
    return $data;
  }

  //-------------------------------------------------------------------------
  // simple get that assumes an "id" column

  function get($table, int $id)
  {
    return $this->queryRow("select * from $table where id=$id");
  }

  //-------------------------------------------------------------------------

  function insert($table, $columns, $values = NULL)
  {
    // Three possibilities
    // string[string] columns, null
    // string[] columns, string[] values
    // string[] columns, string[][] values

    if ($values === NULL)
    {
      $values = array_values($columns);
      $columns = array_keys($columns);
    }

    $query = "insert into $table (".implode(",",$columns).") values (";
    if (!is_array($values[0]))
    {
      $query .= implode(",",array_map([$this,"quote"],$values));
    }
    else
    {
      $query .= implode("),(",array_map(function($a){ return implode(",",array_map([$this,"quote"],$a));},$values));
    }
    $query .= ")";

    $this->query($query);
    return $this->insert_id;
  }

  //-------------------------------------------------------------------------

  function update($table, $values, $wheres)
  {
    foreach ($values as $key => &$value)
      $v[] = "`$key`=".$this->quote($value);
    if (is_array($wheres))
      foreach ($wheres as $key => &$value)
        $w[] = "`$key`=".$this->quote($value);
    else
      $w = [ "id=".((int)$wheres) ];

    $this->query
    (
      "update $table set ".
      implode(",",$v).
      " where ".
      implode(" and ",$w)
    );
  }

  function replace($table, $values)
  {
    foreach ($values as $key => &$value)
      $v[] = "`$key`=".$this->quote($value);
    $this->Query
    (
      "replace `$table` set ".
      implode(",",$v)
    );
    return $this->insert_id;
  }

  //-------------------------------------------------------------------------

  function set($table, $values, $wheres)
  {
    $w = [];

    foreach ($wheres as $key => &$value)
      $w[] = "`$key`=".$this->quote($value);
    $wClause = implode(" and ", $w);

    if ($this->queryValue("select count(*) from $table where $wClause") != 0)
    {
      foreach ($values as $key => &$value)
        $v[] = "`$key`=".$this->quote($value);

      $this->query
      (
        "update $table set ".
        implode(",",$v).
        " where $wClause"
      );
      return 0;
    }
    else
    {
      return $this->insert($table, array_merge($values, $wheres));
    }
  }

  //-------------------------------------------------------------------------

  public function delete($table, $wheres)
  {
    if (is_array($wheres))
    {
      $w = [];
      foreach ($wheres as $key => &$value)
        $w[] = "`$key`=".$this->quote($value);
    }
    else
      $w = [ "id=".((int)$wheres) ];

    $this->Query("delete from $table where ".implode(" and ", $w));
    return $this->affected_rows;
  }

  //-------------------------------------------------------------------------

  /*
  * For numeric columns, this function obtains a value that is guaranteed
  * unique. Used when a unique id number is needed *before* inserting a new
  * entry into the database.
  */

  function uniqueNum($table, $column)
  {
    return $this->queryValue("select max($column) from $table") + 1;
  }

  //-------------------------------------------------------------------------

  function tableExists($tablename)
  {
    $fromdb = '';

    if ($pos = strpos($tablename, '.'))
    {
      $fromdb = "from ".substr($tablename, 0, $pos);
      $tablename = substr($tablename, $pos+1);
    }

    $query = "show tables $fromdb like ".$this->quote($tablename);
    $result = $this->query($query);

    $exists = ($result->num_rows != 0);
    $result->close();
    return $exists;
  }

}

