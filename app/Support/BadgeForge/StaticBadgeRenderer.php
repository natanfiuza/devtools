<?php

namespace App\Support\BadgeForge;

/**
 * Renderer do badge estatico.
 *
 * Documento unico, uma linha, sem declaracao XML: o mesmo template e
 * reproduzido byte a byte pelo espelho TypeScript (lib/render.ts). Qualquer
 * mudanca aqui exige regravar as fixtures de tests/Fixtures/badgeforge.
 */
final class StaticBadgeRenderer
{
    public function __construct(
        private readonly FontMetrics $metrics,
    ) {}

    public function render(BadgeSpec $spec): string
    {
        $style = $spec->style;
        $gap = $style->gap;
        $logoWidth = $spec->logo !== null ? $spec->logoSize + $gap : 0;

        // `??` ja tolera `$spec->font` nulo (mesma semantica de isset()).
        $fontFamily = $spec->font->stack ?? $style->fontFamily;
        $metricsFamily = $spec->font->slug ?? $style->metricsFamily;

        $labelAdvance = $this->metrics->width(
            $spec->label,
            $metricsFamily,
            $style->fontSize,
            $style->fontWeight,
            $style->letterSpacing,
        );

        $messageAdvance = $this->metrics->width(
            $spec->message,
            $metricsFamily,
            $style->fontSize,
            $style->fontWeight,
            $style->letterSpacing,
        );

        $labelWidth = $labelAdvance + 2 * $style->padding + ($spec->logoOnLabel() ? $logoWidth : 0);
        $messageWidth = $messageAdvance + 2 * $style->padding + ($spec->logoOnMessage() ? $logoWidth : 0);
        $width = $labelWidth + $messageWidth;
        $height = $style->height;

        $labelStart = $style->padding + ($spec->logoOnLabel() ? $logoWidth : 0);
        $labelEnd = $labelWidth - $style->padding;
        $labelX = intdiv($labelStart + $labelEnd, 2);

        $messageStart = $labelWidth + $style->padding;
        $messageEnd = $width - $style->padding - ($spec->logoOnMessage() ? $logoWidth : 0);
        $messageX = intdiv($messageStart + $messageEnd, 2);

        $logoX = $spec->logoPosition === 'right'
            ? $width - $style->padding - $spec->logoSize
            : $style->padding;
        $logoY = intdiv($height - $spec->logoSize, 2);

        $stops = $style->gradientStops();
        $accessibleLabel = Svg::escape($spec->label.': '.$spec->message);

        $defs = '';

        if ($stops !== []) {
            $defs .= '<linearGradient id="s" x2="0" y2="100%">';

            foreach ($stops as $stop) {
                $defs .= '<stop offset="'.$stop['offset'].'" stop-color="'.$stop['color'].'" stop-opacity="'.$stop['opacity'].'"/>';
            }

            $defs .= '</linearGradient>';
        }

        if ($style->radius > 0) {
            $defs .= '<clipPath id="r"><rect width="'.$width.'" height="'.$height.'" rx="'.$style->radius.'"/></clipPath>';
        }

        $defs = $defs !== '' ? '<defs>'.$defs.'</defs>' : '';

        $group = '<g'.($style->radius > 0 ? ' clip-path="url(#r)"' : '').'>';
        $group .= '<rect width="'.$labelWidth.'" height="'.$height.'" '.$spec->labelColor->fillAttributes().'/>';
        $group .= '<rect x="'.$labelWidth.'" width="'.$messageWidth.'" height="'.$height.'" '.$spec->messageColor->fillAttributes().'/>';

        if ($stops !== []) {
            $group .= '<rect width="'.$width.'" height="'.$height.'" fill="url(#s)"/>';
        }

        if ($style->bottomShadow !== null) {
            $shadowHeight = $style->bottomShadow['height'];
            $group .= '<rect y="'.($height - $shadowHeight).'" width="'.$width.'" height="'.$shadowHeight.'" fill="'.$style->bottomShadow['color'].'" fill-opacity="'.$style->bottomShadow['opacity'].'"/>';
        }

        if ($spec->logo !== null) {
            $group .= '<svg x="'.$logoX.'" y="'.$logoY.'" width="'.$spec->logoSize.'" height="'.$spec->logoSize.'" viewBox="'.Svg::escape($spec->logo->viewBox).'" aria-hidden="true" focusable="false">';

            foreach ($spec->logo->paths as $logoPath) {
                $group .= '<path d="'.Svg::escape($logoPath->d).'"';

                if ($logoPath->fillRule !== null) {
                    $group .= ' fill-rule="'.$logoPath->fillRule.'"';
                }

                if ($logoPath->clipRule !== null) {
                    $group .= ' clip-rule="'.$logoPath->clipRule.'"';
                }

                $group .= ' '.$spec->logoColor->fillAttributes().'/>';
            }

            $group .= '</svg>';
        }

        $group .= $this->text($labelX, $style->baseline, $style, $fontFamily, $spec->label);
        $group .= $this->text($messageX, $style->baseline, $style, $fontFamily, $spec->message);
        $group .= '</g>';

        $content = $defs.$group;

        if ($spec->link !== null) {
            $content = '<a href="'.Svg::escape($spec->link).'" target="_blank" rel="noopener noreferrer">'.$content.'</a>';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.$accessibleLabel.'">'
            .'<title>'.$accessibleLabel.'</title>'
            .$content
            .'</svg>';

        Svg::assertSafe($svg);

        return $svg;
    }

    private function text(int $x, int $y, Style $style, string $fontFamily, string $text): string
    {
        $attributes = 'x="'.$x.'" y="'.$y.'" '.$style->textColor->fillAttributes()
            .' font-family="'.Svg::escape($fontFamily).'"'
            .' font-size="'.$style->fontSize.'"'
            .' font-weight="'.$style->fontWeight.'"';

        if ($style->letterSpacing > 0) {
            $attributes .= ' letter-spacing="'.$style->letterSpacing.'"';
        }

        return '<text '.$attributes.' text-anchor="middle">'.Svg::escape($text).'</text>';
    }
}
