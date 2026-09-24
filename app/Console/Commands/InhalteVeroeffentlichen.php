<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Tenant;
use App\Observers\PostObserver;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/** Geplante Beitraege, deren Zeit gekommen ist, melden (alle zehn Minuten). */
class InhalteVeroeffentlichen extends Command
{
    protected $signature = 'inhalte:veroeffentlichen';

    protected $description = 'Faellige Beitraege an die Mitglieder melden';

    public function handle(CurrentTenant $current): int
    {
        foreach (Tenant::where('is_active', true)->get() as $tenant) {
            $current->run($tenant, function () use ($tenant) {
                $due = Post::query()->published()->whereNull('notified_at')->whereNotNull('notify_channels')->where('source', 'app')->get();
                foreach ($due as $post) {
                    app(PostObserver::class)->notify($post);
                }
                if ($due->isNotEmpty()) {
                    $this->line("{$tenant->slug}: {$due->count()} gemeldet");
                }
            });
        }

        return self::SUCCESS;
    }
}
