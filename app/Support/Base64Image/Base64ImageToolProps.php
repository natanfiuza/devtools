<?php

namespace App\Support\Base64Image;

use App\Models\Plan;
use App\Models\User;

/**
 * Props para a ferramenta Base64 Image na pagina generica (ADR-043, ADR-055).
 */
final class Base64ImageToolProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?User $user): array
    {
        $plan = $user?->effectivePlan() ?? Plan::free();
        $isPaidPlan = ! $plan->isFree();

        $freeConfigLimit = (int) config('tools.limits.base64_image.free_max_bytes', 2 * 1024 * 1024);
        $paidConfigLimit = (int) config('tools.limits.base64_image.paid_max_bytes', 25 * 1024 * 1024);

        $maxUploadBytes = $isPaidPlan
            ? ($plan->max_image_upload_bytes > 0 ? $plan->max_image_upload_bytes : $paidConfigLimit)
            : ($plan->max_image_upload_bytes > 0 ? $plan->max_image_upload_bytes : $freeConfigLimit);

        $downloadThresholdBytes = (int) config('tools.limits.base64_image.download_threshold_bytes', 2 * 1024 * 1024);
        $freeHours = (int) config('tools.limits.base64_image.free_retention_hours', 24);
        $paidDays = (int) config('tools.limits.base64_image.paid_retention_days', 30);

        return [
            'isAuthenticated' => $user !== null,
            'isPaidPlan' => $isPaidPlan,
            'maxUploadBytes' => (int) $maxUploadBytes,
            'downloadThresholdBytes' => $downloadThresholdBytes,
            'retentionHours' => $isPaidPlan ? $paidDays * 24 : $freeHours,
        ];
    }
}
