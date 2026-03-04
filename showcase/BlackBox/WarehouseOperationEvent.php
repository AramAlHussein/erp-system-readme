<?php

class WarehouseOperationEvent
{
    use Dispatchable;
    use SerializesModels;

    /** @var string Type of the affected entity (e.g., Item, Warehouse, Supplier) */
    public string $entityType;

    /** @var int|null ID of the affected entity, if applicable */
    public ?int $entityId;

    /** @var string Status of the operation: 'trying', 'success', 'failed' */
    public string $status;

    /** @var string Type of operation: 'create', 'update', 'delete', 'transfer' */
    public string $operationType;

    /** @var string|null Username or ID of the user performing the operation */
    public ?string $performedBy;

    /** @var array|null Snapshot of data before the operation */
    public ?array $dataBefore;

    /** @var array|null Snapshot of data after the operation */
    public ?array $dataAfter;

    /** @var array|null Additional context or metadata for the operation */
    public ?array $context;

    /** @var string|null IP address of the user performing the operation */
    public ?string $ipAddress;

    /** @var string|null User agent string of the client device */
    public ?string $userAgent;

    /**
     * WarehouseOperationEvent constructor.
     *
     * @param array{
     *   entity_type: string,
     *   entity_id?: int|null,
     *   status: string,
     *   operation_type: string,
     *   performed_by?: string|null,
     *   data_before?: array|null,
     *   data_after?: array|null,
     *   context?: array|null,
     *   ip_address?: string|null,
     *   user_agent?: string|null
     * } $data
     */
    public function __construct(array $data)
    {
        $this->entityType    = $data['entity_type'];
        $this->entityId      = $this->getValue($data['entity_id']);
        $this->status        = $data['status'];
        $this->operationType = $data['operation_type'];
        $this->performedBy   = $this->getValue($data['performed_by']);
        $this->dataBefore    = $data['data_before'];
        $this->dataAfter     = $data['data_after'];
        $this->context       = $data['context'];
        $this->ipAddress     = $this->getValue($data['ip_address']);
        $this->userAgent     = $this->getValue($data['user_agent']);
    }

    private function getValue(int|string|null $value): int|string|null
    {
        return $value ?? null;
    }
}
