@props(['user', 'size' => 'sm'])
@php
    $px = $size === 'md' ? 44 : 36;
    $isStudent = $user->role === \App\Enums\UserRole::Student;
@endphp
@if($isStudent)
    <img src="{{ \App\Support\Mascot::forStudent($user->student?->gender) }}" width="{{ $px }}" height="{{ $px }}" alt="" {{ $attributes->class(['avatar', 'avatar-'.$size]) }}>
@else
    <span aria-hidden="true" {{ $attributes->class(['avatar', 'avatar-initials', 'avatar-'.$size]) }}>{{ \App\Support\Mascot::initials($user->name) }}</span>
@endif
