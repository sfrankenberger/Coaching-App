<?php

namespace App\Filament\Coach\Resources\Questions\Pages;

use App\Filament\Coach\Resources\Questions\QuestionResource;
use Filament\Resources\Pages\ListRecords;

class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;
}
