<?php

/*
| Passkeys gehoeren zur Person (users, plattformweit), nicht zum Mandanten: darum ohne tenant_id.
| Die Bindung an den Mandanten passiert ueber die Relying-Party-ID (Domain), siehe TenantWebAuthn.
*/

use Illuminate\Database\Schema\Blueprint;
use Laragear\WebAuthn\Models\WebAuthnCredential;

return WebAuthnCredential::migration()->with(function (Blueprint $table) {
    // Here you can add custom columns to the Two Factor table.
    //
    // $table->string('alias')->nullable();
});
