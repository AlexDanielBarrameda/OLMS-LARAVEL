{{--
    Refund details. $mode "process": the student's original payment receipt (to see where to send the money back).
    $mode "view": the staff's refund screenshot, for audits. Expects $loan, $mode, $imageUrl.
--}}
<div class="bg-receipt">
    <dl class="bg-receipt-facts">
        <div>
            <dt>Refund</dt>
            <dd class="bg-receipt-amount">{{ \App\Models\Transaction::peso((float) $loan->fee_amount) }}</dd>
        </div>
        <div>
            <dt>Request</dt>
            <dd>{{ $loan->status->getLabel() }} · {{ $loan->duration_days }} days</dd>
        </div>
        <div>
            <dt>{{ $mode === 'view' ? 'Refunded' : 'Paid' }}</dt>
            <dd>{{ ($mode === 'view' ? $loan->refunded_at : $loan->created_at)?->format('M j, Y') }}</dd>
        </div>
    </dl>

    @if ($imageUrl)
        <a href="{{ $imageUrl }}" target="_blank" rel="noopener" class="bg-receipt-image" title="Open full size">
            <img src="{{ $imageUrl }}" alt="{{ $mode === 'view' ? 'Refund GCash screenshot' : 'Student\'s original GCash payment receipt' }}">
        </a>
        <div class="bg-receipt-row">
            <p class="bg-receipt-hint">
                {{ $mode === 'view' ? 'Proof of the refund sent by staff.' : 'The student\'s original payment. Send the refund to the same GCash account.' }}
            </p>
            <x-filament::button tag="a" :href="$imageUrl" target="_blank" rel="noopener" color="gray" size="sm" icon="heroicon-m-arrow-top-right-on-square">
                Open
            </x-filament::button>
        </div>
    @else
        <p class="bg-receipt-hint">No image on file.</p>
    @endif
</div>
