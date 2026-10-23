<?php
declare(strict_types=1);
/** Local SVG icons: no external fonts, CDN or script dependency. All paths are fixed. */
function portal_icon(string $name, int $size = 19): string {
    $shapes = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'budgets' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'requests' => '<path d="M4 7h16v12H4zM4 7l8 6 8-6M8 4h8"/>',
        'transactions' => '<path d="M4 7h16m-4-4 4 4-4 4M20 17H4m4-4-4 4 4 4"/>',
        'liquidations' => '<path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8l-5-5H8zM15 3v6h6M8 15l3 3 5-6"/>',
        'reports' => '<path d="M4 20V4m0 16h17M8 16v-5m5 5V6m5 10v-8"/>',
        'audit' => '<circle cx="11" cy="11" r="7"/><path d="m16 16 5 5M11 7v4l3 2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 15h3"/>',
        'notifications' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 11a3 3 0 1 0-1-5M17 14a5 5 0 0 1 4 5v1"/>',
        'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'logout' => '<path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="m5 5 14 14M19 5 5 19"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'arrow-up' => '<path d="M12 19V5m-6 6 6-6 6 6"/>',
        'arrow-down' => '<path d="M12 5v14m-6-6 6 6 6-6"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M17 15h1M6 6V4h11"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>',
        'trend' => '<path d="m3 17 6-6 4 4 8-8m-6 0h6v6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
    ];
    $size = min(28, max(12, $size));
    return '<svg class="icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="'.$size.'" height="'.$size.'" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($shapes[$name] ?? $shapes['dashboard']).'</svg>';
}
