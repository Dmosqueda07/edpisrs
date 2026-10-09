<?php

use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestAttachment;
use App\Models\ItSupportRequestApproval;
use App\Models\ItSupportRequestDetail;
use App\Models\ItSupportRequestLog;
use App\Models\RequestType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates ticket numbers sequentially within each request year', function () {
    Carbon::setTestNow('2026-10-07 10:00:00');

    try {
        $first = ItSupportRequest::factory()->create();
        $second = ItSupportRequest::factory()->create();
        Carbon::setTestNow('2027-01-01 09:00:00');
        $nextYear = ItSupportRequest::factory()->create();
    } finally {
        Carbon::setTestNow();
    }

    expect($first->ticket_no)->toBe('EDP-2026-00001')
        ->and($second->ticket_no)->toBe('EDP-2026-00002')
        ->and($nextYear->ticket_no)->toBe('EDP-2027-00001');
});

it('restricts deleting users referenced by requests and audit logs', function () {
    $requester = User::factory()->create();
    $request = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create();
    $log = ItSupportRequestLog::factory()->create([
        'it_support_request_id' => $request->getKey(),
        'user_id' => $requester->getKey(),
    ]);

    expect(fn () => $requester->delete())->toThrow(QueryException::class)
        ->and(ItSupportRequest::query()->whereKey($request->getKey())->exists())->toBeTrue()
        ->and(ItSupportRequestLog::query()->whereKey($log->getKey())->exists())->toBeTrue();
});

it('cascades pure request child rows when a request is deleted', function () {
    $request = ItSupportRequest::factory()->create();
    $uploader = User::factory()->create();
    $detail = ItSupportRequestDetail::factory()->create([
        'it_support_request_id' => $request->getKey(),
    ]);
    $attachment = ItSupportRequestAttachment::factory()->create([
        'it_support_request_id' => $request->getKey(),
        'uploaded_by' => $uploader->getKey(),
    ]);

    $request->delete();

    expect(ItSupportRequestDetail::query()->whereKey($detail->getKey())->exists())->toBeFalse()
        ->and(ItSupportRequestAttachment::query()->whereKey($attachment->getKey())->exists())->toBeFalse();
});

it('restricts deleting a request type while requests reference it', function () {
    $request = ItSupportRequest::factory()->create();
    $requestType = $request->requestType;

    expect(fn () => $requestType->delete())->toThrow(QueryException::class)
        ->and($requestType->exists)->toBeTrue();
});

it('allows only one approval decision per request', function () {
    $request = ItSupportRequest::factory()->create();
    $firstApproval = ItSupportRequestApproval::factory()->create([
        'it_support_request_id' => $request->getKey(),
    ]);

    expect(fn () => ItSupportRequestApproval::factory()->create([
        'it_support_request_id' => $request->getKey(),
    ]))->toThrow(QueryException::class)
        ->and($firstApproval->exists)->toBeTrue();
});

it('keeps request audit logs append-only through the model', function () {
    $log = ItSupportRequestLog::factory()->create();

    expect(fn () => $log->update(['remarks' => 'changed']))->toThrow(\LogicException::class)
        ->and(fn () => $log->delete())->toThrow(\LogicException::class);
});
