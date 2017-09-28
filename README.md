# FixDB

Written by Jason den Dulk

A database create/update/synchronise tool for use with MySQL.

Many tools exist these days for database maintenace, called "migration". They
usually work by specifying the changes needed to the database, and then running
the program to update the database.

FixDB works a little different. You sepcify the database definition you want.
The tool compares this definition to what exists in the database, and then
generates the SQL to alter the database to match. The changes are non-destructive
as much a spossible.

This allows you to specify your database as the way it is, rather than an
initial definition plus a whole lot of changes.

## Requirements

PHP with php-mysqli extension.  
Composer  
jaypha\mysqli-ext

## Installation

```
composer require jaypha/mysqli-fixdb
```

## Versions for other databases.

There are no plans to create versions for other databases. It shouldn't be too
difficult to do so if you are so inclined. I am also open to commissions.

## License

Copyright (C) 2017 Jaypha.  
Distributed under the Boost Software License, Version 1.0.  
See http://www.boost.org/LICENSE_1_0.txt

