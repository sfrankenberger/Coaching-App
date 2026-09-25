@props(['art', 'id', 'an' => false, 'text' => false])
<form method="post" action="{{ route('merken') }}" data-merken {{ $attributes->merge(['class' => 'inline']) }}>
    @csrf
    <input type="hidden" name="type" value="{{ $art }}"><input type="hidden" name="id" value="{{ $id }}">
    @if ($text)
        <button type="submit" @class(['knopf knopf-leise knopf-klein', 'text-primary border-primary' => $an]) data-an="{{ $an ? 1 : 0 }}" aria-pressed="{{ $an ? 'true' : 'false' }}">
            <i class="fa-{{ $an ? 'solid' : 'regular' }} fa-bookmark"></i>
            <span data-merken-text>{{ $an ? 'Gemerkt' : 'Merken' }}</span>
        </button>
    @else
        <button type="submit" @class(['merken', 'an' => $an]) aria-label="Merken" title="Merken" data-an="{{ $an ? 1 : 0 }}" aria-pressed="{{ $an ? 'true' : 'false' }}">
            <i class="fa-{{ $an ? 'solid' : 'regular' }} fa-bookmark"></i>
        </button>
    @endif
</form>
