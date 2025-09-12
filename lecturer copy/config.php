<?php

define('NOTIFICATIONS', 3);
define('NOTIFICATIONS_LIMIT', 10);

// Helper function to adjust color brightness
function adjustBrightness($hex, $factor) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = min(255, max(0, round($r * $factor)));
    $g = min(255, max(0, round($g * $factor)));
    $b = min(255, max(0, round($b * $factor)));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
?>
