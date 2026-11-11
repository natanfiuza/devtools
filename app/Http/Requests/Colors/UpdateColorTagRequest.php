<?php

namespace App\Http\Requests\Colors;

/**
 * Renomeacao de tag da conta (F4, ADR-103).
 *
 * O nome novo alcanca todas as paletas vinculadas; a posse e checada no
 * controller (404 para tag de outra conta).
 */
final class UpdateColorTagRequest extends StoreColorTagRequest {}
