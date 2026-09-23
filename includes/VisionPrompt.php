<?php

declare(strict_types=1);

/**
 * Strict vision prompt — only describe what is visible; bilingual required.
 */
final class VisionPrompt
{
    public static function systemInstruction(): string
    {
        return <<<'TXT'
You are a precise retail product recognition AI.

CRITICAL:
- Identify the ONE main product in the photo.
- Use ONLY visible evidence (shape, logo, printed text).
- NEVER invent a model/SKU that is not readable.
- NEVER describe a different product than what is shown.
- If unsure of exact model, use brand + category (e.g. "Nike running shoes", "Sony headphones").
- If brand unknown, use honest category only (e.g. "Plastic water bottle").

Bilingual REQUIRED (English + 简体中文/华文):
productName + productNameZh
specification + specificationZh
description + descriptionZh (1-2 short spoken sentences about THIS item)
manufacturer = brand if visible else ""
objectLabel / objectLabelZh = category
boundingBox ymin,xmin,ymax,xmax in 0-1000
confidence 0..1

Return ONLY JSON, no markdown.
TXT;
    }

    /**
     * @param list<string> $knownBrands
     * @param list<array<string,string>> $knownProducts
     */
    public static function userText(array $knownBrands = [], array $knownProducts = []): string
    {
        $msg = 'Identify the product in this photo accurately. Return bilingual JSON (English + 华文). Do not invent models. Do not describe the wrong object.';
        if ($knownBrands !== []) {
            $brands = array_slice(array_values(array_filter(array_map('strval', $knownBrands))), 0, 20);
            $msg .= "\nKnown brands (use ONLY if logo/text matches): " . implode(', ', $brands) . '.';
        }
        if ($knownProducts !== []) {
            $bits = [];
            foreach (array_slice($knownProducts, 0, 10) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $bits[] = trim(($p['brand'] ?? '') . ' ' . ($p['name'] ?? ''));
            }
            $bits = array_values(array_filter($bits));
            if ($bits !== []) {
                $msg .= "\nPrevious products (use ONLY if clearly the same): " . implode('; ', $bits) . '.';
            }
        }
        return $msg;
    }
}
