<div class="flex flex-col gap-2">
    @if (!empty($error))
        <div class="bg-background-secondary p-3 rounded-lg text-red-500">{{ $error }}</div>
    @endif

    <div class="bg-background-secondary p-4 rounded-lg">
        <h3 class="text-lg font-semibold mb-2">{{ __('ignite::ignite.overview') }}</h3>
        <ul class="text-sm">
            <li><strong>{{ __('ignite::ignite.name') }}:</strong> {{ $detail['name'] ?? '' }}</li>
            <li><strong>{{ __('ignite::ignite.status') }}:</strong> {{ $detail['status'] ?? '' }}</li>
            <li><strong>{{ __('ignite::ignite.hostname') }}:</strong> {{ $detail['hostname'] ?? '' }}</li>
            <li><strong>IPv4:</strong> {{ $detail['network']['primaryIpv4'] ?? '' }}</li>
            <li><strong>IPv6:</strong> {{ $detail['network']['primaryIpv6'] ?? '' }}</li>
            <li><strong>{{ __('ignite::ignite.node') }}:</strong> {{ $detail['nodeName'] ?? '' }}</li>
        </ul>
    </div>
</div>
