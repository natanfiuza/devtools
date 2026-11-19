<?php

namespace App\Enums;

/**
 * Estrategia de harmonia do Layout Harmonizer (ADR-146).
 *
 * Espelha `LAYOUT_STRATEGIES` do motor (`engine/types.ts`). O catalogo de 33
 * tokens nao muda entre estrategias; o que muda e a derivacao da secundaria.
 * O valor e persistido no snapshot e a paridade e coberta por teste.
 */
enum LayoutStrategy: string
{
    case Analogous = 'analogous';
    case Complementary = 'complementary';
    case Triadic = 'triadic';
    case Monochromatic = 'monochromatic';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $strategy): string => $strategy->value, self::cases());
    }

    public function labelKey(): string
    {
        return 'tools.layout-harmonizer.strategy.'.$this->value.'.name';
    }
}
