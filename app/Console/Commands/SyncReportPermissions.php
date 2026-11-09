<?php

namespace App\Console\Commands;

use App\Support\ReportCatalog;
use Illuminate\Console\Command;

class SyncReportPermissions extends Command
{
    protected $signature = 'reports:sync-permissions';
    protected $description = 'Create any missing report permissions and give them to Super Admin';

    public function handle(): int
    {
        $n = ReportCatalog::syncPermissions();
        $this->info($n ? "Created $n missing report permission(s) and granted them to Super Admin." : 'All report permissions already exist.');
        return self::SUCCESS;
    }
}
