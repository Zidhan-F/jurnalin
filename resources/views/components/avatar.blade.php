@props(['user', 'size' => 'sm'])
@php
    $px = match ($size) {
        'xl' => 96,
        'lg' => 72,
        'md' => 44,
        default => 36,
    };
    $isStudent = $user->role === \App\Enums\UserRole::Student;
@endphp
@if(! empty($user->avatar_path))
    <img src="/media/{{ $user->avatar_path }}" width="{{ $px }}" height="{{ $px }}" alt="" {{ $attributes->class(['avatar', 'avatar-photo', 'avatar-'.$size]) }}>
@elseif($isStudent)
    <img src="{{ \App\Support\Mascot::forStudent($user->student?->gender) }}" width="{{ $px }}" height="{{ $px }}" alt="" {{ $attributes->class(['avatar', 'avatar-'.$size]) }}>
@else
    <span aria-hidden="true" {{ $attributes->class(['avatar', 'avatar-initials', 'avatar-'.$size]) }}>{{ \App\Support\Mascot::initials($user->name) }}</span>
@endif
