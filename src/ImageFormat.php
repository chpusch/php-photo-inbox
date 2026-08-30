<?php

declare(strict_types=1);

namespace PhotoInbox;

/**
 * Die vom Postfach unterstuetzten Bildformate.
 *
 * Der Wert ist der MIME-Type, der aus dem Dateiinhalt ermittelt wird; die
 * Dateiendung wird ausschliesslich hier serverseitig vergeben und niemals aus
 * dem hochgeladenen Namen uebernommen.
 */
enum ImageFormat: string
{
    case Jpeg = 'image/jpeg';
    case Png  = 'image/png';
    case Gif  = 'image/gif';
    case Webp = 'image/webp';
    case Heic = 'image/heic';
    case Heif = 'image/heif';
    case Avif = 'image/avif';

    public function extension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Png  => 'png',
            self::Gif  => 'gif',
            self::Webp => 'webp',
            self::Heic => 'heic',
            self::Heif => 'heif',
            self::Avif => 'avif',
        };
    }

    public function label(): string
    {
        return strtoupper($this->extension());
    }

    /**
     * Konstante von getimagesize(), sofern PHP das Format kennt.
     * HEIC/HEIF/AVIF liefern hier null - sie werden ueber die Container-
     * Signatur geprueft, nicht ueber getimagesize().
     */
    public function imageTypeConstant(): ?int
    {
        return match ($this) {
            self::Jpeg => IMAGETYPE_JPEG,
            self::Png  => IMAGETYPE_PNG,
            self::Gif  => IMAGETYPE_GIF,
            self::Webp => IMAGETYPE_WEBP,
            default    => null,
        };
    }
}
