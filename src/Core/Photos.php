<?php

namespace Solar\Core;

/**
 * Curated Unsplash photography. Every ID below was fetched and visually
 * checked for subject matter (solar installation / rooftop arrays) — do not
 * add an ID without doing the same. Pools are indexed deterministically by
 * a record id so a given installer always shows the same photo.
 */
final class Photos
{
    private const BASE = 'https://images.unsplash.com/photo-';

    /** Installers on roofs / wiring panels / commercial arrays. */
    private const INSTALLER_COVERS = [
        '1624397640148-949b1732bb0a', // installer lifting a panel onto a tiled roof
        '1559302504-64aae6ca6b6d',    // gloved hands connecting panel wiring
        '1613665813446-82a78c468a1d', // rooftop array at sunset
        '1611365892117-00ac5ef43c90', // commercial rooftop array
    ];

    public static function url(string $photoId, int $width, ?int $height = null): string
    {
        $url = self::BASE . $photoId . '?auto=format&fit=crop&w=' . $width;
        if ($height !== null) {
            $url .= '&h=' . $height;
        }

        return $url . '&q=80';
    }

    public static function installerCover(int $installerId, int $width = 640): string
    {
        $pool = self::INSTALLER_COVERS;

        return self::url($pool[$installerId % count($pool)], $width, (int) round($width * 0.625));
    }
}
