<?php

namespace App\Support\Database;

use Illuminate\Database\SQLiteConnection;

class SqliteVerbindung extends SQLiteConnection
{
    use UtcBindings;
}
