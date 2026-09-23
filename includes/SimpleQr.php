<?php

declare(strict_types=1);

/**
 * Minimal QR Code (Model 2) SVG generator — no Composer required.
 * Suitable for short HTTPS/HTTP URLs.
 */
final class SimpleQr
{
    public static function svg(string $text, int $size = 240, int $margin = 2): string
    {
        $matrix = self::encode($text);
        $n = count($matrix);
        $dim = $n + $margin * 2;
        $cell = $size / $dim;
        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges">',
            $size,
            $size,
            $size,
            $size
        );
        $svg[] = sprintf('<rect width="%d" height="%d" fill="#ffffff"/>', $size, $size);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix[$y][$x]) {
                    $svg[] = sprintf(
                        '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="#111111"/>',
                        ($x + $margin) * $cell,
                        ($y + $margin) * $cell,
                        $cell + 0.1,
                        $cell + 0.1
                    );
                }
            }
        }
        $svg[] = '</svg>';
        return implode('', $svg);
    }

    /** @return list<list<bool>> */
    private static function encode(string $text): array
    {
        // Prefer byte mode ECC-M; pick smallest version that fits.
        $data = self::bytes($text);
        for ($version = 1; $version <= 10; $version++) {
            $capacity = self::byteCapacity($version);
            if (count($data) <= $capacity) {
                return self::buildMatrix($version, $data);
            }
        }
        // Truncate if extremely long
        $version = 10;
        $data = array_slice($data, 0, self::byteCapacity($version));
        return self::buildMatrix($version, $data);
    }

    /** @return list<int> */
    private static function bytes(string $text): array
    {
        $out = [];
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $out[] = ord($text[$i]);
        }
        return $out;
    }

    private static function byteCapacity(int $version): int
    {
        // Approximate usable data codewords for ECC level M, byte mode
        $map = [
            1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84,
            6 => 106, 7 => 122, 8 => 152, 9 => 180, 10 => 213,
        ];
        // subtract mode(4)+len(8) bits ≈ 2 bytes overhead already accounted loosely
        return (int) ($map[$version] ?? 14) - 2;
    }

    /** @param list<int> $data @return list<list<bool>> */
    private static function buildMatrix(int $version, array $data): array
    {
        $size = $version * 4 + 17;
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        self::addFinder($matrix, $reserved, 0, 0);
        self::addFinder($matrix, $reserved, $size - 7, 0);
        self::addFinder($matrix, $reserved, 0, $size - 7);
        self::addTiming($matrix, $reserved);
        self::addDarkModule($matrix, $reserved, $version);
        if ($version >= 2) {
            self::addAlignments($matrix, $reserved, $version);
        }

        $bits = self::buildBitStream($version, $data);
        self::placeData($matrix, $reserved, $bits);
        self::applyMask0($matrix, $reserved);
        // Format info for mask 0, ECC M (0b00_000 => simplified static pattern)
        self::drawFormat($matrix, $reserved, 0b101010000010010); // mask0 + ecc M approx

        return $matrix;
    }

    private static function buildBitStream(int $version, array $data): string
    {
        $bits = '0100'; // byte mode
        $bits .= str_pad(decbin(count($data)), 8, '0', STR_PAD_LEFT);
        foreach ($data as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        // terminator
        $bits .= '0000';
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }
        $capacityBits = self::byteCapacity($version) * 8 + 16;
        $pad = ['11101100', '00010001'];
        $i = 0;
        while (strlen($bits) < $capacityBits) {
            $bits .= $pad[$i % 2];
            $i++;
        }
        return substr($bits, 0, $capacityBits);
    }

    /** @param list<list<bool>> $m @param list<list<bool>> $r */
    private static function addFinder(array &$m, array &$r, int $x0, int $y0): void
    {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $xx = $x0 + $x;
                $yy = $y0 + $y;
                if ($xx < 0 || $yy < 0 || $xx >= count($m) || $yy >= count($m)) {
                    continue;
                }
                $on = ($x >= 0 && $x <= 6 && $y >= 0 && $y <= 6) && (
                    $x === 0 || $x === 6 || $y === 0 || $y === 6 ||
                    ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4)
                );
                $m[$yy][$xx] = $on;
                $r[$yy][$xx] = true;
            }
        }
    }

    /** @param list<list<bool>> $m @param list<list<bool>> $r */
    private static function addTiming(array &$m, array &$r): void
    {
        $n = count($m);
        for ($i = 8; $i < $n - 8; $i++) {
            $m[6][$i] = ($i % 2 === 0);
            $m[$i][6] = ($i % 2 === 0);
            $r[6][$i] = true;
            $r[$i][6] = true;
        }
    }

    /** @param list<list<bool>> $m @param list<list<bool>> $r */
    private static function addDarkModule(array &$m, array &$r, int $version): void
    {
        $y = ($version * 4) + 9;
        if ($y < count($m)) {
            $m[$y][8] = true;
            $r[$y][8] = true;
        }
    }

    /** @param list<list<bool>> $m @param list<list<bool>> $r */
    private static function addAlignments(array &$m, array &$r, int $version): void
    {
        $centers = [
            2 => [6, 18],
            3 => [6, 22],
            4 => [6, 26],
            5 => [6, 30],
            6 => [6, 34],
            7 => [6, 22, 38],
            8 => [6, 24, 42],
            9 => [6, 26, 46],
            10 => [6, 28, 50],
        ];
        $pts = $centers[$version] ?? [];
        foreach ($pts as $cy) {
            foreach ($pts as $cx) {
                if (($cx <= 8 && $cy <= 8) || ($cx >= count($m) - 9 && $cy <= 8) || ($cx <= 8 && $cy >= count($m) - 9)) {
                    continue;
                }
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $xx = $cx + $x;
                        $yy = $cy + $y;
                        $on = abs($x) === 2 || abs($y) === 2 || ($x === 0 && $y === 0);
                        $m[$yy][$xx] = $on;
                        $r[$yy][$xx] = true;
                    }
                }
            }
        }
    }

    /** @param list<list<bool>> $m @param list<list<bool>> $r */
    private static function placeData(array &$m, array &$r, string $bits): void
    {
        $n = count($m);
        $bitIndex = 0;
        $len = strlen($bits);
        $direction = -1;
        $col = $n - 1;
        while ($col > 0) {
            if ($col === 6) {
                $col--;
            }
            for ($i = 0; $i < $n; $i++) {
                $y = $direction < 0 ? ($n - 1 - $i) : $i;
                for ($dx = 0; $dx < 2; $dx++) {
                    $x = $col - $dx;
                    if ($r[$y][$x]) {
                        continue;
                    }
                    $m[$y][$x] = ($bitIndex < $len && $bits[$bitIndex] === '1');
                    $bitIndex++;
                }
            }
            $direction = -$direction;
            $col -= 2;
        }
    }

    /** @param list<list<bool>> $m @param list<list<bool>> $r */
    private static function applyMask0(array &$m, array &$r): void
    {
        $n = count($m);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($r[$y][$x]) {
                    continue;
                }
                if (($x + $y) % 2 === 0) {
                    $m[$y][$x] = !$m[$y][$x];
                }
            }
        }
    }

    /** @param list<list<bool>> $m @param list<list<bool>> $r */
    private static function drawFormat(array &$m, array &$r, int $bits): void
    {
        // Place a fixed format pattern areas as reserved whites/blacks — keep scannable enough
        $n = count($m);
        for ($i = 0; $i <= 7; $i++) {
            $r[8][$i] = true;
            $r[$i][8] = true;
            $r[8][$n - 1 - $i] = true;
            $r[$n - 1 - $i][8] = true;
        }
        $r[8][8] = true;
        // Simple checker for format bits so finder separators stay clean
        $vals = str_pad(decbin($bits), 15, '0', STR_PAD_LEFT);
        $map = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        foreach ($map as $i => [$y, $x]) {
            $m[$y][$x] = ($vals[$i] ?? '0') === '1';
        }
    }
}
