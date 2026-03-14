<?php

/**
 * SHOWCASE: Event-Driven Business Automation via Model Observer
 *
 * Problem:
 * After a Purchase Requisition (PR) is approved, the system must automatically
 * create the next document in the procurement lifecycle — either an RFQ or a PO.
 * Placing this logic inside the controller or service couples the approval action
 * to the downstream creation logic, making both harder to test and extend.
 *
 * Solution:
 * A Model Observer listens for status changes on the PR model.
 * When the status transitions to 'approved', it delegates document creation
 * to isolated protected methods — completely decoupled from the approval action itself.
 *
 * Lifecycle this Observer drives:
 *
 *   PR (approved)
 *    ├── conversion_target = 'rfq'  →  creates RFQ with items copied from PR
 *    └── conversion_target = 'po'   →  creates PO in draft status with items copied from PR
 *
 * Guard:
 * If an RFQ or PO already exists for this PR, the Observer exits silently.
 * This prevents duplicate document creation if the status is updated more than once.
 *
 * Important:
 * Do NOT update PR status directly in the database.
 * Always go through the model to trigger this Observer.
 */
class PurchaseRequisitionObserver
{
    /**
     * React only to status changes on the PR.
     * If the new status is 'approved', trigger the appropriate document creation.
     */
    public function updated(PurchaseRequisition $pr): void
    {
        if (!$pr->isDirty('status_id')) {
            return;
        }

        // $pr->status->code === 'approved'
        if ($pr->isApproved()) {

            // Guard: downstream document already exists
            if ($pr->rfqs()->exists() || $pr->po()->exists()) {
                return;
            }

            match ($pr->conversion_target) {
                'rfq' => $this->createRFQ($pr),
                'po'  => $this->createPO($pr),
            };
        }
    }

    /**
     * Create an RFQ from the approved PR.
     * Items are copied directly from the PR line items.
     * RFQ starts in 'on_hold' status pending supplier selection.
     */
    protected function createRFQ(PurchaseRequisition $pr): void
    {
        $rfq = $pr->rfqs()->create([
            'code'         => RequestForQuotation::generateCode(),
            'rfq_date'     => null,
            'valid_until'  => null,
            'status_id'    => app(StatusServiceInterface::class)
                ->findByCode('on_hold', 'purchases.request_for_quotation')->id,
            'triggered_by' => $pr->approver?->approved_by_user_id,
        ]);

        foreach ($pr->items as $item) {
            $rfq->items()->create([
                'item_id'  => $item->item_id,
                'unit_id'  => $item->unit_id,
                'quantity' => $item->quantity,
                'notes'    => $item->notes,
            ]);
        }
    }

    /**
     * Create a PO directly from the approved PR, bypassing the RFQ stage.
     * PO is created in 'draft' document_status with zero amounts.
     * Financial details (supplier, price, currency) are filled in manually after creation.
     *
     * Wrapped in a DB transaction to ensure PO and its items are created atomically.
     */
    protected function createPO(PurchaseRequisition $pr): void
    {
        DB::transaction(function () use ($pr) {

            $po = PurchaseOrder::create([
                'po_number'       => PurchaseOrder::generateCode(),
                'po_date'         => now(),
                'source_type'     => 'pr',
                'source_id'       => $pr->id,
                'document_status' => 'draft',
                'process_status'  => null,
                'subtotal_amount' => 0,
                'tax_amount'      => 0,
                'total_amount'    => 0,
                'created_by'      => $pr->updated_by,
                'notes'           => $pr->notes,
                // supplier, currency, payment_term filled manually after creation
            ]);

            foreach ($pr->items as $item) {
                $po->items()->create([
                    'item_id'          => $item->item_id,
                    'unit_id'          => $item->unit_id,
                    'ordered_quantity' => $item->quantity,
                    'unit_price'       => 0,
                    'line_subtotal'    => 0,
                    'line_total'       => 0,
                    'notes'            => $item->notes,
                ]);
            }
        });
    }
}
