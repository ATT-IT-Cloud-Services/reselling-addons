<div>
    @if (!empty($error))
        <div class="bg-background-secondary p-3 rounded-lg text-red-500 mb-4">{{ $error }}</div>
    @endif

    @php($pleskData = $plesk ?? [])
    <h3 class="text-lg font-semibold mb-2">Plesk</h3>
    <ul class="text-sm mb-4">
        <li><strong>{{ __('ignite::ignite.status') }}:</strong> {{ $pleskData['setup']['status'] ?? '' }}</li>
        <li><strong>{{ __('ignite::ignite.hostname') }}:</strong> {{ $pleskData['setup']['hostname'] ?? '' }}</li>
    </ul>

    <h3 class="text-lg font-semibold mb-2">{{ __('ignite::ignite.domains') }}</h3>
    <ul class="text-sm">
        @forelse (($pleskData['domains']['domains'] ?? $pleskData['domains']['data'] ?? []) as $domain)
            <li class="border-t border-background-secondary py-1">{{ $domain['name'] ?? $domain['domain'] ?? '' }}</li>
        @empty
            <li class="py-2 text-gray-500">{{ __('ignite::ignite.empty') }}</li>
        @endforelse
    </ul>
</div>
