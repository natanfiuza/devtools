import { describe, it, expect, beforeEach } from 'vitest';
import { parseBase64Image } from '../tools/base64-image/lib/decoder';
import {
    getAnonymousHistory,
    saveAnonymousConversion,
    removeAnonymousConversion,
} from '../tools/base64-image/lib/storage';
import {
    escapeXml,
    extractRawBase64,
    formatJsonOutput,
    formatXmlOutput,
    formatSnippet,
} from '../tools/base64-image/lib/formatters';
import type { ConversionItem } from '../tools/base64-image/types';

describe('Base64 Image Decoder & Storage', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    describe('parseBase64Image', () => {
        it('falha com entrada vazia', () => {
            const res = parseBase64Image('');
            expect(res.valid).toBe(false);
            expect(res.error).toBe('empty_input');
        });

        it('decodifica data URI valida de PNG', () => {
            const samplePng =
                'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
            const res = parseBase64Image(samplePng);

            expect(res.valid).toBe(true);
            expect(res.mimeType).toBe('image/png');
            expect(res.extension).toBe('png');
            expect(res.sizeBytes).toBeGreaterThan(0);
        });

        it('infere MIME type jpeg a partir de magic bytes /9j/', () => {
            // Pequeno base64 comecando com /9j/
            const rawJpeg = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';
            const res = parseBase64Image(rawJpeg);

            expect(res.valid).toBe(true);
            expect(res.mimeType).toBe('image/jpeg');
            expect(res.extension).toBe('jpg');
        });

        it('retorna erro em string base64 corrompida', () => {
            const corrupted = 'data:image/png;base64,###INVALID_BASE64###';
            const res = parseBase64Image(corrupted);

            expect(res.valid).toBe(false);
            expect(res.error).toBe('invalid_base64');
        });
    });

    describe('localStorage de Anonimos', () => {
        it('salva, recupera e remove registros no localStorage', () => {
            expect(getAnonymousHistory()).toEqual([]);

            const record1 = {
                id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                filename: 'photo1.png',
                mime_type: 'image/png',
                file_size_bytes: 1024,
                base64_size_bytes: 1400,
                created_at: new Date().toISOString(),
                download_url: '/tools/base64-image/download/01ARZ3NDEKTSV4RRFFQ69G5FAV',
                preview_url: '/tools/base64-image/preview/01ARZ3NDEKTSV4RRFFQ69G5FAV',
            };

            saveAnonymousConversion(record1);

            const items = getAnonymousHistory();
            expect(items).toHaveLength(1);
            expect(items[0]?.id).toBe(record1.id);

            removeAnonymousConversion(record1.id);
            expect(getAnonymousHistory()).toEqual([]);
        });

        it('descarta itens mais antigos que 24 horas', () => {
            const oldDate = new Date(Date.now() - 25 * 3600 * 1000).toISOString();
            const expiredRecord = {
                id: '01ARZ3NDEKTSV4RRFFQ69G5OLD',
                filename: 'old.png',
                mime_type: 'image/png',
                file_size_bytes: 1024,
                base64_size_bytes: 1400,
                created_at: oldDate,
                download_url: '/dl',
                preview_url: '/prev',
            };

            localStorage.setItem(
                'devtools_base64_image_history',
                JSON.stringify([expiredRecord]),
            );

            expect(getAnonymousHistory()).toEqual([]);
        });
    });

    describe('Formatters (JSON & XML)', () => {
        const sampleItem: ConversionItem = {
            id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            filename: 'test & demo <1>.png',
            mime_type: 'image/png',
            file_size_bytes: 1024,
            base64_size_bytes: 1400,
            created_at: new Date().toISOString(),
            download_url: '/dl',
            data_uri: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        };

        it('escapa caracteres especiais de XML', () => {
            expect(escapeXml('a & b < c > "d" \'e\'')).toBe(
                'a &amp; b &lt; c &gt; &quot;d&quot; &apos;e&apos;',
            );
        });

        it('extrai base64 puro removendo o prefixo Data URI', () => {
            const raw = extractRawBase64(sampleItem.data_uri!);
            expect(raw).toBe('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
            expect(extractRawBase64('simpleBase64==')).toBe('simpleBase64==');
        });

        it('formata JSON valido com metadados e base64', () => {
            const jsonStr = formatJsonOutput(sampleItem);
            const parsed = JSON.parse(jsonStr);

            expect(parsed.filename).toBe('test & demo <1>.png');
            expect(parsed.mime_type).toBe('image/png');
            expect(parsed.file_size_bytes).toBe(1024);
            expect(parsed.base64_size_bytes).toBe(1400);
            expect(parsed.data_uri).toBe(sampleItem.data_uri);
            expect(parsed.base64).toBe(extractRawBase64(sampleItem.data_uri!));
        });

        it('formata documento XML valido com tags e escape', () => {
            const xmlStr = formatXmlOutput(sampleItem);

            expect(xmlStr).toContain('<?xml version="1.0" encoding="UTF-8"?>');
            expect(xmlStr).toContain('<image>');
            expect(xmlStr).toContain('<filename>test &amp; demo &lt;1&gt;.png</filename>');
            expect(xmlStr).toContain('<mime_type>image/png</mime_type>');
            expect(xmlStr).toContain('<file_size_bytes>1024</file_size_bytes>');
            expect(xmlStr).toContain('<base64_size_bytes>1400</base64_size_bytes>');
            expect(xmlStr).toContain(`<data_uri>${escapeXml(sampleItem.data_uri!)}</data_uri>`);
            expect(xmlStr).toContain('</image>');
        });

        it('formatSnippet resolve todos os modos suportados', () => {
            expect(formatSnippet('json', sampleItem)).toBe(formatJsonOutput(sampleItem));
            expect(formatSnippet('xml', sampleItem)).toBe(formatXmlOutput(sampleItem));
            expect(formatSnippet('data-uri', sampleItem)).toBe(sampleItem.data_uri);
            expect(formatSnippet('img', sampleItem)).toBe(`<img src="${sampleItem.data_uri}" alt="${sampleItem.filename}" />`);
            expect(formatSnippet('css', sampleItem)).toBe(`background-image: url("${sampleItem.data_uri}");`);
        });
    });
});
