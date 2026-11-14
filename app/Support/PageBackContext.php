<?php

namespace App\Support;

final class PageBackContext
{
    /**
     * @return array{pageBackUrl: ?string, pageBackLabel: string, pageBackFallback: string, pageBackShow: bool}
     */
    public static function forLayout(string $backUrlSection, string $backLabelSection): array
    {
        $pageBackUrl = trim($backUrlSection);
        $pageBackUrl = $pageBackUrl !== '' ? $pageBackUrl : null;
        $pageBackLabel = trim($backLabelSection);
        $pageBackLabel = $pageBackLabel !== '' ? $pageBackLabel : 'Back';

        if ($pageBackUrl === null) {
            $inferred = PageBackTarget::forRequest();
            $pageBackUrl = $inferred['url'];
            if ($pageBackLabel === 'Back' && ($inferred['label'] ?? '') !== '') {
                $pageBackLabel = $inferred['label'];
            }
        }

        $user = auth()->user();
        $pageBackFallback = $user !== null && $user->isManagement()
            ? route('admin.dashboard')
            : route('employee.dashboard');

        $routeName = request()->route()?->getName();

        return [
            'pageBackUrl' => $pageBackUrl,
            'pageBackLabel' => $pageBackLabel,
            'pageBackFallback' => $pageBackFallback,
            'pageBackShow' => $pageBackUrl !== null || ! PageBackTarget::isRootRoute(is_string($routeName) ? $routeName : null),
        ];
    }
}