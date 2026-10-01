<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class GalleryGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Gallery activity';

    protected ?string $description = 'New galleries created over the last six months';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $firstMonth = now()->startOfMonth()->subMonths(5);
        $labels = [];
        $counts = [];

        for ($month = $firstMonth->copy(); $month <= now()->startOfMonth(); $month->addMonth()) {
            $labels[] = $month->translatedFormat('M');
            $counts[] = $user->galleries()
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Galleries created',
                    'data' => $counts,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
