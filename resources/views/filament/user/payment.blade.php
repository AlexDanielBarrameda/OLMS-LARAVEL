{{--
    GCash payment block: amount due, steps, and a large scannable QR.
    Used by the overdue-fine and borrow-request modals. Expects $label, $amount, $breakdown, $steps (HTML-safe strings), $qrUrl.
--}}
<div class="bg-pay">
    <div class="bg-pay-amount-block @if ($tone ?? null) bg-pay-{{ $tone }} @endif">
        <p class="bg-pay-label">{{ $label }}</p>
        <p class="bg-pay-amount">{{ $amount }}</p>
        <p class="bg-pay-breakdown">{{ $breakdown }}</p>
    </div>

    <figure class="bg-pay-qr">
        <img src="{{ $qrUrl }}" alt="GCash QR code for paying Book Garden Library" width="720" height="1360" decoding="async">
        <figcaption class="bg-pay-qr-actions">
            <a href="{{ $qrUrl }}" target="_blank" rel="noopener">Open full size</a>
            <span aria-hidden="true">·</span>
            {{-- On a phone you can't scan your own screen: save it and pick it from GCash's "Upload QR" --}}
            <a href="{{ $qrUrl }}" download="book-garden-gcash-qr.png">Save QR image</a>
        </figcaption>
    </figure>

    <ol class="bg-pay-steps">
        @foreach ($steps as $i => $step)
            <li><span>{{ $i + 1 }}</span><p>{!! $step !!}</p></li>
        @endforeach
    </ol>
</div>
