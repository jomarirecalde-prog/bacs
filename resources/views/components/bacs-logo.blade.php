@props(['class' => 'h-11 w-auto'])

<img src="{{ asset('images/bacs_logo_no_bg.png') }}" alt="BACS Construction" {{ $attributes->merge(['class' => $class]) }}>
