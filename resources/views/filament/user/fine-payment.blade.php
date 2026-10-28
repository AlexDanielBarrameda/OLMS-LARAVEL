{{-- Overdue fine + GCash QR, shown above the receipt upload in the "Return Book" modal --}}
<div class="bg-fine">
    <div class="bg-fine-qr">
        <img src="{{ $qrUrl }}" alt="GCash QR code for paying library fines" width="720" height="1360" loading="eager">
    </div>

    <div class="bg-fine-info">
        <div class="bg-fine-amount-block">
            <p class="bg-fine-label">Amount due</p>
            <p class="bg-fine-amount">{{ $amount }}</p>
            <p class="bg-fine-breakdown">{{ $days }} {{ str('day')->plural($days) }} late × {{ $rate }} per day</p>
        </div>

        <ol class="bg-fine-steps">
            <li><span>1</span><p>Scan the QR code with GCash and pay <strong>{{ $amount }}</strong>.</p></li>
            <li><span>2</span><p>Upload a screenshot of your receipt below.</p></li>
            <li><span>3</span><p>Bring the book to the library desk. A librarian will verify your payment and check it in.</p></li>
        </ol>
    </div>
</div>
