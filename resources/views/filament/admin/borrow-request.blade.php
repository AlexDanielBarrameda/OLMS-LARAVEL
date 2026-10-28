{{-- Borrow request details in the Approve modal. Expects $loan, $receiptUrl. --}}
@php
    $days = $loan->loanDays();
@endphp

<div class="bg-receipt">
    <dl class="bg-receipt-facts">
        <div>
            <dt>Duration</dt>
            <dd>{{ $days }} days</dd>
        </div>
        <div>
            <dt>Fee paid</dt>
            <dd class="bg-receipt-amount">{{ $loan->fee_amount !== null ? $loan->feeLabel() : '—' }}</dd>
        </div>
        @if ($loan->gcash_reference)
            <div>
                <dt>GCash ref</dt>
                <dd>{{ $loan->gcash_reference }}</dd>
            </div>
        @endif
        <div>
            <dt>Due if approved now</dt>
            <dd>{{ today()->addDays($days)->format('M j, Y') }}</dd>
        </div>
    </dl>

    @if ($receiptUrl)
        <a href="{{ $receiptUrl }}" target="_blank" rel="noopener" class="bg-receipt-image" title="Open full size">
            <img src="{{ $receiptUrl }}" alt="GCash receipt uploaded by {{ $loan->user->name }}">
        </a>
        <div class="bg-receipt-row">
            <p class="bg-receipt-hint">Screenshots can be edited: find this reference number and amount in the library's GCash history before approving.</p>
            <x-filament::button tag="a" :href="$receiptUrl" target="_blank" rel="noopener" color="gray" size="sm" icon="heroicon-m-arrow-top-right-on-square">
                View receipt
            </x-filament::button>
        </div>
    @else
        <p class="bg-receipt-hint">No fee was collected: this request was made before paid borrowing. It will be lent for {{ $days }} days.</p>
    @endif
</div>
