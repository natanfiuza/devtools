<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatBytes } from '@/lib/bytes';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
    Upload,
    Check,
    Copy,
    Download,
    RefreshCw,
    AlertCircle,
    FileText,
    Code,
    Sparkles,
} from '@lucide/vue';
import type { Base64ImageToolProps, OutputFormat, ConversionItem } from './types';
import { saveAnonymousConversion } from './lib/storage';

const props = defineProps<{
    toolProps: Base64ImageToolProps;
}>();

const emit = defineEmits<{
    (e: 'conversion-done', item: ConversionItem): void;
}>();

const { t, locale } = useI18n();
const { message: copyMessage, copy } = useCopyFeedback();
const copied = computed(() => copyMessage.value !== null);

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);
const currentResult = ref<ConversionItem | null>(null);
const selectedFormat = ref<OutputFormat>('data-uri');

const formattedMaxLimit = computed(() =>
    formatBytes(props.toolProps.maxUploadBytes, locale.value),
);

function onDrop(event: DragEvent) {
    isDragging.value = false;
    const files = event.dataTransfer?.files;
    if (files && files[0]) {
        handleFile(files[0]);
    }
}

function onFileSelect(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        handleFile(target.files[0]);
    }
}

async function handleFile(file: File) {
    errorMessage.value = null;

    if (!file.type.startsWith('image/')) {
        errorMessage.value = t('tools.base64-image.errors.invalid_image');
        return;
    }

    if (file.size > props.toolProps.maxUploadBytes) {
        errorMessage.value = t('tools.base64-image.errors.file_too_large', {
            limit: formattedMaxLimit.value,
        });
        return;
    }

    isLoading.value = true;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch('/api/tools/base64-image/convert', {
            method: 'POST',
            body: formData,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || t('tools.base64-image.errors.conversion_failed'));
        }

        const data: ConversionItem = await response.json();
        currentResult.value = data;

        // Se usuario anonimo, grava em localStorage
        if (!props.toolProps.isAuthenticated) {
            saveAnonymousConversion({
                id: data.id,
                filename: data.filename,
                mime_type: data.mime_type,
                file_size_bytes: data.file_size_bytes,
                base64_size_bytes: data.base64_size_bytes,
                created_at: new Date().toISOString(),
                download_url: data.download_url,
                preview_url: data.preview_url || data.download_url,
            });
        }

        emit('conversion-done', data);

        // Se direct_download = true, dispara o download imediato
        if (data.direct_download && data.download_url) {
            triggerDownload(data.download_url, `${data.filename}.base64.txt`);
        }
    } catch (err: unknown) {
        errorMessage.value =
            err instanceof Error ? err.message : t('tools.base64-image.errors.conversion_failed');
    } finally {
        isLoading.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match && match[1] ? decodeURIComponent(match[1]) : '';
}

function triggerDownload(url: string, filename: string) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const formattedOutput = computed(() => {
    if (!currentResult.value?.data_uri) return '';
    const uri = currentResult.value.data_uri;
    const alt = currentResult.value.filename;

    switch (selectedFormat.value) {
        case 'img':
            return `<img src="${uri}" alt="${alt}" />`;
        case 'css':
            return `background-image: url("${uri}");`;
        case 'data-uri':
        default:
            return uri;
    }
});

function reset() {
    currentResult.value = null;
    errorMessage.value = null;
}
</script>

<template>
    <div class="space-y-6">
        <!-- Area de Upload / Dropzone -->
        <div
            v-if="!currentResult && !isLoading"
            class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-10 text-center transition-colors cursor-pointer"
            :class="isDragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50 hover:bg-muted/30'"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="fileInput?.click()"
        >
            <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="onFileSelect"
            />
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary mb-4">
                <Upload class="h-8 w-8" />
            </div>
            <h3 class="text-lg font-semibold tracking-tight">
                {{ t('tools.base64-image.upload_title') }}
            </h3>
            <p class="text-sm text-muted-foreground mt-1 max-w-sm">
                {{ t('tools.base64-image.upload_subtitle') }}
            </p>
            <div class="mt-4 flex items-center gap-2">
                <Badge variant="outline">
                    {{ t('tools.base64-image.max_limit_badge', { limit: formattedMaxLimit }) }}
                </Badge>
                <Badge v-if="toolProps.isPaidPlan" variant="default" class="bg-primary/90">
                    <Sparkles class="mr-1 h-3 w-3" />
                    {{ t('tools.base64-image.pro_plan_badge') }}
                </Badge>
            </div>
        </div>

        <!-- Estado de Carregamento -->
        <div v-if="isLoading" class="flex flex-col items-center justify-center rounded-xl border p-12 text-center bg-card">
            <Spinner class="h-8 w-8 text-primary mb-4" />
            <p class="text-base font-medium">{{ t('tools.base64-image.converting') }}</p>
            <p class="text-xs text-muted-foreground mt-1">{{ t('tools.base64-image.converting_hint') }}</p>
        </div>

        <!-- Alerta de Erro -->
        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>{{ t('tools.base64-image.errors.title') }}</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <!-- Resultado da Conversao -->
        <div v-if="currentResult && !isLoading" class="space-y-6">
            <!-- Barra de Informacoes e Previa -->
            <div class="flex flex-col lg:flex-row gap-6 rounded-xl border bg-card p-5">
                <!-- Miniatura / Previa da Imagem -->
                <div class="flex items-center justify-center rounded-lg border bg-muted/30 p-2 max-w-[200px] max-h-[160px] overflow-hidden self-center lg:self-start">
                    <img
                        :src="currentResult.data_uri || currentResult.preview_url"
                        :alt="currentResult.filename"
                        class="max-h-36 max-w-full rounded object-contain"
                    />
                </div>

                <!-- Detalhes do Arquivo -->
                <div class="flex-1 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="font-semibold text-base break-all">{{ currentResult.filename }}</h4>
                        <Button variant="outline" size="sm" @click="reset">
                            <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                            {{ t('tools.base64-image.new_image') }}
                        </Button>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1 text-xs text-muted-foreground">
                        <span class="rounded bg-muted px-2 py-0.5 font-medium text-foreground">
                            {{ currentResult.mime_type }}
                        </span>
                        <span>
                            {{ t('tools.base64-image.original_size') }}:
                            <strong>{{ formatBytes(currentResult.file_size_bytes, locale) }}</strong>
                        </span>
                        <span>•</span>
                        <span>
                            {{ t('tools.base64-image.base64_size') }}:
                            <strong>{{ formatBytes(currentResult.base64_size_bytes, locale) }}</strong>
                        </span>
                        <span>•</span>
                        <span>
                            {{ t('tools.base64-image.retention_info', { hours: toolProps.retentionHours }) }}
                        </span>
                    </div>

                    <!-- Mensagem para Download Direto (> 2 MB no Plano Pago) -->
                    <Alert v-if="currentResult.direct_download" class="mt-3 bg-primary/5 border-primary/20">
                        <Sparkles class="h-4 w-4 text-primary" />
                        <AlertTitle class="text-sm font-medium text-foreground">
                            {{ t('tools.base64-image.direct_download_notice_title') }}
                        </AlertTitle>
                        <AlertDescription class="text-xs text-muted-foreground mt-0.5">
                            {{ t('tools.base64-image.direct_download_notice_desc') }}
                        </AlertDescription>
                    </Alert>
                </div>
            </div>

            <!-- Saida Formatada (se nao for direct_download) -->
            <div v-if="!currentResult.direct_download && currentResult.data_uri" class="space-y-3">
                <Tabs v-model="selectedFormat" class="w-full">
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-1">
                        <TabsList>
                            <TabsTrigger value="data-uri" class="text-xs">
                                <FileText class="mr-1.5 h-3.5 w-3.5" />
                                {{ t('tools.base64-image.formats.data_uri') }}
                            </TabsTrigger>
                            <TabsTrigger value="img" class="text-xs">
                                <Code class="mr-1.5 h-3.5 w-3.5" />
                                {{ t('tools.base64-image.formats.img_tag') }}
                            </TabsTrigger>
                            <TabsTrigger value="css" class="text-xs">
                                <Sparkles class="mr-1.5 h-3.5 w-3.5" />
                                {{ t('tools.base64-image.formats.css_bg') }}
                            </TabsTrigger>
                        </TabsList>

                        <div class="flex items-center gap-2">
                            <Button size="sm" variant="secondary" @click="copy(formattedOutput)">
                                <Check v-if="copied" class="mr-1.5 h-3.5 w-3.5 text-primary" />
                                <Copy v-else class="mr-1.5 h-3.5 w-3.5" />
                                {{ copied ? t('tools.base64-image.copied') : t('tools.base64-image.copy') }}
                            </Button>
                            <Button size="sm" variant="outline" as="a" :href="currentResult.download_url">
                                <Download class="mr-1.5 h-3.5 w-3.5" />
                                {{ t('tools.base64-image.download_txt') }}
                            </Button>
                        </div>
                    </div>

                    <TabsContent value="data-uri" class="mt-2">
                        <textarea
                            readonly
                            rows="7"
                            :value="formattedOutput"
                            class="w-full resize-y rounded-lg border bg-muted/40 p-3 font-mono text-xs leading-relaxed focus:outline-none focus:ring-1 focus:ring-primary"
                        ></textarea>
                    </TabsContent>

                    <TabsContent value="img" class="mt-2">
                        <textarea
                            readonly
                            rows="7"
                            :value="formattedOutput"
                            class="w-full resize-y rounded-lg border bg-muted/40 p-3 font-mono text-xs leading-relaxed focus:outline-none focus:ring-1 focus:ring-primary"
                        ></textarea>
                    </TabsContent>

                    <TabsContent value="css" class="mt-2">
                        <textarea
                            readonly
                            rows="7"
                            :value="formattedOutput"
                            class="w-full resize-y rounded-lg border bg-muted/40 p-3 font-mono text-xs leading-relaxed focus:outline-none focus:ring-1 focus:ring-primary"
                        ></textarea>
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Botao de Download caso tenha sido Direct Download -->
            <div v-else class="flex justify-end">
                <Button variant="default" as="a" :href="currentResult.download_url">
                    <Download class="mr-2 h-4 w-4" />
                    {{ t('tools.base64-image.download_txt') }}
                </Button>
            </div>
        </div>
    </div>
</template>
