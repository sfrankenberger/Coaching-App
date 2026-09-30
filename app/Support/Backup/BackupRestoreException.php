<?php

namespace App\Support\Backup;

use RuntimeException;

/** Die Server-Schnittstelle hat nicht geantwortet oder etwas Unbrauchbares geliefert. */
class BackupRestoreException extends RuntimeException {}
