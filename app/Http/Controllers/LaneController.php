<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLaneRequest;
use App\Http\Requests\UpdateLaneRequest;
use App\Models\Lane;
use App\Services\LaneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LaneController extends Controller
{
    public function __construct(private readonly LaneService $lanes)
    {
    }

    public function index(): View
    {
        return view('lanes.index', [
            'lanes' => $this->lanes->getAll(),
        ]);
    }

    public function create(): View
    {
        return view('lanes.create', [
            'statuses' => ['active', 'maintenance', 'broken'],
        ]);
    }

    public function store(StoreLaneRequest $request): RedirectResponse
    {
        $this->lanes->create($request->validated());

        return redirect()
            ->route('lanes.index')
            ->with('success', 'Дорожка создана.');
    }

    public function edit(Lane $lane): View
    {
        return view('lanes.edit', [
            'lane' => $lane,
            'statuses' => ['active', 'maintenance', 'broken'],
        ]);
    }

    public function update(UpdateLaneRequest $request, Lane $lane): RedirectResponse
    {
        $this->lanes->update($lane, $request->validated());

        return redirect()
            ->route('lanes.index')
            ->with('success', 'Дорожка обновлена.');
    }

    public function destroy(Lane $lane): RedirectResponse
    {
        try {
            $this->lanes->delete($lane);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('lanes.index')
                ->withErrors($e->errors())
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('lanes.index')
            ->with('success', 'Дорожка удалена.');
    }

    public function archive(Lane $lane): RedirectResponse
    {
        try {
            $this->lanes->archive($lane);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('lanes.index')
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('lanes.index')
            ->with('success', 'Дорожка переведена в архив.');
    }
}