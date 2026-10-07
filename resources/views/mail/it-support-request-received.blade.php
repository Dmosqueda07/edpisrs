<p>Hello {{ $supportRequest->requester_name }},</p>

<p>Your IT support request has been received.</p>

<dl>
    <dt>Reference number</dt>
    <dd>#{{ $supportRequest->getKey() }}</dd>

    <dt>Type of support</dt>
    <dd>{{ $supportRequest->support_type }}</dd>

    <dt>Details</dt>
    <dd>{{ $supportRequest->details }}</dd>

    <dt>Submitted</dt>
    <dd>{{ $supportRequest->created_at->toDayDateTimeString() }}</dd>
</dl>

<p>For urgent concerns, coordinate directly with the EDP IT Support Team.</p>
