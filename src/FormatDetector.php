<?php

declare(strict_types=1);

namespace PhotoInbox;

use finfo;

/**
 * Ermittelt das Bildformat aus dem Dateiinhalt.
 *
 * Die vom Browser gemeldete Endung und der gemeldete MIME-Type sind
 * Nutzereingaben und werden bewusst ignoriert.
 */
final class FormatDetector
{
    /** ISO-BMFF-Marken, die einen HEIF/HEIC-Container kennzeichnen. */
    private const HEIF_BRANDS = [
        'heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'hevm', 'hevs', 'mif1', 'msf1',
    ];

    /** ISO-BMFF-Marken fuer AVIF. */
    private const AVIF_BRANDS = ['avif', 'avis'];

    public function detect(string $path): ?ImageFormat
    {
        $format = $this->detectByMagicBytes($path) ?? $this->detectByIsoContainer($path);

        if ($format === null) {
            return null;
        }

        return $this->verifyDimensions($path, $format) ? $format : null;
    }

    private function detectByMagicBytes(string $path): ?ImageFormat
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);

        if (!is_string($mime)) {
            return null;
        }

        return ImageFormat::tryFrom(strtolower($mime));
    }

    /**
     * Fallback fuer HEIC/HEIF/AVIF: aeltere libmagic-Versionen melden dafuer
     * nur "application/octet-stream". Wir lesen die ftyp-Box selbst.
     */
    private function detectByIsoContainer(string $path): ?ImageFormat
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $header = fread($handle, 64);
        fclose($handle);

        if (!is_string($header) || strlen($header) < 12 || substr($header, 4, 4) !== 'ftyp') {
            return null;
        }

        // Major brand plus die compatible brands, die auf die ftyp-Box folgen.
        $brands = str_split(substr($header, 8), 4);
        foreach ($brands as $brand) {
            $brand = strtolower($brand);
            if (in_array($brand, self::AVIF_BRANDS, true)) {
                return ImageFormat::Avif;
            }
            if (in_array($brand, self::HEIF_BRANDS, true)) {
                return ImageFormat::Heic;
            }
        }

        return null;
    }

    /**
     * Zweite Meinung fuer die Formate, die PHP selbst parsen kann: eine Datei,
     * die getimagesize() nicht als das erkannte Format liest, wird verworfen.
     */
    private function verifyDimensions(string $path, ImageFormat $format): bool
    {
        $expected = $format->imageTypeConstant();
        if ($expected === null) {
            return true;
        }

        $info = @getimagesize($path);

        return is_array($info)
            && ($info[2] ?? null) === $expected
            && (int) ($info[0] ?? 0) > 0
            && (int) ($info[1] ?? 0) > 0;
    }
}
