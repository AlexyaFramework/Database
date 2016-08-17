Database
========
Alexya's database components

Contents
--------

 - [Connection](#connection)
    - [Connecting to a database](#connecting_to_a_database)
    - [Executing queries](#executing_queries)
    - [Advanced Database Functions](#advanced_database_functions)
 - [Query builder](#query_builder)
    - [Select queries](#select_queries)
    - [Insert queries](#insert_queries)
    - [Update queries](#update_queries)
    - [Delete queries](#delete_queries)
    - [`WHERE` Syntax](#where_syntax)
    - [Other SQL Functions](#other_sql_functions)
 - [ORM](#orm)
    - [CRUD](#crud)
       - [Creating records](#creating_recors)
       - [Reading records](#reading_records)
       - [Updating records](#updating_records)
       - [Deleting records](#deleting_records)

<a name="connection"></a>
Connection
----------

The class `\Alexya\Database\Connection` provides an easy layer for connecting to a database and execute queries.

<a name="connecting_to_a_database"></a>
### Connecting to a database

To connect to a database you'll need to instance a `\Alexya\Database\Connection` object, the constructor accepts
the following parameters:

 * A string being server's host/ip
 * An integer being server's port
 * A string being database username
 * A string being database password
 * A string being database password

<a name="executing_queries"></a>
### Executing queries

The method `\Alexya\Database\Connection::execute` accepts as parameter a string that is the SQL query to execute:

```php
<?php

$Database = new \Alexya\Database\Connection("localhost", 3306, "root", "", "alexya");

$users = $Database->execute("SELECT * FROM `users`");

print_r($users);
```

You can also send a boolean indicating if the connection should fetch all results or just one, you can also
specify how the results will be fetched with a third parameter, by default it's `PDO::FETCH_ASSOC`.

<a name="advanced_database_functions"></a>
### Advanced Database Functions

If you want to take a total control over `\Alexya\Database\Connection` class you can use the method
`\Alexya\Database\Connection::getConnection` that retruns the current PDO object with the database connection.

When an error happens the method `\Alexya\Database\Connection::getError` returns the latest error:

```php
<?php

$Database = new \Alexya\Database\Connection("localhost", 3306, "root", "", "alexya");

$users = $Database->query("SELECT FROM `users`");
if(empty($users)) {
    echo "An error happened!\n". $Database->getError();
}
```

You can see the last executed query with the property `\Alexya\Database\Connection::lastQuery`:

```php
<?php

$Database = new \Alexya\Database\Connection("localhost", 3306, "root", "", "alexya");

$users = $Database->query("SELECT FROM `users`");
echo "Last query: ". $Database->lastQuery; // Last query: SELECT FROM `users`
```

<a name="query_builder"></a>
Query builder
-------------
The class `\Alexya\Database\QueryBuilder` provides a fluent way for generating queries.

The constructor accepts as parameter the `\Alexya\Database\Connection` object with the connection to the database.
Once you've generated the query you can execute it directly with the method `execute` or retrieve the SQL with the
method `getQuery`.

If you want to build more than one query with the same `\Alexya\Database\QueryBuilder` use the method `clear` each time
you finish a query.

<a name="select_queries"></a>
### Select queries

The method `\Alexya\Database\QueryBuilder::select` begins a `SELECT` query and accepts 3 types of parameters:
 * Nothing (the same as passing "*" as parameter)
 * A string that contains the name of the colum to select
 * An array containing the columns to select

```php
<?php

$query->select(); // SELECT *
$query->select("name"); // SELECT `name`
$query->select(["name", "password", "email"]); // SELECT `name`, `password`, `email`
```

Next we must indicate the table that we will use for getting the columns, we do that with the method `\Alexya\Database\QueryBuilder::from`
that accepts as parameter a string containing the name of the table:

```php
<?php

$query->select()
      ->from("users"); // SELECT * FROM `users`
```

<a name="update_queries"></a>
### Insert queries

The method `\Alexya\Database\QueryBuilder::insert` begins an `INSERT` query and accepts as parameter a string
that is the name of the table to insert the new record:

```php
<?php

$query->insert("users"); // INSERT INTO `users`
```

The next thing is to add the values to insert to the table, for that we use the method `\Alexya\Database\QueryBuilder::values`
that accepts an array as parameter, it contains the values to insert into table:

```php
<?php

$query->insert("users")
      ->values([
            "id"       => 1,
            "name"     => "test",
            "password" => "test",
            "email"    => "test@test.test"
        ]); // INSERT INTO `users` (`id`, `name`, `password`, `email`) VALUES (1, 'test', 'test', 'test@test.test')
```

If an index of the array is an object or an array it will serialize it to convert it to a string.

```php
<?php

$query->insert("users")
      ->values([
            "login_log" => ["date1", "date2", "date3"]
        ]); // INSERT INTO `users` (`login_log`) VALUES ('a:3:{i:0;s:5:"date1";i:1;s:5:"date2";i:2;s:5:"date3";}')

$log = new LoginLog(); //Let's assume it exists and is the same as the array but in an object shape
$query->insert("users")
      ->values([
            "login_log" => $log
        ]); // INSERT INTO `users` (`login_log`) VALUES ('O:3:"Obj":3:{s:5:"date1";s:5:"date1";s:5:"date2";s:5:"date2";s:5:"date3";s:5:"date3";}')
```

Alternatively you can encode the values with JSON:
```php
<?php

$query->insert("users")
      ->values([
            "(JSON)login_log" => ["date1", "date2", "date3"]
        ]); // INSERT INTO `users` (`login_log`) VALUES ('["date1","date2","date3"]')

$log = new LoginLog(); //Let's assume it exists and is the same as the array but in an object shape
$query->insert("users")
      ->values([
            "(JSON)login_log" => $log
        ]); // INSERT INTO `users` (`login_log`) VALUES ('{"date1":"date1","date2":"date2","date3":"date3"}')
```

<a name="update_queries"></a>
### Update queries

The method `\Alexya\Database\QueryBuilder::update` begins an `UPDATE` query and accepts as parameter a stringt
that is the name of the table to alter.
```php
<?php
/**
 * Load Alexya's core
 */
require_once("bootstrap.php");

$query = new \Alexya\Database\QueryBuilder();

$query->update("users"); // UPDATE `users`
```

Now we have to set the values to alter, we do that with the method `\Alexya\Database\QueryBuilder::set` which accepts as parameter
an array with the values to alter. You can append to the end of the key the following tags:
 * [+]
 * [-]
 * [*]
 * [/]

You can also serialize values like in `INSERT` queries.

```php
<?php

$query->update("users")
      ->set([
            "name"     => "test",
            "money[+]" => 2
        ]); // UPDATE `users` SET `name`='test', `money`=(`money`+2)
```

<a name="delete_queries"></a>
### Delete queries

Delete queries begins with the method `\Alexya\Database\QueryBuilder::delete` that accepts as parameter the table name as string:
```php
<?php

$query->delete("users"); // DELETE FROM `users`
```

<a name="where_syntax"></a>
### `WHERE` Syntax

The method `\Alexya\Database\QueryBuilder::where` starts the `WHERE` clause and accepts as parameter containing an array:

```php
<?php

$query->select()
      ->from("users")
      ->where([
            "name" => "test"
        ]); // SELECT * FROM `users` WHERE `name`='test'
```

For more advanced conditions you can use the followin tags:
 * [>]
 * [>=]
 * [!]
 * [<>]
 * [><]

```php
<?php

$query->where([
    "id[>]" => 100
]); // WHERE `id`>100

$query->where([
    "id[>=]" => 100
]); // WHERE `id`>=100

$query->where([
    "id[!]" => 100
]); // WHERE `id`!=100

$query->where([
    "id[<>]" => [0, 1000]
]); // WHERE `id` BETWEEN 0 AND 1000

$query->where([
    "id[><]" => [0, 1000]
]); // WHERE `id` NOT BETWEEN 0 AND 1000

$query->where([
    "id" => [0, 1, 2, 3, 4, 5, 6]
]); // WHERE `id` IN(0,1,2,3,4,5,6)

$query->where([
    "id[!]" => [0, 1, 2, 3, 4, 5, 6]
]); // WHERE `id` NOT IN(0,1,2,3,4,5,6)

$query->where([
    "name" => NULL
]); // WHERE `name` IS NULL

$query->where([
    "name[!]" => NULL
]); // WHERE `name` IS NOT NULL
```

You can also serialize data by adding `(JSON)` to the begining of the key:

```php
<?php

$query->where([
    "(JSON)login_log" => ["date1", "date2", "date3"]
]); // WHERE `login_log`='["date1","date2","date3"]'
```

You can also add `AND` and `OR` statemets:

```php
<?php

$query->where([
    "AND" => [
        "OR" => [
            "username" => "test",
            "email"    => "test@test.test"
        ],
        "password" => "test"
    ]
]); // WHERE `username`='test' OR `email`='test@test.test' AND `password`='test'
```

<a name="other_sql_functions"></a>
### Other SQL functions

`\Alexya\Database\QueryBuilder` provides 3 other methods for SQL clauses:

The method `\Alexya\Database\QueryBuilder::limit` begins a `LIMIT` clause and can accept an integer or an array as parameter:

```php
<?php

$query->limit(1); //LIMIT 1
$query->limit([1, 10]); //LIMT 1, 10
```

The method `\Alexya\Database\QueryBuilder::offset` begins a `OFFSET` clause and accepts an integer as parameter:

```php
<?php

$query->offset(10); //OFFSET 10
```

The method `\Alexya\Database\QueryBuilder::sql` appends raw SQL to the query, this method does not avoids SQL injection
so it's not recommended to use unless you know what you're doing:

```php
<?php

$query->sql("SELECT * FROM users WHERE username='test'"); //SELECT * FROM users WHERE username='test'
```

<a name="orm"></a>
ORM
---

The class `\Alexya\Database\ORM\Model` acts as the mediator between the database table and the PHP code.

Before anything you should initialize the class with the method `initialize`.
It accepts as parameter an object of type `\Alexya\Database\Connection` being the connection to the database
and a string being the base namespace where the Model classes are located, this is if you want to store the Model
classes in a separated namespace (default is "\"):

```php
<?php

$connection = new Connection("localhost", 3306, "root", "", "alexya");
Model::initialize($connection, "\Application\ORM");
```

You should write a class that extends this for each model, but when you're following the naming conventions
you'll surely finish with a package full of empty classes.
To prevent this you can use the method `instance` which accepts as parameter the name of the database table.

Extending this class allows you to take more control over it. You can specify the name of the table, the name of
the primary key, relations...

The table name is, by default, the `snake_case`, plural name of the class, if you want to override it change the
property `_table` with the name of the table:

```php
<?php

class UsersTable extends Model
{
    protected $_table = "users"; // Without this, the table name would be `userstables`, see \Alexya\Database\ORM\Model::getTable
}
```

The primary key is, by default, `id`, if you want to override it change the property `_primaryKey` with the name of
the primary key:

```php
<?php

class UsersTable extends Model
{
    protected $_primaryKey = "userID";
}
```

The method `onInstance` is executed when the class has been instantiated, use it instead of the constructor.

<a name="crud"></a>
CRUD
----

CRUD stands for Create, Read, Update, Delete.

<a name="creating_records"></a>
### Creating records

To create a new record call the method `\Alexya\Database\ORM\Model::create`:

```php
<?php

$user = UsersTable::create();

$user->id       = 1;
$user->name     = "test";
$user->password = "test";
$user->email    = "test@test.test";

$user->save();
```

<a name="reading_records"></a>
### Reading records

The method `find` finds a record from the database and returns an instance of the Model class.
It accepts as parameter an integer being the value of the primary key or an array contaning the `WHERE` clause of the query:

```php
<?php

$user      = UsersTable::find(1); // SELECT * FROM `users` WHERE `usersID`=1
$otherUser = UsersTable::find([
    "name" => "test"
]); // SELECT * FROM `users` WHERE `name`='test'

if($user->name == $otherUser->name) {
    $user->name = "Test";
    $user->save;

    $otherUser->id = 2;
    $otherUser->save;
}
```

You can send a second integer parameter being the amount of records to fetch from the database.
If it's omited it will return a single record, otherwise an array of speficied amount of records.

<a name="updating_records"></a>
### Updating records

Once you have the ORM instance you can use the methods `\Alexya\Database\ORM\Model::get` and `\Alexya\Database\ORM\Model::set` to changes
the values of columns, it also has the [magic methods __get, __set, __isset and __unset](http://php.net/manual/en/language.oop5.overloading.php) for
an alternative syntax. To update the database use the method `\Alexya\Database\ORM\Model::save`:

```php
<?php

$user = UsersTable::find(1); // SELECT * FROM `users` WHERE `usersID`=1

$user->name     = "test";
$user->password = "test";

$user->save(); // UPDATE `users` SET `name`='test', `password`='test' WHERE `usersID`=1
```

<a name="deleting_records"></a>
### Deleting records

To delete records you must call the method `\Alexya\Database\ORM\Model::delete`:

```php
<?php

$user = UsersTable::find(1); // SELECT * FROM `users` WHERE `usersID`=1

$user->delete(); // DELETE FROM `users` WHERE `userID`=1
```
