<?php

/**
 * Add 'module' and 'position_id' columns to the roles table.
 *
 * - 'module' is a nullable string to group roles by application module.
 * - 'position_id' is a nullable foreign key referencing positions.id.
 *   On delete cascade is enforced to delete roles when related positions are deleted.
 *
 * Note:
 * - The 'module' column is useful for logically grouping permissions, improving
 *   management and filtering capabilities within the system.
 *
 * Migration Methods:
 * - up(): Adds 'module' and 'position_id' columns to 'roles' table.
 * - down(): Removes these columns to rollback changes.
 *
 * @package Database\Migrations
 */
return new class() extends Migration {
    /**
     * Run the migrations to add new columns to roles table.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('module')
                ->nullable()
                ->after('name')
                ->comment('Group roles by application module');

            // Notes Linking FK in anther file create_foreign_keys_access_control
            $table->unsignedBigInteger('position_id')
                ->nullable()
                ->after('module')
                ->comment('Foreign key referencing positions.id');
        });
    }

    /**
     * Reverse the migrations by dropping the added columns.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('module');
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
        });
    }
};
