<?php

namespace App\Http\Requests\Colors;

/**
 * Atualizacao de sistema de layout (F4, ADR-168).
 *
 * O payload e completo: a tela envia o estado que esta exibindo, sem PATCH
 * parcial de tokens. A posse e checada no controller (404 para outra conta).
 */
final class UpdateLayoutSystemRequest extends StoreLayoutSystemRequest {}
