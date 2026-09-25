<?php

namespace App\Filament\Coach\Resources\Tasks\Pages;

use App\Filament\Coach\Resources\Tasks\TaskResource;
use App\Models\Program;
use App\Models\Task;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    /** An alle im Programm: je Teilnehmerin eine eigene Aufgabe, sichtbar fuer die Coachin. */
    protected function handleRecordCreation(array $data): Model
    {
        $base = ['assigned_by' => auth()->id(), 'source' => 'coach', 'visibility' => 'coach'];
        $userIds = [];

        if ($this->data['fuer_programm'] ?? false) {
            // Kursaufgabe: Vorlage auch fuer spaeter Eintretende (ProgramMemberObserver)
            $base['source'] = 'program';
            $program = Program::find($data['program_id'] ?? 0);
            $userIds = $program ? $program->members()->pluck('user_id')->all() : [];
            $data['user_id'] = $userIds[0] ?? auth()->id();
        }

        $first = Task::create($data + $base);
        foreach (array_slice($userIds, 1) as $uid) {
            Task::create(array_merge($data, $base, ['user_id' => $uid]));
        }

        return $first;
    }
}
