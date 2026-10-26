import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/base64-image/Tool.vue';
import EncoderPanel from '@/tools/base64-image/EncoderPanel.vue';
import type { Base64ImageToolProps, ConversionItem } from '@/tools/base64-image/types';

const TRADUCOES = {
    tools: {
        'base64-image': {
            tabs: {
                encoder: 'Codificador (Imagem → Base64)',
                decoder: 'Decodificador (Base64 → Imagem)',
                history: 'Histórico',
            },
            upload_title: 'Clique para selecionar ou arraste uma imagem',
            upload_subtitle: 'Suporta PNG, JPG, GIF, WebP, SVG, BMP, AVIF e outros formatos',
            max_limit_badge: 'Limite: {limit}',
            pro_plan_badge: 'Plano Pro (Até 25 MB)',
            converting: 'Convertendo imagem no servidor...',
            converting_hint: 'Aguarde o processamento do arquivo.',
            new_image: 'Nova Imagem',
            original_size: 'Tamanho original',
            base64_size: 'Tamanho Base64',
            retention_info: 'Histórico disponível por {hours}h',
            retention_info_day: 'Histórico disponível por {days} dia',
            retention_info_days: 'Histórico disponível por {days} dias',
            history_empty_title: 'Nenhuma conversão recente',
            history_empty_desc: 'As imagens que você converter aparecerão aqui durante o período de retenção.',
            decoder_input_label: 'Cole a string Base64 ou Data URI:',
            decoder_output_label: 'Imagem Decodificada:',
            decoder_placeholder: 'data:image/png;base64,...',
            decoder_empty_prompt: 'Nenhuma imagem decodificada ainda.',
            decoder_empty_hint: 'Cole uma string Base64 válida à esquerda para visualizar e baixar.',
            errors: {
                title: 'Erro na conversão',
                invalid_image: 'O arquivo enviado não é uma imagem válida.',
                file_too_large: 'O arquivo excede o limite máximo permitido de {limit}.',
                conversion_failed: 'Ocorreu uma falha ao processar a imagem. Tente novamente.',
                invalid_base64: 'A sequência Base64 fornecida é inválida ou está corrompida.',
            },
        },
    },
};

function i18n() {
    const instance = createI18nInstance();
    applyTranslations(instance, { locale: 'pt-BR', translations: TRADUCOES });
    return instance;
}

describe('Base64 Image Tool Component', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('renderiza normalmente com props desempacotadas de visitante (Free)', () => {
        const props: Base64ImageToolProps = {
            isAuthenticated: false,
            isPaidPlan: false,
            maxUploadBytes: 2 * 1024 * 1024,
            downloadThresholdBytes: 2 * 1024 * 1024,
            retentionHours: 24,
        };

        const wrapper = mount(Tool, {
            props,
            global: { plugins: [i18n()] },
        });

        // A aba do codificador deve exibir a dropzone e o limite de 2 MB
        expect(wrapper.text()).toContain('Clique para selecionar ou arraste uma imagem');
        expect(wrapper.text()).toContain('Limite: 2 MB');
        // Badge do plano Pro nao deve estar visivel
        expect(wrapper.text()).not.toContain('Plano Pro (Até 25 MB)');
    });

    it('renderiza badge Pro e limite expandido quando autenticado com plano pago', () => {
        const props: Base64ImageToolProps = {
            isAuthenticated: true,
            isPaidPlan: true,
            maxUploadBytes: 25 * 1024 * 1024,
            downloadThresholdBytes: 2 * 1024 * 1024,
            retentionHours: 720,
        };

        const wrapper = mount(Tool, {
            props,
            global: { plugins: [i18n()] },
        });

        expect(wrapper.text()).toContain('Limite: 25 MB');
        expect(wrapper.text()).toContain('Plano Pro (Até 25 MB)');
    });

    it('nao lanca excecao quando montado sem props explicitas (resiliencia com defaults)', () => {
        expect(() => {
            mount(Tool, {
                global: { plugins: [i18n()] },
            });
        }).not.toThrow();
    });

    it('exibe retencao em horas (24h) no painel de conversao para plano Free', async () => {
        const mockConversion: ConversionItem = {
            id: 'conv-123',
            filename: 'test.png',
            mime_type: 'image/png',
            file_size_bytes: 1024,
            base64_size_bytes: 1400,
            data_uri: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            created_at: new Date().toISOString(),
            download_url: '/dl/123',
            preview_url: '/preview/123',
        };

        vi.spyOn(global, 'fetch').mockResolvedValueOnce({
            ok: true,
            json: async () => mockConversion,
        } as Response);

        const wrapper = mount(EncoderPanel, {
            props: {
                toolProps: {
                    isAuthenticated: false,
                    isPaidPlan: false,
                    retentionHours: 24,
                },
            },
            global: { plugins: [i18n()] },
        });

        const file = new File(['dummy'], 'test.png', { type: 'image/png' });
        const input = wrapper.find('input[type="file"]');
        Object.defineProperty(input.element, 'files', {
            value: [file],
            writable: false,
        });
        await input.trigger('change');
        await flushPromises();

        expect(wrapper.text()).toContain('Histórico disponível por 24h');
    });

    it('exibe retencao em dias (30 dias) no painel de conversao para plano Pro (720h)', async () => {
        const mockConversion: ConversionItem = {
            id: 'conv-456',
            filename: 'pro.png',
            mime_type: 'image/png',
            file_size_bytes: 2048,
            base64_size_bytes: 2800,
            data_uri: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            created_at: new Date().toISOString(),
            download_url: '/dl/456',
            preview_url: '/preview/456',
        };

        vi.spyOn(global, 'fetch').mockResolvedValueOnce({
            ok: true,
            json: async () => mockConversion,
        } as Response);

        const wrapper = mount(EncoderPanel, {
            props: {
                toolProps: {
                    isAuthenticated: true,
                    isPaidPlan: true,
                    retentionHours: 720,
                },
            },
            global: { plugins: [i18n()] },
        });

        const file = new File(['dummy'], 'pro.png', { type: 'image/png' });
        const input = wrapper.find('input[type="file"]');
        Object.defineProperty(input.element, 'files', {
            value: [file],
            writable: false,
        });
        await input.trigger('change');
        await flushPromises();

        expect(wrapper.text()).toContain('Histórico disponível por 30 dias');
    });
});
