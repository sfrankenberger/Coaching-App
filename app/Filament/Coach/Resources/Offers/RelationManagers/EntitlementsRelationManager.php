<?php

namespace App\Filament\Coach\Resources\Offers\RelationManagers;

use App\Models\Membership;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EntitlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'entitlements';

    protected static ?string $title = 'Zugänge';

    protected static ?string $modelLabel = 'Zugang';

    protected static ?string $pluralModelLabel = 'Zugänge';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Person')->required()->searchable()->native(false)
                ->options(fn () => Membership::query()->with('user')->get()->mapWithKeys(fn (Membership $m) => [$m->user_id => $m->user->name.' ('.$m->user->email.')'])->all()),
            Select::make('source')->label('Quelle')->options(['manual' => 'Von Hand', 'woocommerce' => 'WooCommerce', 'stripe' => 'Stripe', 'import' => 'Import'])->default('manual')->native(false),
            TextInput::make('source_ref')->label('Bestell- oder Abo-Nr.')->maxLength(120),
            Select::make('status')->label('Status')->options(['active' => 'Aktiv', 'ended' => 'Beendet', 'cancelled' => 'Storniert'])->default('active')->native(false),
            DateTimePicker::make('starts_at')->label('Ab')->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->default(now()),
            DateTimePicker::make('ends_at')->label('Bis')->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->helperText('Leer heisst unbegrenzt.'),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')->label('Name')->searchable(),
                TextColumn::make('user.email')->label('E-Mail')->searchable(),
                TextColumn::make('source')->label('Quelle')->badge(),
                TextColumn::make('source_ref')->label('Nr.'),
                TextColumn::make('status')->label('Status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('starts_at')->label('Ab')->date('d.m.Y'),
                TextColumn::make('ends_at')->label('Bis')->date('d.m.Y')->placeholder('unbegrenzt'),
            ])
            ->headerActions([CreateAction::make()->label('Zugang geben')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
