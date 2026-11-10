import { serve } from '@/lib/worker/serve';
import {
    extractPalette,
    type ExtractionOutcome,
    type ExtractionRequest,
} from './extract';

/**
 * O worker da extracao (ADR-122).
 *
 * `color-thief` so e importado por este arquivo e pelo caminho de thread
 * principal: o chunk da pagina nunca carrega a quantizacao. O payload chega
 * com o `ArrayBuffer` transferido, sem copia.
 */
serve<ExtractionRequest, ExtractionOutcome>((request) => extractPalette(request));
