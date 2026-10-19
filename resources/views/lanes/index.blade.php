@extends('layouts.app')

@section('title', 'Дорожки')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h1>Дорожки</h1>
            <a href="{{ route('lanes.create') }}" class="btn">+ Добавить</a>
        </div>

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @if ($lanes->isEmpty())
            <p>Пока нет ни одной дорожки.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Статус</th>
                        <th>Цена/час</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lanes as $lane)
                        <tr>
                            <td>{{ $lane->number }}</td>
                            <td>
                                @if ($lane->status === 'active')
                                    <span style="color: #059669;">Активна</span>
                                @elseif ($lane->status === 'maintenance')
                                    <span style="color: #d97706;">На ТО</span>
                                @else
                                    <span style="color: #dc2626;">Сломана</span>
                                @endif
                            </td>
                            <td>{{ $lane->price_per_hour }} ₽</td>
                            <td>
                                <a href="{{ route('lanes.edit', $lane) }}" class="btn">Изменить</a>

                                @if ($lane->bookings()->exists())
                                    {{-- Есть брони — удалить нельзя, но можно в архив --}}
                                    @if ($lane->status !== 'broken')
                                        <form method="POST" action="{{ route('lanes.archive', $lane) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn" style="background: #d97706;"
                                                    onclick="return confirm('Перевести дорожку в архив? Она останется в БД, но её нельзя будет бронировать.');">
                                                В архив
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    {{-- Броней нет — можно удалить --}}
                                    <form method="POST" action="{{ route('lanes.destroy', $lane) }}" style="display: inline;"
                                        onsubmit="return confirm('Удалить дорожку {{ $lane->number }}? Это действие необратимо.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger">Удалить</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection