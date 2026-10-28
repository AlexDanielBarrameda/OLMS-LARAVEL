{{-- Receipt + fine summary in the "Verify Payment & Return" modal. Expects $loan, $receiptUrl. --}}
<div class="bg-receipt">
    <dl class="bg-receipt-facts">
        <div>
            <dt>Fine</dt>
            <dd class="bg-receipt-amount">{{ \App\Models\Transaction::peso($loan->fineDue()) }}</dd>
        </div>
        <div>
            <dt>Due date</dt>
            <dd>{{ \Illuminate\Support\Carbon::parse($loan->due_date)->format('M j, Y') }}</dd>
        </div>
        <div>
            <dt>Submitted</dt>
            <dd>{{ $loan->updated_at->format('M j, g:i A') }}</dd>
        </div>
    </dl>

    @if ($receiptUrl)
        <a href="{{ $receiptUrl }}" target="_blank" rel="noopener" class="bg-receipt-image" title="Open full size">
            <img src="{{ $receiptUrl }}" alt="GCash receipt uploaded by {{ $loan->user->name }}">
        </a>
        <p class="bg-receipt-hint">Check the amount, the date and the reference number. Click the image to open it full size.</p>
    @else
        <p class="bg-receipt-hint">No receipt file was found for this payment.</p>
    @endif
</div>
