<?php

/**
 * SHOWCASE: Artisan Command — permissions:sync
 *
 * CLI interface for the PermissionSyncService.
 * Designed as a DevOps-friendly tool with three operational modes:
 *
 *   php artisan permissions:sync                    # full sync
 *   php artisan permissions:sync --module=HR        # scope to one module
 *   php artisan permissions:sync --dry              # preview — no DB writes
 *   php artisan permissions:sync --clean            # remove obsolete permissions
 *
 * After sync, the permission cache is cleared automatically
 * so AutoDetectPermission picks up the new permissions immediately.
 *
 * When to run:
 *   - After adding new routes to any module
 *   - After renaming existing routes
 *   - During deployment as part of the release checklist
 *
 * @see PermissionSyncService  The engine that performs the actual sync logic
 * @see ADR-004                The architectural decision behind this system
 */
class SyncPermissionsFromRoutes extends Command
{
    protected $signature = 'permissions:sync
                            {--module= : Sync permissions only for a specific module}
                            {--dry : Simulate the sync without saving changes}
                            {--clean : Delete permissions that are no longer in routes}';

    protected $description = 'Sync permissions from named routes of active modules';

    /**
     * Service for syncing permissions.
     *
     * @var PermissionSyncService
     */
    protected PermissionSyncService $syncService;

    /**
     * Service for cache permissions.
     *
     * @var PermissionCacheService
     */
    protected PermissionCacheService $cacheService;

    public function __construct(PermissionSyncService $syncService, PermissionCacheService $cacheService)
    {
        parent::__construct();
        $this->syncService  = $syncService;
        $this->cacheService = $cacheService;
    }

    public function handle(): void
    {
        $this->info('🔄 Starting permission sync...');

        $results = $this->syncService->sync([
            'module' => $this->option('module'),
            'dry'    => $this->option('dry'),
            'clean'  => $this->option('clean'),
        ]);

        $this->line('');
        $this->components->info('✅ Sync complete!');

        $this->table(
            ['Type', 'Count'],
            [
                ['➕ Created',                  count($results['created'])],
                ['✏️  Skipped (Already Exists)', count($results['skipped'])],
                ['❌ Deleted',                  count($results['deleted'])],
            ]
        );

        if ($this->option('dry')) {
            $this->warn('🧪 Dry run: no changes were saved.');
        }

        if ($this->option('module')) {
            $this->info('📦 Module: ' . $this->option('module'));
        }

        $this->newLine();

        // Clear cache so AutoDetectPermission picks up changes immediately
        $this->cacheService->clear();
    }
}
