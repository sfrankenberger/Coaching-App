<?php

namespace App\Filament\Coach\Resources\Memberships\Schemas;

use App\Enums\Role;
use App\Models\Membership;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MembershipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Person')
                ->description('Die Person ist plattformweit eine, die Mailadresse ist ihr Anmeldename.')
                ->schema([
                    Group::make([
                        TextInput::make('name')->label('Name')->required()->maxLength(120),
                        TextInput::make('email')->label('E-Mail')->email()->required()->maxLength(190)
                            ->unique(table: 'users', column: 'email', ignorable: fn ($record) => $record?->user),
                        TextInput::make('phone')->label('Handynummer')->tel()->maxLength(40),
                    ])->relationship('user')->columns(1),
                ]),

            Section::make('Zugang')
                ->schema([
                    Select::make('role')->label('Rolle')->required()
                        ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all())
                        ->default(Role::Member->value)->native(false),
                    Select::make('status')->label('Status')->required()
                        ->options(Membership::statusLabels())->default('active')->native(false),
                    TextInput::make('legacy_id')->label('WordPress-ID')->disabled()->dehydrated(false),
                ])->columns(3),

            Section::make('Was die Person erreicht')
                ->schema([
                    Toggle::make('settings.notifications.termine')->label('Termin-Erinnerungen')->default(true),
                    Toggle::make('settings.notifications.abendmail')->label('Abendmail')->default(true),
                    Toggle::make('settings.notifications.aufgaben')->label('Aufgaben-Erinnerungen')->default(true),
                ])->columns(3)->collapsed(),
        ]);
    }
}
