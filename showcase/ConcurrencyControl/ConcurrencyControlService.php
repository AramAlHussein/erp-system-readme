<?php

/**
 * @see ConcurrencyControlServiceInterface for the contract definition
 */

/**
 * SHOWCASE: Concurrency Control in Stock Operations
 *
 * Problem:
 * In a warehouse system with multiple concurrent users, two operations
 * can read the same stock quantity simultaneously, both decide there is
 * enough stock, and both proceed — resulting in negative stock or
 * duplicate reservations.
 *
 * Solution:
 * Before any stock mutation, acquire row-level database locks on both
 * the transaction record and all affected stock rows.
 * This forces concurrent requests to queue, not race.
 *
 * Two locks are always acquired together:
 *   1. acquireInvoiceLock()  — locks the WarehouseTransaction row
 *   2. acquireStockLocks()   — locks all matching Stock rows for the warehouse
 *
 * Both must be called inside a DB::transaction() block.
 * The locks are released automatically when the transaction commits or rolls back.
 *
 * Usage:
 *   DB::transaction(function () use ($transactionId, $itemIds, $warehouseId) {
 *       $transaction = $this->concurrencyControl->acquireInvoiceLock($transactionId);
 *       $this->concurrencyControl->acquireStockLocks($itemIds, $warehouseId);
 *       // safe to read and mutate stock here
 *   });
 */
class ConcurrencyControlService implements ConcurrencyControlServiceInterface
{
    use HandlesResponse;

    /**
     * Lock the WarehouseTransaction row for the duration of the transaction.
     *
     * Prevents another request from processing the same transaction concurrently.
     * Throws if the transaction does not exist.
     */
    public function acquireInvoiceLock(int $transactionId): WarehouseTransaction
    {
        $transaction = WarehouseTransaction::lockForUpdate()->find($transactionId);
        $this->throwIf(is_null($transaction), TransactionException::invalidRequestIdOrCode());

        return $transaction;
    }

    /**
     * Lock all Stock rows for the given items in a specific warehouse.
     *
     * The ->get() call is intentional — it materializes the query and
     * activates the row-level locks. Without it, lockForUpdate() has no effect.
     *
     * Prevents concurrent requests from reading stale quantities
     * on the same stock rows before a mutation occurs.
     */
    public function acquireStockLocks(array $itemIds, int $warehouseId): void
    {
        Stock::byWarehouseId($warehouseId)
            ->whereIn('item_id', $itemIds)
            ->lockForUpdate()
            ->get(); // do not remove — activates the locks
    }
}
