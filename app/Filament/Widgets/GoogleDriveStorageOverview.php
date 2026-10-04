<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Google\GoogleDriveService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class GoogleDriveStorageOverview extends Widget
{
    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.google-drive-storage-overview';

    protected function getViewData(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ['storage' => null];
        }

        $quota = app(GoogleDriveService::class)->getStorageQuota($user);

        if (! $quota) {
            return ['storage' => null];
        }

        $usage = max(0, (int) $quota->getUsage());
        $driveUsage = min(max(0, (int) $quota->getUsageInDrive()), $usage);
        $limit = max(0, (int) $quota->getLimit());
        $boundedUsage = $limit > 0 ? min($usage, $limit) : $usage;
        $boundedDriveUsage = $limit > 0 ? min($driveUsage, $limit) : $driveUsage;
        $otherUsage = max(0, $boundedUsage - $boundedDriveUsage);
        $available = $limit > 0 ? max(0, $limit - $boundedUsage) : null;
        $usedPercent = $limit > 0 ? min(100, round(($boundedUsage / $limit) * 100, 1)) : null;
        $drivePercent = $boundedUsage > 0
            ? round(($boundedDriveUsage / $boundedUsage) * 100, 1)
            : 0;
        $driveAllocationPercent = $limit > 0
            ? round(($boundedDriveUsage / $limit) * 100, 2)
            : $drivePercent;
        $otherAllocationPercent = $usedPercent === null
            ? max(0, round(100 - $driveAllocationPercent, 2))
            : max(0, round($usedPercent - $driveAllocationPercent, 2));

        return [
            'storage' => [
                'drive' => $this->formatBytes($driveUsage),
                'other' => $this->formatBytes($otherUsage),
                'total' => $this->formatBytes($boundedUsage),
                'available' => $available === null ? 'Unlimited' : $this->formatBytes($available),
                'limit' => $limit > 0 ? $this->formatBytes($limit) : 'Unlimited',
                'usedPercent' => $usedPercent,
                'driveAllocationPercent' => $driveAllocationPercent,
                'otherAllocationPercent' => $otherAllocationPercent,
                'allocationPercent' => $usedPercent ?? 0,
                'availablePercent' => $usedPercent === null ? 0 : max(0, round(100 - $usedPercent, 2)),
            ],
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return number_format($bytes / (1024 ** 3), 1).' GB';
        }

        return number_format($bytes / (1024 ** 2), 1).' MB';
    }
}
