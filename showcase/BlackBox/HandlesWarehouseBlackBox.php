<?php

trait HandlesWarehouseBlackBox
{
    use HandlesResponse;

    protected string $modelName;

    protected WarehouseBlackBoxServiceInterface $service;

    /**
     * Initialize the blackbox service.
     *
     * ARCHITECTURAL DECISION — Service Container vs Constructor Injection:
     *
     * Constructor Injection was intentionally avoided here.
     * This Trait is used across 10+ Service classes. Explicit injection
     * would require adding WarehouseBlackBoxServiceInterface to every
     * constructor — unnecessary boilerplate with no architectural benefit.
     *
     * Why app() is safe here:
     * - BlackBox is core infrastructure, not business logic.
     * - Its binding never changes within this system's lifecycle.
     * - This is a closed Enterprise ERP — container bindings are fully controlled.
     *
     * Trade-off acknowledged: Reduces testability in isolation.
     * Accepted: BlackBox logging is tested at integration level, not unit level.
     *
     * @param string $modelName Name of the model affected.
     */
    public function initWarehouseBlackBox(string $modelName)
    {
        // Resolving core immutable service directly to reduce constructor clutter
        $this->service        = app(WarehouseBlackBoxServiceInterface::class);
        $this->modelName      = $modelName;
        $this->exceptionClass = LogicException::class;
    }

    /**
     * Trigger a WarehouseOperationEvent for logging purposes.
     *
     * @param string     $modelName  Name of the model affected.
     * @param string     $method     Operation method (create, update, delete, transfer).
     * @param string     $status     Status of the operation: trying, success, failed.
     * @param array|null $dataBefore Snapshot of data before the operation.
     * @param int|null   $entityID   ID of the entity affected.
     * @param array|null $dataAfter  Snapshot of data after the operation.
     * @param array|null $context    Additional context for the operation.
     *
     *
     * @throws LogicException if the black box service is not initialized.
     */
    public function logAttempted(
        string $modelName,
        string $method,
        string $status,
        ?array $dataBefore = null,
        ?int $entityID = null,
        ?array $dataAfter = null,
        ?array $context = null
    ): void {

        $this->throwIf(
            !isset($this->service),
            new LogicException('WarehouseBlackBoxServiceInterface not initialized.')
        );

        event(new WarehouseOperationEvent(
            $this->service->getAttempt(
                $modelName,
                $method,
                $status,
                $dataBefore,
                $entityID,
                $dataAfter,
                $context
            )
        ));
    }

    /**
     * Shortcut for logging a "trying" status operation
     */
    public function logTrying(
        string $method,
        ?array $dataBefore = null,
        ?int $entityID = null,
        ?array $dataAfter = null,
        ?array $context = null
    ): void {
        $this->logAttempted($this->modelName, $method, 'trying', $dataBefore, $entityID, $dataAfter, $context);
    }

    /**
     * Shortcut for logging a "success" status operation
     */
    public function logSuccess(
        string $method,
        ?array $dataBefore = null,
        ?int $entityID = null,
        ?array $dataAfter = null,
        ?array $context = null
    ): void {
        $this->logAttempted($this->modelName, $method, 'success', $dataBefore, $entityID, $dataAfter, $context);
    }

    /**
     * Shortcut for logging a "failed" status operation
     */
    public function logFailed(
        string $method,
        ?array $dataBefore = null,
        ?int $entityID = null,
        ?array $dataAfter = null,
        ?array $context = null
    ): void {
        $this->logAttempted($this->modelName, $method, 'failed', $dataBefore, $entityID, $dataAfter, $context);
    }
}
