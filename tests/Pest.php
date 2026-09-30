<?php

/*
| Pest laeuft neben PHPUnit: bestehende Klassen-Tests bleiben, neue Tests
| duerfen im Pest-Stil geschrieben werden. Beide nutzen dieselbe TestCase.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');
