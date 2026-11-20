<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOG_ACTION = 'legacy_status_mapped';

    private const LOG_PREFIX = 'migration:2026_11_03:';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            Schema::table('it_support_requests', function (Blueprint $table): void {
                $table->json('answers')->nullable();
            });

            return;
        }

        if (! Schema::hasTable('it_support_requests')) {
            throw new RuntimeException('Cannot backfill request answers: it_support_requests does not exist.');
        }

        if (! Schema::hasColumn('it_support_requests', 'answers')) {
            Schema::table('it_support_requests', function (Blueprint $table): void {
                $table->json('answers')->nullable();
            });
        }

        $definitions = collect(config('request_forms.request_types'))
            ->keyBy('key');
        $requestTypes = DB::table('request_types')->pluck('key', 'id');
        $hasLegacyDetails = Schema::hasColumn('it_support_requests', 'details');

        DB::table('it_support_requests')
            ->select(['id', 'request_type_id', 'answers', ...($hasLegacyDetails ? ['details'] : [])])
            ->orderBy('id')
            ->chunkById(250, function ($requests) use ($definitions, $requestTypes, $hasLegacyDetails): void {
                $requestIds = $requests->pluck('id')->all();
                $detailRows = Schema::hasTable('it_support_request_details')
                    ? DB::table('it_support_request_details')
                        ->whereIn('it_support_request_id', $requestIds)
                        ->orderBy('id')
                        ->get()
                        ->groupBy('it_support_request_id')
                    : collect();

                foreach ($requests as $request) {
                    if ($request->answers !== null) {
                        $this->validateAnswers(
                            $request->answers,
                            $definitions,
                            $requestTypes[$request->request_type_id] ?? null,
                            "request {$request->id}",
                        );

                        continue;
                    }

                    $typeKey = $requestTypes[$request->request_type_id] ?? null;
                    $definition = $definitions->get($typeKey);
                    if (! $definition) {
                        throw new RuntimeException(
                            "Cannot backfill request {$request->id}: request type {$request->request_type_id} has no request_forms.php definition."
                        );
                    }

                    $allowedKeys = collect($definition['fields'] ?? [])
                        ->pluck('key')
                        ->all();
                    if ($allowedKeys === []) {
                        throw new RuntimeException(
                            "Cannot backfill request {$request->id}: request type {$typeKey} defines no answer fields."
                        );
                    }

                    $answers = [];
                    foreach ($detailRows->get($request->id, collect()) as $detail) {
                        if (! in_array($detail->field_key, $allowedKeys, true)) {
                            throw new RuntimeException(
                                "Cannot backfill request {$request->id}: field key {$detail->field_key} is not defined for request type {$typeKey}."
                            );
                        }

                        if (array_key_exists($detail->field_key, $answers)) {
                            throw new RuntimeException(
                                "Cannot backfill request {$request->id}: duplicate answer rows exist for field key {$detail->field_key}."
                            );
                        }

                        $answers[$detail->field_key] = $detail->field_value;
                    }

                    $legacyDetails = $hasLegacyDetails ? $request->details : null;
                    if ($answers === [] && $legacyDetails !== null && $legacyDetails !== '') {
                        if (count($allowedKeys) !== 1) {
                            throw new RuntimeException(
                                "Cannot backfill request {$request->id}: legacy details cannot be assigned unambiguously to request type {$typeKey}."
                            );
                        }

                        $answers[$allowedKeys[0]] = $legacyDetails;
                    }

                    $this->validateAnswers(
                        $answers,
                        $definitions,
                        $typeKey,
                        "request {$request->id}",
                    );

                    DB::table('it_support_requests')
                        ->where('id', $request->id)
                        ->whereNull('answers')
                        ->update(['answers' => json_encode((object) $answers, JSON_THROW_ON_ERROR)]);
                }
            });

        $statusMappings = [
            'pending_approval' => 'submitted',
            'approved' => 'submitted',
            'rejected' => 'cancelled',
        ];

        if (! Schema::hasTable('it_support_request_logs')) {
            throw new RuntimeException('Cannot map legacy request statuses: it_support_request_logs does not exist.');
        }

        foreach ($statusMappings as $legacyStatus => $newStatus) {
            DB::table('it_support_requests')
                ->where('status', $legacyStatus)
                ->orderBy('id')
                ->chunkById(250, function ($requests) use ($legacyStatus, $newStatus): void {
                    foreach ($requests as $request) {
                        $remarks = self::LOG_PREFIX." status {$legacyStatus} mapped to {$newStatus}.";
                        $alreadyLogged = DB::table('it_support_request_logs')
                            ->where('it_support_request_id', $request->id)
                            ->where('action', self::LOG_ACTION)
                            ->where('remarks', $remarks)
                            ->exists();

                        DB::transaction(function () use ($request, $legacyStatus, $newStatus, $remarks, $alreadyLogged): void {
                            if (! $alreadyLogged) {
                                DB::table('it_support_request_logs')->insert([
                                    'it_support_request_id' => $request->id,
                                    'user_id' => null,
                                    'action' => self::LOG_ACTION,
                                    'from_status' => $legacyStatus,
                                    'to_status' => $newStatus,
                                    'remarks' => $remarks,
                                    'created_at' => now(),
                                ]);
                            }

                            DB::table('it_support_requests')
                                ->where('id', $request->id)
                                ->where('status', $legacyStatus)
                                ->update(['status' => $newStatus]);
                        });
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('it_support_requests') && Schema::hasTable('it_support_request_details')) {
            $definitions = collect(config('request_forms.request_types'))->keyBy('key');
            $requestTypes = DB::table('request_types')->pluck('key', 'id');

            DB::table('it_support_requests')
                ->select(['id', 'request_type_id', 'answers'])
                ->whereNotNull('answers')
                ->orderBy('id')
                ->chunkById(250, function ($requests) use ($definitions, $requestTypes): void {
                    foreach ($requests as $request) {
                        $typeKey = $requestTypes[$request->request_type_id] ?? null;
                        $definition = $definitions->get($typeKey);
                        if (! $definition) {
                            throw new RuntimeException(
                                "Cannot restore request details for request {$request->id}: request type {$request->request_type_id} has no request_forms.php definition."
                            );
                        }

                        $answers = $this->validateAnswers(
                            $request->answers,
                            $definitions,
                            $typeKey,
                            "request {$request->id}",
                        );
                        $fields = collect($definition['fields'])->keyBy('key');

                        foreach ($answers as $fieldKey => $fieldValue) {
                            $exists = DB::table('it_support_request_details')
                                ->where('it_support_request_id', $request->id)
                                ->where('field_key', $fieldKey)
                                ->exists();
                            if ($exists) {
                                continue;
                            }

                            DB::table('it_support_request_details')->insert([
                                'it_support_request_id' => $request->id,
                                'field_key' => $fieldKey,
                                'field_label' => $fields[$fieldKey]['label'],
                                'field_value' => (string) $fieldValue,
                            ]);
                        }
                    }
                });
        }

        if (Schema::hasTable('it_support_requests') && Schema::hasTable('it_support_request_logs')) {
            DB::table('it_support_request_logs')
                ->where('action', self::LOG_ACTION)
                ->where('remarks', 'like', self::LOG_PREFIX.'%')
                ->orderBy('id')
                ->chunkById(250, function ($logs): void {
                    foreach ($logs as $log) {
                        DB::transaction(function () use ($log): void {
                            DB::table('it_support_requests')
                                ->where('id', $log->it_support_request_id)
                                ->where('status', $log->to_status)
                                ->update(['status' => $log->from_status]);

                            DB::table('it_support_request_logs')
                                ->where('id', $log->id)
                                ->delete();
                        });
                    }
                });
        }

        if (Schema::hasColumn('it_support_requests', 'answers')) {
            Schema::table('it_support_requests', function (Blueprint $table): void {
                $table->dropColumn('answers');
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAnswers(
        mixed $answers,
        \Illuminate\Support\Collection $definitions,
        ?string $typeKey,
        string $context,
    ): array {
        if (is_string($answers)) {
            try {
                $answers = json_decode($answers, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new RuntimeException("Cannot validate answers for {$context}: invalid JSON.", previous: $exception);
            }
        }

        if (! is_array($answers)) {
            throw new RuntimeException("Cannot validate answers for {$context}: answers must be a JSON object.");
        }

        $definition = $definitions->get($typeKey);
        $allowedKeys = collect($definition['fields'] ?? [])->pluck('key')->all();
        foreach (array_keys($answers) as $fieldKey) {
            if (! is_string($fieldKey) || ! in_array($fieldKey, $allowedKeys, true)) {
                throw new RuntimeException(
                    "Cannot validate answers for {$context}: field key ".(string) $fieldKey.' is not defined for request type '.(string) $typeKey.'.'
                );
            }
        }

        return $answers;
    }
};
