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
        <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('ignite::ignite.created') }}</th><th>{{ __('ignite::ignite.size') }}</th><th>{{ __('ignite::ignite.status') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($backups as $backup)
                <tr class="border-t border-background-secondary">
                    <td class="py-2">{{ $backup['createdAt'] ?? $backup['created_at'] ?? '' }}</td>
                    <td>{{ $backup['size'] ?? $backup['sizeBytes'] ?? '' }}</td>
                    <td>{{ $backup['status'] ?? '' }}</td>
                    <td>
                        <div class="flex gap-3">
                            <form method="post" action="{{ route('ignite.service.backups.restore') }}">
                                @csrf
                                <input type="hidden" name="service_id" value="{{ $serviceId }}">
                                <input type="hidden" name="backup_id" value="{{ $backup['id'] ?? '' }}">
                                <button class="text-sm" type="submit" onclick="return confirm('{{ __('ignite::ignite.confirmRestore') }}')">{{ __('ignite::ignite.restore') }}</button>
                            </form>
                            <form method="post" action="{{ route('ignite.service.backups.delete') }}">
                                @csrf
                                <input type="hidden" name="service_id" value="{{ $serviceId }}">
                                <input type="hidden" name="backup_id" value="{{ $backup['id'] ?? '' }}">
                                <button class="text-red-500 text-sm" type="submit">{{ __('ignite::ignite.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-3 text-gray-500">{{ __('ignite::ignite.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="mt-3 text-sm text-gray-500">{{ __('ignite::ignite.createBackupHint') }}</p>
</div>
