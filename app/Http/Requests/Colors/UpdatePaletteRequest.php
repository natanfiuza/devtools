<?php

namespace App\Http\Requests\Colors;

/**
 * Atualizacao de paleta (F4, ADR-123).
 *
 * O payload e completo: a tela envia o que esta exibindo, sem PATCH parcial
 * de cores. A posse e checada no controller (404 para outra conta).
 */
final class UpdatePaletteRequest extends StorePaletteRequest {}
