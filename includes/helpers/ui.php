<?php
declare(strict_types=1);

/** Version local assets so an update does not leave old CSS or JavaScript cached. */
function assetUrl(string $path): string
{
    return url($path) . "?v=" . filemtime(ROOT . "/" . $path);
}

/** Small, shared line icons. Labels stay visible in the surrounding HTML. */
function icon(string $name): string
{
    $paths = [
        "arrow" => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
        "external" => '<path d="M7 17 17 7M7 7h10v10"/>',
        "bag" => '<path d="M5 7h14l1 14H4L5 7Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        "pin" => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        "clock" => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        "grid" => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        "box" => '<path d="m12 3 9 5v9l-9 5-9-5V8l9-5Zm0 9 9-4M3 8l9 4v10M7 5.8l9 5v4"/>',
        "orders" => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/>',
        "people" => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v3"/>',
        "chart" => '<path d="M4 3v18h17M8 16v-5m5 5V7m5 9V4"/>',
        "link" => '<path d="m9 15 6-6M8 16l-1 1a4 4 0 1 1-5.7-5.7l4-4a4 4 0 0 1 5.7 0m2 1 1-1a4 4 0 1 1 5.7 5.7l-4 4a4 4 0 0 1-5.7 0"/>',
        "settings" => '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2" fill="currentColor"/><circle cx="15" cy="12" r="2" fill="currentColor"/><circle cx="8" cy="18" r="2" fill="currentColor"/>',
        "check" => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? $paths["arrow"]) . '</svg>';
}
