<?php

namespace App\Programs;

use App\Models\Entitlement;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Zugriffspruefung an genau einer Stelle (entspricht dem alten lea_zugang_darf).
 *
 * Eine Person sieht ein Programm, wenn
 * - sie den Mandanten verwaltet (owner, team, Plattform-Admin), oder
 * - sie direkt als Mitglied im Programm steht (program_members), oder
 * - ein laufender Zugang (entitlement) zu einem Angebot besteht, das das Programm enthaelt.
 * Interne Programme (is_internal) sehen nur Verwaltende und direkte Mitglieder.
 */
class ProgramAccess
{
    /** IDs aller Programme, die die Person gerade sehen darf. */
    public function programIdsFor(User $user): Collection
    {
        if ($user->canManageCurrentTenant()) {
            return Program::query()->pluck('id');
        }

        $direct = ProgramMember::query()->where('user_id', $user->id)->pluck('program_id');

        $viaOffers = Entitlement::query()
            ->current()
            ->where('user_id', $user->id)
            ->with('offer.programs:id,is_internal')
            ->get()
            ->flatMap(fn (Entitlement $e) => $e->offer?->programs->reject(fn ($p) => $p->is_internal)->pluck('id') ?? collect());

        return $direct->merge($viaOffers)->unique()->values();
    }

    public function canView(User $user, Program $program): bool
    {
        if (! $program->is_published && ! $user->canManageCurrentTenant()) {
            return false;
        }

        return $this->programIdsFor($user)->contains($program->id);
    }

    /** Programme der Person, sortiert, mit Schritten und Einheiten geladen. */
    public function programsFor(User $user): Collection
    {
        $ids = $this->programIdsFor($user);

        return Program::query()
            ->whereIn('id', $ids)
            ->where(fn ($q) => $q->where('is_published', true)->orWhere(fn () => $user->canManageCurrentTenant()))
            ->when(! $user->canManageCurrentTenant(), fn ($q) => $q->where('is_published', true))
            ->with(['steps', 'units'])
            ->orderBy('position')->orderBy('title')
            ->get();
    }

    /** Direktes Mitglied werden (idempotent). */
    public function join(User $user, Program $program, string $role = 'participant'): ProgramMember
    {
        return ProgramMember::firstOrCreate(
            ['program_id' => $program->id, 'user_id' => $user->id],
            ['role_in_program' => $role, 'joined_at' => now()],
        );
    }
}
