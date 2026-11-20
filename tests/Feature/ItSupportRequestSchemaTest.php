<?php

use App\Models\RequestAttachment;
use App\Models\RequestLog;
use App\Models\RequestType;
use App\Models\SupportRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('uses the renamed request tables and primary keys and removes approval and comment tables', function () {
    expect((new SupportRequest)->getTable())->toBe('support_requests')
        ->and((new SupportRequest)->getKeyName())->toBe('requests_id')
        ->and((new RequestAttachment)->getTable())->toBe('request_attachments')
        ->and((new RequestAttachment)->getKeyName())->toBe('attachments_id')
        ->and((new RequestLog)->getTable())->toBe('request_logs')
        ->and((new RequestLog)->getKeyName())->toBe('logs_id')
        ->and((new RequestType)->getKeyName())->toBe('request_types_id')
        ->and(Schema::hasColumn('support_requests', 'answers'))->toBeTrue()
        ->and(Schema::hasColumn('support_requests', 'request_types_id'))->toBeTrue()
        ->and(Schema::hasColumn('request_attachments', 'requests_id'))->toBeTrue()
        ->and(Schema::hasColumn('request_logs', 'requests_id'))->toBeTrue()
        ->and(Schema::hasTable('it_support_request_details'))->toBeFalse()
        ->and(Schema::hasTable('it_support_request_approvals'))->toBeFalse()
        ->and(Schema::hasTable('it_support_request_comments'))->toBeFalse();
});

it('generates ticket numbers sequentially within each request year', function () {
    Carbon::setTestNow('2026-10-07 10:00:00');

    try {
        $first = SupportRequest::factory()->create();
        $second = SupportRequest::factory()->create();
        Carbon::setTestNow('2027-01-01 09:00:00');
        $nextYear = SupportRequest::factory()->create();
    } finally {
        Carbon::setTestNow();
    }

    expect($first->ticket_no)->toBe('EDP-2026-00001')
        ->and($second->ticket_no)->toBe('EDP-2026-00002')
        ->and($nextYear->ticket_no)->toBe('EDP-2027-00001');
});

it('casts merged answers to an array and relates attachments and logs by requests_id', function () {
    $request = SupportRequest::factory()->create([
        'answers' => ['details' => 'The computer will not start.'],
    ]);
    $attachment = RequestAttachment::factory()->create([
        'requests_id' => $request->getKey(),
    ]);
    $log = RequestLog::factory()->create([
        'requests_id' => $request->getKey(),
    ]);

    expect($request->fresh()->answers)->toBe(['details' => 'The computer will not start.'])
        ->and($attachment->supportRequest->getKey())->toBe($request->getKey())
        ->and($log->supportRequest->getKey())->toBe($request->getKey());
});

it('restricts deleting users referenced by requests and audit logs', function () {
    $requester = User::factory()->create();
    $request = SupportRequest::factory()
        ->for($requester, 'requester')
        ->create();
    $log = RequestLog::factory()->create([
        'requests_id' => $request->getKey(),
        'user_id' => $requester->getKey(),
    ]);

    expect(fn () => $requester->delete())->toThrow(QueryException::class)
        ->and(SupportRequest::query()->whereKey($request->getKey())->exists())->toBeTrue()
        ->and(RequestLog::query()->whereKey($log->getKey())->exists())->toBeTrue();
});

it('restricts deleting requests with retained attachments or audit logs', function () {
    $request = SupportRequest::factory()->create();
    $attachment = RequestAttachment::factory()->create([
        'requests_id' => $request->getKey(),
    ]);
    $log = RequestLog::factory()->create([
        'requests_id' => $request->getKey(),
    ]);

    expect(fn () => $request->delete())->toThrow(QueryException::class)
        ->and(SupportRequest::query()->whereKey($request->getKey())->exists())->toBeTrue()
        ->and(RequestAttachment::query()->whereKey($attachment->getKey())->exists())->toBeTrue()
        ->and(RequestLog::query()->whereKey($log->getKey())->exists())->toBeTrue();
});

it('restricts deleting a request type while requests reference it', function () {
    $request = SupportRequest::factory()->create();
    $requestType = $request->requestType;

    expect(fn () => $requestType->delete())->toThrow(QueryException::class)
        ->and($requestType->exists)->toBeTrue();
});

it('keeps request audit logs append-only through the model', function () {
    $log = RequestLog::factory()->create();

    expect(fn () => $log->update(['remarks' => 'changed']))->toThrow(\LogicException::class)
        ->and(fn () => $log->delete())->toThrow(\LogicException::class);
});
