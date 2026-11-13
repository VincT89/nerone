@props(['opensAt'])

@php($withTime = $opensAt->format('H:i') !== '00:00')
<time datetime="{{ $withTime ? $opensAt->toIso8601String() : $opensAt->toDateString() }}">{{ $opensAt->locale('it')->isoFormat($withTime ? 'D MMMM YYYY [alle] HH:mm' : 'D MMMM YYYY') }}</time>@if($withTime) (ora italiana)@endif
