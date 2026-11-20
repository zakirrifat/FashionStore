@extends('shop.account.layout')

@section('title', 'Addresses')

@section('account')
    <div class="flex justify-between items-center mb-6">
        <h1 class="h2" data-split>Addresses</h1>
        <a href="{{ route('account.addresses.create') }}" class="label link-u">Add address</a>
    </div>

    @if ($addresses->isEmpty())
        <div class="border border-line p-8 text-center">
            <p class="text-mute mb-4">You haven’t saved any addresses yet.</p>
            <a href="{{ route('account.addresses.create') }}" class="btn-line max-w-xs mx-auto">Add address</a>
        </div>
    @else
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach ($addresses as $address)
                <div class="border {{ $address->is_default ? 'border-ink' : 'border-line' }} p-5 flex flex-col">
                    @if ($address->is_default)
                        <p class="label mb-3">Default</p>
                    @endif
                    <address class="not-italic flex-1">
                        @foreach ($address->lines() as $line)
                            <span class="block {{ $loop->first ? '' : 'text-mute' }}">{{ $line }}</span>
                        @endforeach
                    </address>
                    <div class="flex gap-4 mt-4 label">
                        <a href="{{ route('account.addresses.edit', $address) }}" class="link-u">Edit</a>
                        <form method="post" action="{{ route('account.addresses.destroy', $address) }}"
                              x-data @submit="if (! window.confirm('Remove this address?')) $event.preventDefault()">
                            @csrf @method('DELETE')
                            <button class="link-u text-mute">Remove</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
