<?php

use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Api\V1Controller;
use App\Tenancy\Middleware\IdentifyTenant;
use Illuminate\Support\Facades\Route;

/*
| JSON-API v1 (Sanctum-Token je Person, Mandant ueber die Domain wie in der App).
| Nur lesend, als Grundlage fuer eine spaetere native App oder Anbindungen.
| Token anlegen: php84 artisan api:token <email>
*/
Route::prefix('v1')->middleware([IdentifyTenant::class, 'auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('/ich', [V1Controller::class, 'ich']);
    Route::get('/kurse', [V1Controller::class, 'kurse']);
    Route::get('/termine', [V1Controller::class, 'termine']);
    Route::get('/mitteilungen', [V1Controller::class, 'mitteilungen']);
    Route::get('/aufgaben', [V1Controller::class, 'aufgaben']);
});

/*
| MCP-Server (Model Context Protocol, Streamable HTTP): Claude, ChatGPT oder ein anderer Assistent
| verbindet sich mit der App und nutzt die Werkzeuge aus App\Ai\Werkzeuge. Token mit Faehigkeit "mcp"
| (Profil, Schluessel fuer Verbindungen), nur fuer das Team.
*/
Route::match(['get', 'post', 'delete'], '/mcp', McpController::class)->middleware([IdentifyTenant::class, 'auth:sanctum', 'throttle:240,1'])->name('mcp');
