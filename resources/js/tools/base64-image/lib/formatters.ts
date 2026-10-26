import type { ConversionItem, OutputFormat } from '../types';

/**
 * Escapa caracteres reservados para inclusao segura em nos XML.
 */
export function escapeXml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

/**
 * Extrai a carga util Base64 pura a partir de uma Data URI.
 */
export function extractRawBase64(dataUri: string): string {
    const match = dataUri.match(/^data:[^;]+;base64,(.+)$/s);
    return match && match[1] ? match[1].trim() : dataUri.trim();
}

/**
 * Formata o resultado da conversao como um objeto JSON estruturado (indentado a 2 espacos).
 */
export function formatJsonOutput(item: ConversionItem): string {
    const uri = item.data_uri || '';
    const raw = extractRawBase64(uri);

    const payload = {
        filename: item.filename,
        mime_type: item.mime_type,
        file_size_bytes: item.file_size_bytes,
        base64_size_bytes: item.base64_size_bytes,
        data_uri: uri,
        base64: raw,
    };

    return JSON.stringify(payload, null, 2);
}

/**
 * Formata o resultado da conversao como um documento XML valido.
 */
export function formatXmlOutput(item: ConversionItem): string {
    const uri = item.data_uri || '';
    const raw = extractRawBase64(uri);

    return [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<image>',
        `  <filename>${escapeXml(item.filename)}</filename>`,
        `  <mime_type>${escapeXml(item.mime_type)}</mime_type>`,
        `  <file_size_bytes>${item.file_size_bytes}</file_size_bytes>`,
        `  <base64_size_bytes>${item.base64_size_bytes}</base64_size_bytes>`,
        `  <data_uri>${escapeXml(uri)}</data_uri>`,
        `  <base64>${escapeXml(raw)}</base64>`,
        '</image>',
    ].join('\n');
}

/**
 * Formata o snippet de saida para a aba ativa.
 */
export function formatSnippet(
    format: OutputFormat,
    item: ConversionItem,
): string {
    const uri = item.data_uri || '';

    switch (format) {
        case 'img':
            return `<img src="${uri}" alt="${item.filename}" />`;
        case 'css':
            return `background-image: url("${uri}");`;
        case 'json':
            return formatJsonOutput(item);
        case 'xml':
            return formatXmlOutput(item);
        case 'data-uri':
        default:
            return uri;
    }
}

/**
 * Formata o texto de retenção do histórico em horas (<= 24h) ou dias (> 24h).
 */
export function formatRetentionTime(
    hours: number,
    t: (key: string, params?: Record<string, unknown>) => string,
): string {
    if (hours <= 24) {
        return t('tools.base64-image.retention_info', { hours });
    }

    const days = Math.round(hours / 24);
    if (days === 1) {
        return t('tools.base64-image.retention_info_day', { days });
    }

    return t('tools.base64-image.retention_info_days', { days });
}
