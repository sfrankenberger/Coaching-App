<?php

namespace App\Support\Database;

use Illuminate\Database\MariaDbConnection;

class MariaDbVerbindung extends MariaDbConnection
{
    use UtcBindings;
}
