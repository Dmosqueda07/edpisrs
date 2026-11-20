<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $requestTable = DB::connection()->pretending()
            ? 'it_support_requests'
            : $this->resolveTable('it_support_requests', 'support_requests');
        $attachmentTable = DB::connection()->pretending()
            ? 'it_support_request_attachments'
            : $this->resolveAttachmentTable();
        $logTable = DB::connection()->pretending()
            ? 'it_support_request_logs'
            : $this->resolveTable('it_support_request_logs', 'request_logs');
        $this->migrateProofAttachments($attachmentTable);
        $this->verifyAnswers($requestTable);

        foreach ([
            'it_support_request_details',
            'it_support_request_approvals',
            'it_support_request_comments',
        ] as $legacyTable) {
            Schema::dropIfExists($legacyTable);
        }

        $this->dropForeignKeys($requestTable);
        $this->dropForeignKeys($attachmentTable);
        $this->dropForeignKeys($logTable);
        $this->dropIndexes($requestTable);
        $this->dropIndexes($attachmentTable);
        $this->dropIndexes($logTable);

        $requestTable = $this->renameTableIfNeeded($requestTable, 'support_requests');
        $attachmentTable = $this->renameTableIfNeeded($attachmentTable, 'request_attachments');
        $logTable = $this->renameTableIfNeeded($logTable, 'request_logs');

        $this->renameColumnIfNeeded($requestTable, 'id', 'requests_id');
        $this->renameColumnIfNeeded($requestTable, 'request_type_id', 'request_types_id');
        $this->renameColumnIfNeeded($attachmentTable, 'id', 'attachments_id');
        $this->renameColumnIfNeeded($attachmentTable, 'it_support_request_id', 'requests_id');
        $this->renameColumnIfNeeded($logTable, 'id', 'logs_id');
        $this->renameColumnIfNeeded($logTable, 'it_support_request_id', 'requests_id');
        $this->renameColumnIfNeeded('request_types', 'id', 'request_types_id');

        $this->renamePrimaryKey('support_requests', 'support_requests_requests_id_primary');
        $this->renamePrimaryKey('request_attachments', 'request_attachments_attachments_id_primary');
        $this->renamePrimaryKey('request_logs', 'request_logs_logs_id_primary');
        $this->renamePrimaryKey('request_types', 'request_types_request_types_id_primary');

        $this->dropApprovalColumns();
        $this->createIndexesAndForeignKeys(true);
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_requests')
            || ! Schema::hasTable('request_attachments')
            || ! Schema::hasTable('request_logs')) {
            throw new RuntimeException('Cannot reverse the support-request schema rename: a renamed request table is missing.');
        }

        $this->dropForeignKeys('support_requests');
        $this->dropForeignKeys('request_attachments');
        $this->dropForeignKeys('request_logs');
        $this->dropIndexes('support_requests');
        $this->dropIndexes('request_attachments');
        $this->dropIndexes('request_logs');

        $this->renameColumnIfNeeded('support_requests', 'requests_id', 'id');
        $this->renameColumnIfNeeded('support_requests', 'request_types_id', 'request_type_id');
        $this->renameColumnIfNeeded('request_attachments', 'attachments_id', 'id');
        $this->renameColumnIfNeeded('request_attachments', 'requests_id', 'it_support_request_id');
        $this->renameColumnIfNeeded('request_logs', 'logs_id', 'id');
        $this->renameColumnIfNeeded('request_logs', 'requests_id', 'it_support_request_id');
        $this->renameColumnIfNeeded('request_types', 'request_types_id', 'id');

        $this->renamePrimaryKey('support_requests', 'it_support_requests_id_primary');
        $this->renamePrimaryKey('request_attachments', 'it_support_request_attachments_id_primary');
        $this->renamePrimaryKey('request_logs', 'it_support_request_logs_id_primary');
        $this->renamePrimaryKey('request_types', 'request_types_id_primary');

        $this->renameTableIfNeeded('support_requests', 'it_support_requests');
        $this->renameTableIfNeeded('request_attachments', 'it_support_request_attachments');
        $this->renameTableIfNeeded('request_logs', 'it_support_request_logs');

        $this->restoreApprovalColumns();
        $this->createIndexesAndForeignKeys(false);
        $this->restoreRemovedTables();
    }

    private function migrateProofAttachments(string $attachmentTable): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        if (! Schema::hasTable('it_support_request_comments')) {
            return;
        }

        if (! Schema::hasColumn('it_support_request_comments', 'proof_path')) {
            return;
        }

        if (! Schema::hasTable($attachmentTable)) {
            throw new RuntimeException('Cannot preserve comment proof photos: the request attachment table does not exist.');
        }

        $commentRequestColumn = Schema::hasColumn('it_support_request_comments', 'requests_id')
            ? 'requests_id'
            : 'it_support_request_id';
        $attachmentRequestColumn = Schema::hasColumn($attachmentTable, 'requests_id')
            ? 'requests_id'
            : 'it_support_request_id';
        $disk = Storage::disk('private');

        DB::table('it_support_request_comments')
            ->whereNotNull('proof_path')
            ->where('proof_path', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($comments) use ($attachmentTable, $attachmentRequestColumn, $commentRequestColumn, $disk): void {
                foreach ($comments as $comment) {
                    if ($comment->user_id === null
                        || ! DB::table('users')->where('user_id', $comment->user_id)->exists()) {
                        throw new RuntimeException(
                            "Cannot preserve proof photo for comment {$comment->id}: its uploader is missing or invalid."
                        );
                    }

                    $existing = DB::table($attachmentTable)
                        ->where($attachmentRequestColumn, $comment->{$commentRequestColumn})
                        ->where('file_path', $comment->proof_path)
                        ->first(['uploaded_by']);

                    if ($existing) {
                        if ((string) $existing->uploaded_by !== (string) $comment->user_id) {
                            throw new RuntimeException(
                                "Cannot preserve proof photo for comment {$comment->id}: an attachment with the same path has a different uploader."
                            );
                        }

                        continue;
                    }

                    if (! $disk->exists($comment->proof_path)) {
                        throw new RuntimeException(
                            "Cannot preserve proof photo for comment {$comment->id}: the file is missing from the private disk."
                        );
                    }

                    $mimeType = $disk->mimeType($comment->proof_path);
                    $size = $disk->size($comment->proof_path);
                    if (! is_string($mimeType) || $mimeType === '' || ! is_int($size)) {
                        throw new RuntimeException(
                            "Cannot preserve proof photo for comment {$comment->id}: file metadata could not be read."
                        );
                    }

                    $attachment = [
                        $attachmentRequestColumn => $comment->{$commentRequestColumn},
                        'uploaded_by' => $comment->user_id,
                        'file_path' => $comment->proof_path,
                        'original_name' => basename($comment->proof_path),
                        'mime_type' => $mimeType,
                        'size' => $size,
                        'created_at' => $comment->created_at ?? now(),
                        'updated_at' => $comment->updated_at ?? now(),
                    ];

                    DB::table($attachmentTable)->insert($attachment);
                }
            });
    }

    private function verifyAnswers(string $requestTable): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        if (! Schema::hasTable('it_support_request_details')) {
            return;
        }

        $requestKey = Schema::hasColumn($requestTable, 'requests_id') ? 'requests_id' : 'id';

        DB::table('it_support_request_details')
            ->select(['id', 'it_support_request_id', 'field_key', 'field_value'])
            ->orderBy('id')
            ->chunkById(500, function ($details) use ($requestKey, $requestTable): void {
                $requests = DB::table($requestTable)
                    ->whereIn(
                        $requestKey,
                        $details->pluck('it_support_request_id')->unique()->all(),
                    )
                    ->get([$requestKey, 'answers'])
                    ->keyBy($requestKey);

                foreach ($details as $detail) {
                    $request = $requests->get($detail->it_support_request_id);
                    if (! $request || $request->answers === null) {
                        throw new RuntimeException(
                            "Cannot drop request details: request {$detail->it_support_request_id} has no backfilled answers."
                        );
                    }

                    try {
                        $answers = is_string($request->answers)
                            ? json_decode($request->answers, true, 512, JSON_THROW_ON_ERROR)
                            : $request->answers;
                    } catch (JsonException $exception) {
                        throw new RuntimeException(
                            "Cannot drop request details: request {$detail->it_support_request_id} has invalid answers JSON.",
                            previous: $exception,
                        );
                    }

                    if (! is_array($answers)
                        || ! array_key_exists($detail->field_key, $answers)
                        || (string) $answers[$detail->field_key] !== (string) $detail->field_value) {
                        throw new RuntimeException(
                            "Cannot drop request details: request {$detail->it_support_request_id} field {$detail->field_key} is not represented in answers."
                        );
                    }
                }
            });
    }

    private function dropApprovalColumns(): void
    {
        foreach ([
            'support_requests' => ['requires_approval', 'approval_roles'],
            'request_types' => ['requires_approval', 'approval_roles'],
        ] as $tableName => $columns) {
            if (DB::connection()->pretending() && DB::connection()->getDriverName() === 'sqlite') {
                foreach ($columns as $column) {
                    DB::statement('ALTER TABLE "'.$tableName.'" DROP COLUMN "'.$column.'"');
                }

                continue;
            }

            if (DB::connection()->pretending()) {
                Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });

                continue;
            }

            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn($tableName, $column),
            ));

            if ($existing !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($existing): void {
                    $table->dropColumn($existing);
                });
            }
        }
    }

    private function restoreApprovalColumns(): void
    {
        foreach (['support_requests', 'request_types'] as $tableName) {
            $hasRequiresApproval = Schema::hasColumn($tableName, 'requires_approval');
            $hasApprovalRoles = Schema::hasColumn($tableName, 'approval_roles');
            if ($hasRequiresApproval && $hasApprovalRoles) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($hasRequiresApproval, $hasApprovalRoles): void {
                if (! $hasRequiresApproval) {
                    $table->boolean('requires_approval')->default(false);
                }
                if (! $hasApprovalRoles) {
                    $table->json('approval_roles')->nullable();
                }
            });
        }
    }

    private function restoreRemovedTables(): void
    {
        if (! Schema::hasTable('it_support_request_details')) {
            Schema::create('it_support_request_details', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('it_support_request_id')
                    ->constrained('it_support_requests')
                    ->cascadeOnDelete();
                $table->string('field_key', 100);
                $table->string('field_label');
                $table->text('field_value');
                $table->index('it_support_request_id', 'it_support_request_details_request_index');
            });
        }

        if (! Schema::hasTable('it_support_request_comments')) {
            Schema::create('it_support_request_comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('it_support_request_id')
                    ->constrained('it_support_requests')
                    ->noActionOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users', 'user_id')
                    ->noActionOnDelete();
                $table->string('author_name');
                $table->text('body');
                $table->string('proof_path')->nullable();
                $table->timestamps();
                $table->boolean('is_internal')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->index('it_support_request_id', 'it_support_request_comments_request_index');
                $table->index('user_id', 'it_support_request_comments_user_index');
            });
        }

        if (! Schema::hasTable('it_support_request_approvals')) {
            Schema::create('it_support_request_approvals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('it_support_request_id')
                    ->constrained('it_support_requests')
                    ->noActionOnDelete();
                $table->foreignId('approver_user_id')
                    ->nullable()
                    ->constrained('users', 'user_id')
                    ->noActionOnDelete();
                $table->string('approver_name');
                $table->string('approver_role');
                $table->string('decision');
                $table->text('comment')->nullable();
                $table->timestamp('decided_at');
                $table->timestamps();
                $table->unique('it_support_request_id');
                $table->index('approver_user_id', 'it_support_request_approvals_approver_index');
            });
        }
    }

    private function resolveAttachmentTable(): string
    {
        $tables = array_values(array_filter(
            ['it_support_request_attachments', 'it_support_request_attachmenets', 'request_attachments'],
            fn (string $table): bool => Schema::hasTable($table),
        ));

        if (count($tables) !== 1) {
            throw new RuntimeException(
                'Expected exactly one request attachment table (correct spelling, misspelled legacy spelling, or renamed spelling); found '.count($tables).'.'
            );
        }

        return $tables[0];
    }

    private function resolveTable(string $legacy, string $renamed): string
    {
        $tables = array_values(array_filter(
            [$legacy, $renamed],
            fn (string $table): bool => Schema::hasTable($table),
        ));

        if (count($tables) !== 1) {
            throw new RuntimeException(
                "Expected exactly one of {$legacy} or {$renamed}; found ".count($tables).'.'
            );
        }

        return $tables[0];
    }

    private function renameTableIfNeeded(string $current, string $renamed): string
    {
        if ($current === $renamed) {
            return $renamed;
        }

        if (DB::connection()->pretending()) {
            Schema::rename($current, $renamed);

            return $renamed;
        }

        if (! Schema::hasTable($current) || Schema::hasTable($renamed)) {
            throw new RuntimeException("Cannot safely rename {$current} to {$renamed}: source or destination table state is ambiguous.");
        }

        Schema::rename($current, $renamed);

        return $renamed;
    }

    private function renameColumnIfNeeded(string $tableName, string $legacy, string $renamed): void
    {
        if (DB::connection()->pretending()) {
            Schema::table($tableName, function (Blueprint $table) use ($legacy, $renamed): void {
                $table->renameColumn($legacy, $renamed);
            });

            return;
        }

        $hasLegacy = Schema::hasColumn($tableName, $legacy);
        $hasRenamed = Schema::hasColumn($tableName, $renamed);

        if ($hasRenamed && ! $hasLegacy) {
            return;
        }

        if (! $hasLegacy || $hasRenamed) {
            throw new RuntimeException("Cannot safely rename {$tableName}.{$legacy} to {$renamed}: column state is ambiguous.");
        }

        Schema::table($tableName, function (Blueprint $table) use ($legacy, $renamed): void {
            $table->renameColumn($legacy, $renamed);
        });
    }

    private function renamePrimaryKey(string $tableName, string $newName): void
    {
        if (DB::connection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        if (DB::connection()->pretending()) {
            $sourceTable = match ($tableName) {
                'support_requests' => 'it_support_requests',
                'request_attachments' => Schema::hasTable('it_support_request_attachmenets')
                    ? 'it_support_request_attachmenets'
                    : 'it_support_request_attachments',
                'request_logs' => 'it_support_request_logs',
                default => $tableName,
            };
            $primary = collect(DB::connection()->withoutPretending(
                fn () => Schema::getIndexes($sourceTable),
            ))->firstWhere('primary', true);
            if (! $primary) {
                throw new RuntimeException("Cannot find the primary key constraint for {$sourceTable}.");
            }

            $schema = DB::connection()->withoutPretending(
                fn () => DB::selectOne('SELECT SCHEMA_NAME(schema_id) AS schema_name FROM sys.tables WHERE name = ?', [$sourceTable])?->schema_name,
            );
            if (! is_string($schema) || $schema === '') {
                throw new RuntimeException("Cannot determine the SQL Server schema for {$tableName}.");
            }

            DB::statement("EXEC sp_rename ?, ?, 'INDEX'", [
                "{$schema}.{$tableName}.{$primary['name']}",
                $newName,
            ]);

            return;
        }

        $indexes = Schema::getIndexes($tableName);
        $primary = collect($indexes)->firstWhere('primary', true);
        if (! $primary || $primary['name'] === $newName) {
            return;
        }

        $schema = DB::selectOne(
            'SELECT SCHEMA_NAME(schema_id) AS schema_name FROM sys.tables WHERE name = ?',
            [$tableName],
        )?->schema_name;

        if (! is_string($schema) || $schema === '') {
            throw new RuntimeException("Cannot determine the SQL Server schema for {$tableName}.");
        }

        DB::statement("EXEC sp_rename ?, ?, 'INDEX'", [
            "{$schema}.{$tableName}.{$primary['name']}",
            $newName,
        ]);
    }

    private function dropForeignKeys(string $tableName): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (DB::connection()->pretending()) {
            $columns = match ($tableName) {
                'it_support_requests' => ['requester_user_id', 'assigned_technician_id', 'resolved_by_user_id', 'request_type_id'],
                'it_support_request_attachments' => ['it_support_request_id', 'uploaded_by'],
                'it_support_request_logs' => ['it_support_request_id', 'user_id'],
                default => [],
            };

            foreach ($columns as $column) {
                Schema::table($tableName, function (Blueprint $table) use ($column): void {
                    $table->dropForeign([$column]);
                });
            }

            return;
        }

        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
                if (is_string($foreignKey['name'] ?? null) && $foreignKey['name'] !== '') {
                    $table->dropForeign($foreignKey['name']);
                } else {
                    $table->dropForeign($foreignKey['columns']);
                }
            });
        }
    }

    private function dropIndexes(string $tableName): void
    {
        if (DB::connection()->pretending()) {
            $indexes = match ($tableName) {
                'it_support_requests' => [
                    ['it_support_requests_ticket_no_unique', true],
                    ['it_support_requests_status_index', false],
                    ['it_support_requests_priority_index', false],
                    ['it_support_requests_assigned_technician_index', false],
                    ['it_support_requests_requester_user_index', false],
                    ['it_support_requests_request_type_index', false],
                    ['it_support_requests_division_index', false],
                    ['it_support_requests_requested_at_index', false],
                    ['it_support_requests_resolved_by_index', false],
                ],
                'it_support_request_attachments' => [
                    ['it_support_request_attachments_request_index', false],
                    ['it_support_request_attachments_uploaded_by_index', false],
                ],
                'it_support_request_logs' => [
                    ['it_support_request_logs_request_index', false],
                    ['it_support_request_logs_user_index', false],
                ],
                default => [],
            };

            foreach ($indexes as [$name, $unique]) {
                Schema::table($tableName, function (Blueprint $table) use ($name, $unique): void {
                    if ($unique) {
                        $table->dropUnique($name);
                    } else {
                        $table->dropIndex($name);
                    }
                });
            }

            return;
        }

        foreach (Schema::getIndexes($tableName) as $index) {
            if ($index['primary']) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($index): void {
                if ($index['unique']) {
                    $table->dropUnique($index['name']);
                } else {
                    $table->dropIndex($index['name']);
                }
            });
        }
    }

    private function createIndexesAndForeignKeys(bool $renamed): void
    {
        if ($renamed) {
            $this->ensureIndex('support_requests', ['ticket_no'], 'support_requests_ticket_no_unique', true);
            foreach ([
                ['status', 'support_requests_status_index'],
                ['priority', 'support_requests_priority_index'],
                ['assigned_technician_id', 'support_requests_assigned_technician_index'],
                ['requester_user_id', 'support_requests_requester_user_index'],
                ['request_types_id', 'support_requests_request_types_index'],
                ['division', 'support_requests_division_index'],
                ['requested_at', 'support_requests_requested_at_index'],
                ['resolved_by_user_id', 'support_requests_resolved_by_index'],
            ] as [$column, $name]) {
                $this->ensureIndex('support_requests', [$column], $name);
            }

            foreach ([
                ['requests_id', 'request_attachments_requests_index'],
                ['uploaded_by', 'request_attachments_uploaded_by_index'],
            ] as [$column, $name]) {
                $this->ensureIndex('request_attachments', [$column], $name);
            }
            foreach ([
                ['requests_id', 'request_logs_requests_index'],
                ['user_id', 'request_logs_user_index'],
            ] as [$column, $name]) {
                $this->ensureIndex('request_logs', [$column], $name);
            }

                $this->ensureForeignKey('support_requests', 'requester_user_id', 'users', 'user_id', 'support_requests_requester_user_id_foreign');
                $this->ensureForeignKey('support_requests', 'assigned_technician_id', 'users', 'user_id', 'support_requests_assigned_technician_id_foreign');
                $this->ensureForeignKey('support_requests', 'resolved_by_user_id', 'users', 'user_id', 'support_requests_resolved_by_user_id_foreign');
                $this->ensureForeignKey('support_requests', 'request_types_id', 'request_types', 'request_types_id', 'support_requests_request_types_id_foreign');
                $this->ensureForeignKey('request_attachments', 'requests_id', 'support_requests', 'requests_id', 'request_attachments_requests_id_foreign');
                $this->ensureForeignKey('request_attachments', 'uploaded_by', 'users', 'user_id', 'request_attachments_uploaded_by_foreign');
                $this->ensureForeignKey('request_logs', 'requests_id', 'support_requests', 'requests_id', 'request_logs_requests_id_foreign');
                $this->ensureForeignKey('request_logs', 'user_id', 'users', 'user_id', 'request_logs_user_id_foreign');

            return;
        }

        $this->ensureIndex('it_support_requests', ['ticket_no'], 'it_support_requests_ticket_no_unique', true);
        foreach ([
            ['status', 'it_support_requests_status_index'],
            ['priority', 'it_support_requests_priority_index'],
            ['assigned_technician_id', 'it_support_requests_assigned_technician_index'],
            ['requester_user_id', 'it_support_requests_requester_user_index'],
            ['request_type_id', 'it_support_requests_request_type_index'],
            ['division', 'it_support_requests_division_index'],
            ['requested_at', 'it_support_requests_requested_at_index'],
            ['resolved_by_user_id', 'it_support_requests_resolved_by_index'],
        ] as [$column, $name]) {
            $this->ensureIndex('it_support_requests', [$column], $name);
        }
        foreach ([
            ['it_support_request_id', 'it_support_request_attachments_request_index'],
            ['uploaded_by', 'it_support_request_attachments_uploaded_by_index'],
        ] as [$column, $name]) {
            $this->ensureIndex('it_support_request_attachments', [$column], $name);
        }
        foreach ([
            ['it_support_request_id', 'it_support_request_logs_request_index'],
            ['user_id', 'it_support_request_logs_user_index'],
        ] as [$column, $name]) {
            $this->ensureIndex('it_support_request_logs', [$column], $name);
        }

        $this->ensureForeignKey('it_support_requests', 'requester_user_id', 'users', 'user_id', 'it_support_requests_requester_user_id_foreign');
        $this->ensureForeignKey('it_support_requests', 'assigned_technician_id', 'users', 'user_id', 'it_support_requests_assigned_technician_id_foreign');
        $this->ensureForeignKey('it_support_requests', 'resolved_by_user_id', 'users', 'user_id', 'it_support_requests_resolved_by_user_id_foreign');
        $this->ensureForeignKey('it_support_requests', 'request_type_id', 'request_types', 'id', 'it_support_requests_request_type_id_foreign');
        $this->ensureForeignKey('it_support_request_attachments', 'it_support_request_id', 'it_support_requests', 'id', 'it_support_request_attachments_it_support_request_id_foreign');
        $this->ensureForeignKey('it_support_request_attachments', 'uploaded_by', 'users', 'user_id', 'it_support_request_attachments_uploaded_by_foreign');
        $this->ensureForeignKey('it_support_request_logs', 'it_support_request_id', 'it_support_requests', 'id', 'it_support_request_logs_it_support_request_id_foreign');
        $this->ensureForeignKey('it_support_request_logs', 'user_id', 'users', 'user_id', 'it_support_request_logs_user_id_foreign');
    }

    private function ensureIndex(string $tableName, array $columns, string $name, bool $unique = false): void
    {
        if (! DB::connection()->pretending() && Schema::hasIndex($tableName, $name)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $name, $unique): void {
            if ($unique) {
                $table->unique($columns, $name);
            } else {
                $table->index($columns, $name);
            }
        });
    }

    private function ensureForeignKey(
        string $tableName,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $name,
    ): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $exists = ! DB::connection()->pretending()
            && collect(Schema::getForeignKeys($tableName))->contains(
                fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]
                    && $foreignKey['foreign_table'] === $referencedTable
                    && $foreignKey['foreign_columns'] === [$referencedColumn],
            );
        if ($exists) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable, $referencedColumn, $name): void {
            $foreign = $table->foreign($column, $name)
                ->references($referencedColumn)
                ->on($referencedTable);
            $foreign->noActionOnDelete();
        });
    }
};
