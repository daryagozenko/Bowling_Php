@extends('layouts.app')

@section('title', 'Дашборд')

@section('content')
    <div class="card">
        <h1>Привет, {{ $user->name }}!</h1>
        <p>Твоя роль: <strong>{{ $user->role }}</strong></p>

        @if ($user->role === 'admin')
            <p>Ты — администратор. Скоро здесь появятся управление дорожками и бронями.</p>
        @else
            <p>Ты — клиент. Скоро здесь появится твоя история броней.</p>
        @endif
    </div>
@endsection