<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\SqlServerConnector as FrameworkSqlServerConnector;
use PDO;

class SqlServerConnector extends FrameworkSqlServerConnector
{
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
    ];
}
