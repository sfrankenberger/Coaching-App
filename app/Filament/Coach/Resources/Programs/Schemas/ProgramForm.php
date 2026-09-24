<?php

namespace App\Filament\Coach\Resources\Programs\Schemas;

use App\Models\Program;
use App\Tenancy\CurrentTenant;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Programm')->schema([
                TextInput::make('title')->label('Titel')->required()->maxLength(160)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, $get) => $get('slug') ?: $set('slug', Str::slug($state))),
                TextInput::make('slug')->label('Adresse (Slug)')->required()->alphaDash()->maxLength(120)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', app(CurrentTenant::class)->id())),
                TextInput::make('subtitle')->label('Untertitel')->maxLength(200)->columnSpanFull(),
                Select::make('type')->label('Art')->options(Program::TYPES)->required()->native(false),
                Select::make('pacing')->label('Taktung')->options(Program::PACINGS)->required()->native(false)
                    ->helperText('Wöchentlich: Schritte schalten sich zum eingetragenen Zeitpunkt frei. Alles offen: Selbstlernen. Keine Schritte: 1:1.'),
                DatePicker::make('starts_at')->label('Start')->native(false)->displayFormat('d.m.Y'),
                DatePicker::make('ends_at')->label('Ende')->native(false)->displayFormat('d.m.Y'),
                RichEditor::make('description')->label('Beschreibung')->columnSpanFull()
                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'h3', 'undo', 'redo']),
            ])->columns(2),

            Section::make('Darstellung und Sichtbarkeit')->schema([
                ColorPicker::make('color')->label('Farbe'),
                TextInput::make('icon')->label('Symbol')->placeholder('z. B. graduation-cap')->maxLength(60),
                TextInput::make('cover_url')->label('Titelbild (URL)')->url()->maxLength(500)->columnSpanFull(),
                TextInput::make('position')->label('Reihenfolge')->numeric()->default(0),
                Toggle::make('is_published')->label('Veröffentlicht')->default(true),
                Toggle::make('is_internal')->label('Nur intern')->helperText('Fertig gebaut, aber nur für Team und direkt eingetragene Personen sichtbar.'),
            ])->columns(3)->collapsed(),
        ]);
    }
}
