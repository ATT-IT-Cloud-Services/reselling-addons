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

    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500"><th class="py-2">IP</th><th>{{ __('ignite::ignite.forward') }}</th><th>PTR</th><th></th></tr></thead>
        <tbody>
            @forelse ($rdns as $entry)
                <tr class="border-t border-background-secondary">
                    <td class="py-2">{{ $entry['ipAddress'] ?? '' }}</td>
                    <td>{{ $entry['forwardHostname'] ?? '' }}</td>
                    <td colspan="2">
                        <form method="post" action="{{ route('ignite.service.rdns') }}" class="flex gap-2 items-center">
                            @csrf
                            <input type="hidden" name="service_id" value="{{ $serviceId }}">
                            <input type="hidden" name="entry_id" value="{{ $entry['id'] ?? '' }}">
                            <input class="px-3 py-1 rounded-lg bg-background-secondary" type="text" name="reverse_content" value="{{ $entry['reverseContent'] ?? '' }}">
                            <button class="px-3 py-1 rounded-lg bg-background-secondary text-sm" type="submit">{{ __('ignite::ignite.save') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-3 text-gray-500">{{ __('ignite::ignite.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
