<?php

namespace App\Filament\Coach\Resources\Memberships\Pages;

use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateMembership extends CreateRecord
{
    protected static string $resource = MembershipResource::class;

    /**
     * Gibt es die Person (Mailadresse) schon plattformweit, wird sie verknuepft statt neu angelegt.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $person = $data['user'] ?? [];
        unset($data['user']);

        $user = User::firstOrCreate(
            ['email' => Str::lower(trim($person['email'] ?? ''))],
            ['name' => $person['name'] ?? '', 'phone' => $person['phone'] ?? null],
        );

        $data['user_id'] = $user->id;
        $data['joined_at'] ??= now();

        return static::getModel()::create($data);
    }
}
