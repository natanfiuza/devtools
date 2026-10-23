<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatBytes } from '@/lib/bytes';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Download, Trash2, Sparkles, AlertCircle, Image as ImageIcon } from '@lucide/vue';
import { parseBase64Image, type DecodedImageResult } from './lib/decoder';

const { t, locale } = useI18n();

const inputText = ref('');
const decodedResult = computed<DecodedImageResult>(() => {
    return parseBase64Image(inputText.value);
});

// Exemplo de pequeno PNG transparente 1x1 vermelho
const EXAMPLE_BASE64 =
    'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

function loadExample() {
    inputText.value = EXAMPLE_BASE64;
}

function clear() {
    inputText.value = '';
}

function downloadImage() {
    if (!decodedResult.value.valid || !decodedResult.value.objectUrl) return;

    const extension = decodedResult.value.extension || 'png';
    const link = document.createElement('a');
    link.href = decodedResult.value.objectUrl;
    link.download = `decoded_image_${Date.now()}.${extension}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Painel Esquerdo: Entrada -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <label class="text-sm font-medium">
                    {{ t('tools.base64-image.decoder_input_label') }}
                </label>
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" class="h-8 text-xs" @click="loadExample">
                        <Sparkles class="mr-1 h-3 w-3" />
                        {{ t('tools.base64-image.example') }}
                    </Button>
                    <Button
                        v-if="inputText"
                        variant="ghost"
                        size="sm"
                        class="h-8 text-xs text-muted-foreground hover:text-destructive"
                        @click="clear"
                    >
                        <Trash2 class="mr-1 h-3 w-3" />
                        {{ t('tools.base64-image.clear') }}
                    </Button>
                </div>
            </div>

            <textarea
                v-model="inputText"
                rows="14"
                :placeholder="t('tools.base64-image.decoder_placeholder')"
                class="w-full resize-y rounded-lg border bg-muted/30 p-3 font-mono text-xs leading-relaxed focus:outline-none focus:ring-1 focus:ring-primary"
            ></textarea>
        </div>

        <!-- Painel Direito: Previa e Download -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <label class="text-sm font-medium">
                    {{ t('tools.base64-image.decoder_output_label') }}
                </label>
                <Button
                    v-if="decodedResult.valid"
                    size="sm"
                    variant="default"
                    @click="downloadImage"
                >
                    <Download class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('tools.base64-image.download_image') }}
                </Button>
            </div>

            <!-- Imagem Decodificada Valida -->
            <div
                v-if="decodedResult.valid && decodedResult.objectUrl"
                class="flex flex-col items-center justify-center rounded-xl border bg-card p-6 text-center space-y-4 min-h-[300px]"
            >
                <div class="flex items-center justify-center rounded-lg border bg-muted/20 p-4 max-h-[360px] max-w-full overflow-hidden">
                    <img
                        :src="decodedResult.objectUrl"
                        alt="Decoded Preview"
                        class="max-h-[300px] max-w-full object-contain rounded"
                    />
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground">
                    <Badge variant="secondary">
                        {{ decodedResult.mimeType }}
                    </Badge>
                    <span>
                        {{ t('tools.base64-image.size') }}:
                        <strong>{{ formatBytes(decodedResult.sizeBytes || 0, locale) }}</strong>
                    </span>
                </div>
            </div>

            <!-- Erro de Decodificacao -->
            <Alert v-else-if="inputText && !decodedResult.valid" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ t('tools.base64-image.errors.invalid_base64') }}
                </AlertDescription>
            </Alert>

            <!-- Estado Vazio -->
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center text-muted-foreground min-h-[300px]"
            >
                <ImageIcon class="h-10 w-10 stroke-1 text-muted-foreground/60 mb-2" />
                <p class="text-sm">{{ t('tools.base64-image.decoder_empty_prompt') }}</p>
                <p class="text-xs text-muted-foreground/70 mt-1">
                    {{ t('tools.base64-image.decoder_empty_hint') }}
                </p>
            </div>
        </div>
    </div>
</template>
