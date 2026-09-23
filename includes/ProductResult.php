<?php

declare(strict_types=1);

/**
 * Normalized product recognition result (English + 华文).
 */
final class ProductResult
{
    public string $objectLabel = '';
    public string $objectLabelZh = '';
    public string $productName = '';
    public string $productNameZh = '';
    public string $manufacturer = '';
    public string $specification = '';
    public string $specificationZh = '';
    public string $description = '';
    public string $descriptionZh = '';
    /** @var array{ymin:int,xmin:int,ymax:int,xmax:int} */
    public array $boundingBox = [
        'ymin' => 100,
        'xmin' => 100,
        'ymax' => 900,
        'xmax' => 900,
    ];
    public float $confidence = 0.0;
    public string $provider = '';

    /**
     * @param array<string,mixed> $raw
     */
    public static function fromArray(array $raw, string $provider = ''): self
    {
        $r = new self();
        $r->objectLabel = self::cleanText($raw['objectLabel'] ?? ($raw['object_label'] ?? ''));
        $r->objectLabelZh = self::cleanText($raw['objectLabelZh'] ?? ($raw['object_label_zh'] ?? ''));
        $r->productName = self::cleanText($raw['productName'] ?? ($raw['product_name'] ?? ''));
        $r->productNameZh = self::cleanText($raw['productNameZh'] ?? ($raw['product_name_zh'] ?? ''));
        $r->manufacturer = self::cleanText($raw['manufacturer'] ?? '');
        $r->specification = self::cleanText($raw['specification'] ?? '');
        $r->specificationZh = self::cleanText($raw['specificationZh'] ?? ($raw['specification_zh'] ?? ''));
        $r->description = self::cleanText($raw['description'] ?? '');
        $r->descriptionZh = self::cleanText($raw['descriptionZh'] ?? ($raw['description_zh'] ?? ''));
        $r->provider = $provider !== '' ? $provider : self::cleanText($raw['provider'] ?? '');

        $box = $raw['boundingBox'] ?? ($raw['bounding_box'] ?? []);
        if (!is_array($box)) {
            $box = [];
        }
        $r->boundingBox = [
            'ymin' => self::clampCoord($box['ymin'] ?? 100),
            'xmin' => self::clampCoord($box['xmin'] ?? 100),
            'ymax' => self::clampCoord($box['ymax'] ?? 900),
            'xmax' => self::clampCoord($box['xmax'] ?? 900),
        ];
        if ($r->boundingBox['ymax'] < $r->boundingBox['ymin']) {
            [$r->boundingBox['ymin'], $r->boundingBox['ymax']] = [$r->boundingBox['ymax'], $r->boundingBox['ymin']];
        }
        if ($r->boundingBox['xmax'] < $r->boundingBox['xmin']) {
            [$r->boundingBox['xmin'], $r->boundingBox['xmax']] = [$r->boundingBox['xmax'], $r->boundingBox['xmin']];
        }

        $conf = $raw['confidence'] ?? 0;
        if (is_string($conf) && str_ends_with($conf, '%')) {
            $conf = ((float) $conf) / 100;
        }
        $r->confidence = max(0.0, min(1.0, (float) $conf));

        // Ensure 华文 fields exist for UI + speech
        $r->fillMissingChinese();

        return $r;
    }

    public function fillMissingChinese(): void
    {
        $map = [
            'laptop' => '笔记本电脑',
            'phone' => '手机',
            'cell phone' => '手机',
            'mobile phone' => '手机',
            'bottle' => '瓶子',
            'keyboard' => '键盘',
            'mouse' => '滑鼠',
            'book' => '书本',
            'cup' => '杯子',
            'chair' => '椅子',
            'headphones' => '耳机',
            'headphone' => '耳机',
            'camera' => '相机',
            'remote' => '遥控器',
            'backpack' => '背包',
            'umbrella' => '雨伞',
            'clock' => '时钟',
            'tv' => '电视',
            'monitor' => '显示器',
            'person' => '人物',
        ];
        $key = strtolower($this->objectLabel);
        if ($this->objectLabelZh === '') {
            $this->objectLabelZh = $map[$key] ?? '';
        }
        // Only fill productNameZh if empty AND we have a Chinese category — do not fake a translation of English brand text
        if ($this->productNameZh === '' && $this->objectLabelZh !== '') {
            // If productName looks like plain English category, use Chinese category
            if ($this->productName === '' || strcasecmp($this->productName, $this->objectLabel) === 0) {
                $this->productNameZh = $this->objectLabelZh;
            }
        }
        // Do not auto-copy English description into Chinese — leave empty for UI honesty
    }

    public function isComplete(): bool
    {
        return $this->objectLabel !== '' && $this->productName !== '';
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'objectLabel' => $this->objectLabel,
            'objectLabelZh' => $this->objectLabelZh,
            'productName' => $this->productName,
            'productNameZh' => $this->productNameZh,
            'manufacturer' => $this->manufacturer,
            'specification' => $this->specification,
            'specificationZh' => $this->specificationZh,
            'description' => $this->description,
            'descriptionZh' => $this->descriptionZh,
            'boundingBox' => $this->boundingBox,
            'confidence' => $this->confidence,
            'provider' => $this->provider,
        ];
    }

    private static function cleanText(mixed $v): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        $s = trim(strip_tags((string) $v));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return mb_substr($s, 0, 800);
    }

    private static function clampCoord(mixed $v): int
    {
        $n = (int) round((float) $v);
        return max(0, min(1000, $n));
    }
}
