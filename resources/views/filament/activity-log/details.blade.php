{{-- Before/after table for one activity-log entry --}}
<div class="bg-activity">
    <p class="bg-activity-meta">
        {{ $activity->causer?->name ?? 'System' }} · {{ $activity->created_at->format('M j, Y g:i A') }}
    </p>

    @if (count($changes))
        <table class="bg-activity-table">
            <thead>
                <tr>
                    <th>Field</th>
                    @if ($activity->event !== 'created')
                        <th>Before</th>
                    @endif
                    @if ($activity->event !== 'deleted')
                        <th>After</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($changes as $change)
                    <tr>
                        <td class="bg-activity-field">{{ $change['field'] }}</td>
                        @if ($activity->event !== 'created')
                            <td class="bg-activity-old">{{ $change['old'] ?? '—' }}</td>
                        @endif
                        @if ($activity->event !== 'deleted')
                            <td>{{ $change['new'] ?? '—' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="bg-activity-meta">No field values were recorded.</p>
    @endif
</div>
