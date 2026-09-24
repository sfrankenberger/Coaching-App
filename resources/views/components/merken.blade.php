@props(['art', 'id', 'an' => false, 'text' => false])
<form method="post" action="{{ route('merken') }}" data-merken {{ $attributes->merge(['class' => 'inline']) }}>
    @csrf
    <input type="hidden" name="type" value="{{ $art }}"><input type="hidden" name="id" value="{{ $id }}">
    @if ($text)
        <button type="submit" class="knopf knopf-leise {{ $an ? 'text-primary border-primary' : '' }}" style="min-height:36px;padding:6px 12px" data-an="{{ $an ? 1 : 0 }}">
            <svg viewBox="0 0 24 24" fill="{{ $an ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" class="size-4 inline -mt-0.5"><path d="M6 3h12v18l-6-4-6 4z"/></svg>
            <span data-merken-text>{{ $an ? 'Gemerkt' : 'Merken' }}</span>
        </button>
    @else
        <button type="submit" class="size-9 grid place-items-center rounded-full border border-line {{ $an ? 'text-primary border-primary' : 'text-muted' }}" aria-label="Merken" title="Merken" data-an="{{ $an ? 1 : 0 }}">
            <svg viewBox="0 0 24 24" fill="{{ $an ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" class="size-4"><path d="M6 3h12v18l-6-4-6 4z"/></svg>
        </button>
    @endif
</form>
