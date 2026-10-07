<?php

use App\Database\Connectors\SqlServerConnector;

it('omits the PDO option rejected by the installed SQL Server driver', function () {
    $options = (new SqlServerConnector)->getOptions([]);

    expect($options)
        ->toHaveKey(PDO::ATTR_CASE)
        ->toHaveKey(PDO::ATTR_ERRMODE)
        ->toHaveKey(PDO::ATTR_ORACLE_NULLS)
        ->not->toHaveKey(PDO::ATTR_STRINGIFY_FETCHES);
});
