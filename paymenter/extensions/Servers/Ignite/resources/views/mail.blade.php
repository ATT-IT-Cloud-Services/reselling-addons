<div>
    @if (!empty($error))
        <div class="bg-background-secondary p-3 rounded-lg text-red-500 mb-4">{{ $error }}</div>
    @endif

    @php($mailData = $mail ?? [])
    <h3 class="text-lg font-semibold mb-2">{{ __('ignite::ignite.mailDomains') }}</h3>
    <ul class="text-sm mb-4">
        @forelse (($mailData['domains']['domains'] ?? $mailData['domains']['data'] ?? []) as $domain)
            <li class="border-t border-background-secondary py-1">{{ $domain['domain'] ?? $domain['name'] ?? '' }}</li>
        @empty
            <li class="py-2 text-gray-500">{{ __('ignite::ignite.empty') }}</li>
        @endforelse
    </ul>

    <h3 class="text-lg font-semibold mb-2">{{ __('ignite::ignite.mailboxes') }}</h3>
    <ul class="text-sm">
        @forelse (($mailData['mailboxes']['mailboxes'] ?? $mailData['mailboxes']['data'] ?? []) as $mailbox)
            <li class="border-t border-background-secondary py-1">{{ $mailbox['address'] ?? $mailbox['email'] ?? '' }}</li>
        @empty
            <li class="py-2 text-gray-500">{{ __('ignite::ignite.empty') }}</li>
        @endforelse
    </ul>
</div>
