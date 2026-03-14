<?php


/**
 * Interface ConcurrencyControlServiceInterface
 *
 * Defines methods to handle critical concurrency operations
 * for warehouse transactions and stock adjustments.
 *
 * The purpose is to acquire database-level locks where necessary
 * to prevent race conditions when multiple users or processes
 * attempt to modify the same resources simultaneously.
 */
interface ConcurrencyControlServiceInterface
{
    /**
     * Acquire an exclusive lock for a specific warehouse transaction.
     *
     * Ensures that only one process can modify the transaction at a time.
     *
     * Typically uses a row-level lock (LOCK FOR UPDATE)
     * within a database transaction.
     *
     * @param int $transactionId
     * @return WarehouseTransaction The locked transaction instance
     */
    public function acquireInvoiceLock(int $transactionId): WarehouseTransaction;

    /**
     * Acquire exclusive locks for a set of stock items in a specific warehouse.
     *
     * Ensures that concurrent modifications to the same stock records
     * are prevented during incoming, outgoing, or allocation operations.
     *
     * @param int[] $itemIds Array of item IDs to lock
     * @param int $warehouseId The warehouse ID where the stock resides
     * @return void
     */
    public function acquireStockLocks(array $itemIds, int $warehouseId): void;
}
