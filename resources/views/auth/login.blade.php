@extends('layouts.app')

@section('title', 'Вход')

@section('content')
    <div class="card" style="max-width: 400px; margin: 0 auto;">
        <h1>Вход</h1>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required>
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>


            <button type="submit" class="btn">Войти</button>
        </form>

        <p style="margin-top: 1rem;">
            Нет аккаунта? <a href="{{ route('register') }}">Зарегистрироваться</a>
        </p>
    </div>
@endsection