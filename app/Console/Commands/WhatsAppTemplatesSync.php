<?php

namespace App\Console\Commands;

use App\Enums\OrganizationRole;
use App\Models\Integration;
use App\Models\WhatsAppTemplate;
use App\Support\OrganizationContext;
use App\WhatsApp\TemplateRegistry;
use App\WhatsApp\WhatsAppException;
use Illuminate\Console\Command;

/**
 * Lists the WhatsApp template registry. With --live it first reads template names and
 * review statuses from Meta (WhatsApp Business Account) and updates the registry.
 */
class WhatsAppTemplatesSync extends Command
{
    protected $signature = 'whatsapp:templates:sync {--live : Read template statuses from Meta with the saved access token}';

    protected $description = 'List the WhatsApp template registry, or update it from Meta with --live';

    public function handle(TemplateRegistry $registry): int
    {
        $failed = false;
        $integrations = Integration::query()->withoutGlobalScope('organization')->with('organization')->where('type', 'whatsapp')->get();

        if ($integrations->isEmpty()) {
            $this->warn('No WhatsApp integration is saved.');

            return self::SUCCESS;
        }

        foreach ($integrations as $integration) {
            if ($integration->organization === null) {
                continue;
            }

            OrganizationContext::set($integration->organization, OrganizationRole::Owner);
            $this->line('');
            $this->line('<options=bold>'.$integration->organization->name.'</>');

            if ($this->option('live')) {
                $failed = ! $this->syncLive($registry, $integration) || $failed;
            }

            $rows = WhatsAppTemplate::query()->orderBy('purpose')->orderBy('language')->get()
                ->map(fn (WhatsAppTemplate $template) => [
                    $template->purpose,
                    $template->name,
                    $template->language,
                    $template->status,
                    implode(', ', $template->parameterFields()),
                    $template->synced_at?->toDateTimeString() ?? '-',
                ]);

            if ($rows->isEmpty()) {
                $this->line('  No templates registered. Run with --live to register and check them.');
            } else {
                $this->table(['Purpose', 'Name', 'Language', 'Status', 'Parameters {{1}}..', 'Synced (UTC)'], $rows->all());
            }

            OrganizationContext::forget();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function syncLive(TemplateRegistry $registry, Integration $integration): bool
    {
        try {
            $report = $registry->sync($integration);
        } catch (WhatsAppException $exception) {
            $this->error("  Could not read templates from Meta ({$exception->reason}).");

            return false;
        }

        $this->info("  Updated {$report['updated']} registry entries from Meta.");

        foreach ($report['missing'] as $name) {
            $this->warn("  Not found in Meta: {$name}. Create it in WhatsApp Manager (docs/whatsapp-live-setup.md).");
        }

        foreach ($report['unregistered'] as $name) {
            $this->line("  In Meta but not used by OpsAI: {$name}");
        }

        return true;
    }
}
