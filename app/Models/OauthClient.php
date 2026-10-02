<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Ein Assistent (Claude, ChatGPT), der sich fuer den MCP-Server registriert hat. */
class OauthClient extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['redirect_uris' => 'array', 'last_used_at' => 'datetime'];
    }

    public function erlaubtRedirect(string $uri): bool
    {
        return in_array($uri, (array) $this->redirect_uris, true);
    }
}
