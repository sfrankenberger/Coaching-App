<?php

namespace App\Support\Database;

use Illuminate\Database\MySqlConnection;

class MySqlVerbindung extends MySqlConnection
{
    use UtcBindings;
}
