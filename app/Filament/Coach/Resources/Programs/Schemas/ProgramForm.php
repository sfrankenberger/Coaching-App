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
                Select::make('type')->label('Art')->options(Program::TYPES)->required()->native(false)->live(),
                Select::make('pacing')->label('Taktung')->options(Program::PACINGS)->required()->native(false)
                    ->helperText('Wöchentlich: Schritte schalten sich zum eingetragenen Zeitpunkt frei. Alles offen: Selbstlernen. Keine Schritte: 1:1.'),
                TextInput::make('settings.sitzungen_gesamt')->label('Einzelsitzungen im Paket')->numeric()->minValue(0)->maxValue(200)
                    ->helperText('So viele Einzelsitzungen sind enthalten, auch in Hybrid-Kursen. Die Person sieht, wie viele noch offen sind.')
                    ->visible(fn ($get) => $get('type') !== 'workbook'),
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
                Toggle::make('settings.gratis')->label('Offen für alle')->helperText('Gratiskurs: alle Personen mit Zugang zum Bereich sehen ihn, ohne Kauf.'),
                Toggle::make('settings.kommt_bald')->label('Als «Kommt bald» zeigen')->helperText('Noch nicht veröffentlicht, aber alle sehen die Karte in Meine Kurse.'),
                Toggle::make('settings.strecke')->label('Begleitstrecke')->live()->helperText('Wer hängt, bekommt nach 2 und 7 Tagen eine Mail. Wer durch ist, bekommt die eigenen Goldnuggets, das Team einen Push.'),
                TextInput::make('settings.strecke_gespraech')->label('Link zum Gespräch in den Mails')->url()->maxLength(500)->placeholder('leer: die Buchungsseite, wenn eingeschaltet')->visible(fn ($get) => (bool) $get('settings.strecke')),
                TextInput::make('settings.strecke_goldnuggets')->label('Übungsteil für die Goldnuggets')->maxLength(60)->placeholder('Schlüssel oder Nummer, leer: Listen der letzten Einheit')->visible(fn ($get) => (bool) $get('settings.strecke')),
                Select::make('settings.teilen')->label('Teilen mit der Coachin')->options(['1' => 'Ja, Antworten können geteilt werden', '0' => 'Nein, nichts wird geteilt'])->placeholder('Vorgabe nach Art')->native(false)
                    ->helperText('Vorgabe: Hybrid-Coaching, 1:1 und Arbeitsbuch ja, Selbstlernkurs und Club nein.'),
                Select::make('settings.gemeinschaft')->label('Eigener Raum in der Community')->options(['1' => 'Ja, die Gruppe teilt untereinander', '0' => 'Nein, Fragen laufen in der allgemeinen Community'])->placeholder('Vorgabe nach Art')->native(false)
                    ->helperText('Vorgabe: Hybrid-Coaching und Club ja, Selbstlernkurs nein. Selbstlernkurse werden bei Fragen nur vermerkt.'),
                Select::make('topics')->label('Themen')->relationship('topics', 'name')->multiple()->preload()->searchable()->columnSpanFull()->createOptionForm([TextInput::make('name')->label('Thema')->required()->maxLength(120)]),
            ])->columns(3)->collapsed(),
        ]);
    }
}
