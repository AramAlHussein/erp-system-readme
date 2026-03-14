<?php

/**
 * SHOWCASE: Route-Based Permission Sync Engine
 *
 * This service is the engine behind the Zero-Config Permission Enforcement system.
 * (see ADR-004 and AutoDetectPermission middleware)
 *
 * Problem:
 * In a modular ERP with hundreds of named routes, keeping the permission table
 * in sync with actual routes is a maintenance burden — and doing it manually
 * guarantees human error.
 *
 * Solution:
 * Derive permissions directly from route names.
 * The route name IS the permission name. No manual mapping, no duplication.
 *
 * How it works:
 *   1. Load active modules from modules_statuses.json
 *   2. Load existing permissions from DB
 *   3. Scan all named routes — extract those belonging to active modules
 *   4. Diff: what's new (create), what's gone (delete), what's unchanged (skip)
 *   5. Apply changes — or simulate with --dry
 *
 * Display names are auto-generated from the route name:
 *   'hr.employees.create' → 'Employees Create'
 *
 * Supports:
 *   --module  : scope sync to one module
 *   --dry     : preview changes without writing to DB
 *   --clean   : delete permissions no longer present in routes
 *
 * @see SyncPermissionsFromRoutes  The Artisan Command that drives this service
 * @see AutoDetectPermission        The middleware that enforces these permissions
 */
class PermissionSyncService
{
    /**
     * List of active module names (lowercase).
     *
     * @var string[]
     */
    protected array $activeModules;

    /**
     * Permissions extracted from routes.
     *
     * @var string[]
     */
    protected array $routePermissions = [];

    /**
     * Permissions currently stored in the database.
     *
     * @var string[]
     */
    protected array $existingPermissions = [];

    /**
     * Synchronize permissions with options.
     *
     * Options:
     *  - 'module' (string|null): Filter sync to a specific module.
     *  - 'dry' (bool): If true, simulate changes without saving.
     *  - 'clean' (bool): If true, delete permissions not present in routes.
     *
     * @param  array $options
     * @return array Result summary with keys: created, deleted, skipped (arrays of permission names)
     */
    public function sync(array $options = []): array
    {
        $this->loadModules($options['module'] ?? null);
        $this->loadExistingPermissions();
        $this->loadRoutePermissions();

        $toCreate = array_diff($this->routePermissions, $this->existingPermissions);
        $toDelete = array_diff($this->existingPermissions, $this->routePermissions);
        $skipped  = array_intersect($this->routePermissions, $this->existingPermissions);

        $resultInfo = $this->actionProcessOnPermission($options, $toCreate, $toDelete);

        return [
            'created' => $resultInfo['created'],
            'deleted' => $resultInfo['deleted'],
            'skipped' => $skipped,
        ];
    }

    /**
     * Load active modules from 'modules_statuses.json'.
     * If $only is provided, load only that module.
     *
     * @param string|null $only
     */
    protected function loadModules(?string $only = null): void
    {
        $path = base_path('modules_statuses.json');
        if (!file_exists($path)) {
            $this->logError('loadModules', 'Loading-file', ['Path-File' => $path]);
            $this->activeModules = [];

            return;
        }

        $modulesJson = json_decode(file_get_contents(base_path('modules_statuses.json')), true);

        if (!is_array($modulesJson)) {
            $this->logError('loadModules', 'Error with processing data (josn error)', ['Path-File' => $path, 'Payload' => $modulesJson]);
            $this->activeModules = [];

            return;
        }

        $active = collect($modulesJson)
            ->filter(fn($status) => $status)
            ->keys()
            ->map(fn($m) => strtoupper($m));

        $this->activeModules = $only ? [strtoupper($only)] : $active->toArray();
    }

    /**
     * Load existing permissions names from the database.
     */
    protected function loadExistingPermissions(): void
    {
        $this->existingPermissions = Permission::byRoutePermission()->pluck('name')->toArray();
    }

    /**
     * Load permissions from named routes of active modules.
     *
     * Only routes with a name are considered, and the route's module prefix
     * must be in the active modules list.
     */
    protected function loadRoutePermissions(): void
    {
        $routes                 = Route::getRoutes();
        $this->routePermissions = [];

        foreach ($routes as $route) {
            $name = $route->getName();
            if (!$name) {
                continue;
            }

            $module = strtoupper($this->extractModule($name));
            if (!in_array($module, $this->activeModules)) {
                continue;
            }

            $this->routePermissions[] = $name;
        }
    }

    /**
     * Extract the module name from a permission name.
     * For example, 'hr.employees.create' -> 'HR'
     *
     * @param  string $name
     * @return string
     */
    protected function extractModule(string $name): string
    {
        return strtoupper(explode('.', $name)[0] ?? 'general');
    }

    /**
     * Generate a human-readable display name from a permission name.
     * Skips the module prefix and formats the remaining parts with spaces and capitalization.
     *
     * Example:
     *   'hr.employees.create' => 'Employees Create'
     *
     * @param  string      $permissionName
     * @return string|null
     */
    protected function generateDisplayName(string $permissionName): ?string
    {
        $parts       = explode('.', $permissionName);
        $parts       = array_slice($parts, 1);
        $displayName = implode(' ', $parts);
        $displayName = ucwords(str_replace('_', ' ', $displayName));

        return $displayName;
    }

    /**
     * Create or update a permission in the database with multilingual display names.
     *
     * If a permission with the given name already exists, it will be updated.
     * Otherwise, a new permission record will be created.
     *
     * @param string|null $name        The unique permission name (usually from route name).
     * @param string      $module      The name of the module this permission belongs to.
     * @param string      $displayName A human-readable name (used for both Arabic and English).
     */
    private function updateOrCreatePermission(?string $name, string $module, string $displayName): void
    {
        Permission::updateOrCreate(
            ['name' => $name],
            [
                'module'       => $module,
                'display_name' =>  [
                    'ar' => $displayName,
                    'en' => $displayName,
                ],
                'is_route_permission' => true,
            ]
        );
    }

    /**
     * Process creation and deletion of permissions during sync operation.
     *
     * - Creates new permissions that are missing in the database.
     * - Optionally deletes permissions that no longer exist in active routes (if 'clean' option is true).
     * - Skips saving if 'dry' run is enabled.
     *
     * @param array $options  Options for sync operation:
     *                        - 'dry': (bool) If true, no changes are saved.
     *                        - 'clean': (bool) If true, old permissions are deleted.
     * @param array $toCreate List of permission names to create.
     * @param array $toDelete List of permission names to delete.
     *
     * @return array{
     *     created: string[], // Names of newly created permissions
     *     deleted: string[]  // Names of deleted permissions (if clean enabled)
     * }
     */
    private function actionProcessOnPermission(array $options, array $toCreate, array $toDelete): array
    {
        $created = [];
        $deleted = [];

        if (!($options['dry'] ?? false)) {
            // Create new permissions
            foreach ($toCreate as $name) {
                $module      = $this->extractModule($name);
                $displayName = $this->generateDisplayName($name);

                $this->updateOrCreatePermission($name, $module, $displayName);

                $created[] = $name;
            }

            // Delete obsolete permissions if 'clean' option is set
            if (!empty($options['clean'])) {
                Permission::whereIn('name', $toDelete)->delete();
                $deleted = $toDelete;
            }
        }

        return [
            'created' => $created,
            'deleted' => $deleted,
        ];
    }

    /**
     * Log a structured error message with method context.
     *
     * This helper method is used to standardize error logging throughout the repository.
     * It merges the provided context with the method name to facilitate debugging.
     *
     * @param string $type    Logical name of the log (e.g., 'error', 'info').
     * @param string $action  Logical name of the current action (e.g., 'create', 'update', 'destroy').
     * @param array  $context Optional additional context (e.g., payload, error message).
     */
    protected function logError(string $type, string $action, array $context = []): void
    {
        Log::error("PermissionSyncService::{$type} error during {$action}", array_merge([
            'method' => __METHOD__,
        ], $context));
    }
}
