@extends('layouts.app')

@section('title', 'Редактировать дорожку')

@section('content')
    <div class="card" style="max-width: 500px;">
        <h1>Редактировать дорожку №{{ $lane->number }}</h1>

        <form method="POST" action="{{ route('lanes.update', $lane) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="number">Номер</label>
                <input type="text" id="number" name="number" value="{{ old('number', $lane->number) }}" required>
                @error('number')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="status">Статус</label>
                <select id="status" name="status" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $lane->status) === $status)>
                            {{ $status }}
                        </option>
                    @endforeach
                </select>
                @error('status')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="price_per_hour">Цена за час (₽)</label>
                <input type="text" id="price_per_hour" name="price_per_hour" value="{{ old('price_per_hour', $lane->price_per_hour) }}" required>
                @error('price_per_hour')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn">Сохранить</button>
            <a href="{{ route('lanes.index') }}">Отмена</a>
        </form>
    </div>
@endsection