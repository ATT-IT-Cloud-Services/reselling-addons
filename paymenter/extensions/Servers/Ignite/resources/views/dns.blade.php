<div>
    @if (!empty($error))
        <div class="bg-background-secondary p-3 rounded-lg text-red-500 mb-4">{{ $error }}</div>
    @endif

    @php($flash = session('ignite_flash'))
    @if (!empty($flash))
        <div class="p-3 rounded-lg mb-4 {{ ($flash['type'] ?? '') === 'error' ? 'text-red-500' : 'text-green-500' }}">
            {{ $flash['message'] ?? '' }}
        </div>
    @endif

    @if (!empty($dnsDomainId))
        <p class="text-sm mb-3 text-gray-500">{{ $dnsDomain }}</p>

        <table class="w-full text-sm mb-4">
            <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('ignite::ignite.name') }}</th><th>{{ __('ignite::ignite.type') }}</th><th>{{ __('ignite::ignite.content') }}</th><th>TTL</th><th></th></tr></thead>
            <tbody>
                @forelse ($dns as $record)
                    <tr class="border-t border-background-secondary">
                        <td class="py-2">{{ $record['name'] ?? '' }}</td>
                        <td>{{ $record['type'] ?? '' }}</td>
                        <td>{{ $record['content'] ?? '' }}</td>
                        <td>{{ $record['ttl'] ?? '' }}</td>
                        <td>
                            <form method="post" action="{{ route('ignite.service.dns.delete') }}">
                                @csrf
                                <input type="hidden" name="service_id" value="{{ $serviceId }}">
                                <input type="hidden" name="record_id" value="{{ $record['id'] ?? '' }}">
                                <button class="text-red-500 text-sm" type="submit">{{ __('ignite::ignite.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 text-gray-500">{{ __('ignite::ignite.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        <form method="post" action="{{ route('ignite.service.dns.create') }}" class="flex flex-wrap gap-2">
            @csrf
            <input type="hidden" name="service_id" value="{{ $serviceId }}">
            <input class="px-3 py-1 rounded-lg bg-background-secondary" type="text" name="name" placeholder="{{ __('ignite::ignite.name') }}">
            <input class="px-3 py-1 rounded-lg bg-background-secondary" type="text" name="type" placeholder="A" size="4">
            <input class="px-3 py-1 rounded-lg bg-background-secondary" type="text" name="content" placeholder="{{ __('ignite::ignite.content') }}">
            <input class="px-3 py-1 rounded-lg bg-background-secondary" type="number" name="ttl" value="3600" size="5">
            <button class="px-3 py-1 rounded-lg bg-background-secondary text-sm" type="submit">{{ __('ignite::ignite.addRecord') }}</button>
        </form>
    @else
        <p class="text-gray-500">{{ __('ignite::ignite.empty') }}</p>
    @endif
</div>
