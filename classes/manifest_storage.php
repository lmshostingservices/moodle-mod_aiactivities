<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Compression helper for the generated activity manifest.
 *
 * Activity data is stored as JSON in a single text column. Manifests that
 * include narration audio can be large, so anything past the threshold is
 * stored gzipped and base64 encoded behind a short prefix. Smaller manifests
 * are stored as plain JSON, which keeps existing rows readable.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities;

/**
 * Compresses and decompresses the stored activity manifest.
 */
class manifest_storage {
    /** @var int Manifests larger than this many bytes are stored compressed. */
    public const THRESHOLD = 524288;

    /** @var string Marks a stored value as compressed. */
    public const PREFIX = 'gz:';

    /**
     * Compresses a manifest when it is large enough to be worth it.
     *
     * @param string|null $data The JSON manifest.
     * @return string|null The value to store, compressed only when past the threshold.
     */
    public static function compress(?string $data): ?string {
        if ($data === null || $data === '') {
            return $data;
        }
        if (strlen($data) < self::THRESHOLD) {
            return $data;
        }

        $compressed = gzencode($data, 6);
        if ($compressed === false) {
            return $data;
        }

        return self::PREFIX . base64_encode($compressed);
    }

    /**
     * Returns the JSON manifest for a stored value, decompressing it when needed.
     *
     * @param string|null $data The stored value.
     * @return string|null The JSON manifest.
     */
    public static function decompress(?string $data): ?string {
        if ($data === null || $data === '') {
            return $data;
        }
        if (strncmp($data, self::PREFIX, 3) !== 0) {
            return $data;
        }

        $decoded = base64_decode(substr($data, 3), true);
        if ($decoded === false) {
            return $data;
        }

        $decompressed = gzdecode($decoded);
        if ($decompressed === false) {
            return $data;
        }

        return $decompressed;
    }
}
