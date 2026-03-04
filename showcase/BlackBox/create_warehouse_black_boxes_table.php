<?php


/**
 * CreateWarehouseBlackBoxesTable Migration
 *
 * Creates the `warehouse_black_boxes` table which stores audit logs (black box)
 * for all warehouse-related operations. This table acts as a forensic trail for
 * create, update, delete, and transfer actions across all warehouse entities.
 *
 * @package Database\Migrations
 */
return new class() extends Migration {
    /**
     * Run the migrations.
     *
     * Creates the `warehouse_black_boxes` table with the following columns:
     *
     * @property int         $id             Primary key of the black box record.
     * @property string      $entity_type    Type of the affected entity (e.g., Item, Warehouse, Supplier).
     * @property int|null    $entity_id      ID of the affected entity in its respective table (nullable).
     * @property string      $status         Operation status: trying, success, or failed.
     * @property string      $operation_type Operation performed: create, update, delete, or transfer.
     * @property string|null $performed_by   Username of the user who performed the operation (nullable).
     * @property \DateTime   $performed_at   Timestamp when the operation was performed.
     * @property int         $performed_year Year extracted from `performed_at`, used for partitioning.
     * @property array|null  $data_before    JSON snapshot of the entity before the operation (nullable).
     * @property array|null  $data_after     JSON snapshot of the entity after the operation (nullable).
     * @property array|null  $context        Additional metadata or error details related to the operation (nullable).
     * @property string|null $ip_address     IP address of the user performing the operation (nullable).
     * @property string|null $user_agent     User agent string of the client device (nullable).
     * @property \DateTime   $created_at     Timestamp of record creation.
     * @property \DateTime   $updated_at     Timestamp of last record update.
     */
    public function up(): void
    {
        Schema::create('warehouse_black_boxes', function (Blueprint $table) {
            $table->bigIncrements('id')
                ->comment('Primary key: warehouse_black_boxes ID');

            // Affected entity
            $table->string('entity_type')
                ->comment('Type of the entity affected, e.g., Item, Warehouse, Supplier');
            $table->unsignedBigInteger('entity_id')
                ->nullable()
                ->comment('ID of the affected entity in its respective table');

            // operation type and status
            $table->enum('status', ['trying', 'success', 'failed'])->default('trying')
                ->comment('Status of the operation: trying, success, or failed');
            $table->enum('operation_type', ['create', 'update', 'delete', 'transfer'])
                ->comment('Type of operation performed: create, update, delete, or transfer');

            // who performed the operation and time
            $table->string('performed_by')
                ->nullable()
                ->comment('Username of the user who performed the operation.
               Usernames are auto-generated at account creation and
               are immutable; no system-level modification is permitted.
               Manual DB edits would break audit integrity.');
            $table->timestamp('performed_at')
                ->useCurrent()
                ->comment('Timestamp when the operation was performed');
            $table->unsignedSmallInteger('performed_year')
                ->storedAs('YEAR(`performed_at`)')
                ->comment('Year of the operation, used for partitioning');

            // Data after and before
            $table->json('data_before')
                ->nullable()
                ->comment('JSON snapshot of the entity before the operation');
            $table->json('data_after')
                ->nullable()
                ->comment('JSON snapshot of the entity after the operation');

            // More information as (number of exception for the failed status)
            $table->json('context')
                ->nullable()
                ->comment('Additional context or metadata related to the operation');

            // Optional: loging user
            $table->string('ip_address')
                ->nullable()
                ->comment('IP address of the user performing the operation');
            $table->string('user_agent')
                ->nullable()
                ->comment('User agent string of the client device');

            // Indexes
            $table->primary(['id', 'performed_year'])->comment('Primary key including performed_year for partitioning');
            $table->index('performed_at');
            $table->index('performed_by');
            $table->index(['entity_type', 'entity_id']);
            $table->index(['performed_by', 'performed_at']);
            $table->index(['performed_by', 'performed_at', 'performed_year'], 'idx_blackboxes_user_time_year');

            $table->timestamps();
        });

        // Laravel's Blueprint doesn't support composite PKs with generated columns directly.
        // We set it via raw statement to enforce the partition-aware primary key.
        DB::statement('ALTER TABLE warehouse_black_boxes DROP PRIMARY KEY, ADD PRIMARY KEY (`id`, `performed_year`)');

        $this->addPartitioning();
    }

    private function addPartitioning(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                DB::statement('
                    ALTER TABLE warehouse_black_boxes
                    PARTITION BY RANGE (performed_year) (
                        PARTITION p2025  VALUES LESS THAN (2026),
                        PARTITION p2026 VALUES LESS THAN (2027),
                        PARTITION p2027 VALUES LESS THAN (2028),
                        PARTITION p2028 VALUES LESS THAN (2029),
                        PARTITION p2029 VALUES LESS THAN (2030),
                        PARTITION p2030 VALUES LESS THAN (2031),
                        PARTITION p_future VALUES LESS THAN MAXVALUE
                    )
                ');
            } catch (\Exception $e) {
                Log::warning('Partitioning not supported: ' . $e->getMessage());
            }
        }
    }
};
