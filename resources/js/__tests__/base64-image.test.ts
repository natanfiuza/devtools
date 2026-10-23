import { describe, it, expect, beforeEach } from 'vitest';
import { parseBase64Image } from '../tools/base64-image/lib/decoder';
import {
    getAnonymousHistory,
    saveAnonymousConversion,
    removeAnonymousConversion,
} from '../tools/base64-image/lib/storage';

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
});
