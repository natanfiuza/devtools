<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatBytes } from '@/lib/bytes';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import {
    Download,
    Trash2,
    Clock,
    Image as ImageIcon,
    FileX,
    ExternalLink,
} from '@lucide/vue';
import type { Base64ImageToolProps, ConversionItem } from './types';
import { getAnonymousHistory, removeAnonymousConversion } from './lib/storage';

const props = withDefaults(
    defineProps<{
        toolProps?: Partial<Base64ImageToolProps>;
    }>(),
    {
        toolProps: () => ({
            isAuthenticated: false,
            isPaidPlan: false,
            maxUploadBytes: 2 * 1024 * 1024,
            downloadThresholdBytes: 2 * 1024 * 1024,
            retentionHours: 24,
        }),
    },
);

const { t, locale } = useI18n();

const items = ref<ConversionItem[]>([]);
const isLoading = ref(false);

async function loadHistory() {
    if (props.toolProps?.isAuthenticated) {
        isLoading.value = true;
        try {
            const res = await fetch('/api/tools/base64-image/history', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                items.value = await res.json();
            }
        } catch {
            // falha na consulta
        } finally {
            isLoading.value = false;
        }
    } else {
        const anon = getAnonymousHistory();
        items.value = anon.map((a) => ({
            id: a.id,
            filename: a.filename,
            mime_type: a.mime_type,
            file_size_bytes: a.file_size_bytes,
            base64_size_bytes: a.base64_size_bytes,
            created_at: a.created_at,
            download_url: a.download_url,
            preview_url: a.preview_url,
            is_expired: false,
        }));
    }
}

async function deleteItem(id: string) {
    if (!confirm(t('tools.base64-image.confirm_delete'))) {
        return;
    }

    if (!props.toolProps?.isAuthenticated) {
        removeAnonymousConversion(id);
    }

    try {
        await fetch(`/api/tools/base64-image/history/${id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
        });
    } catch {
        // falha silenciosa
    }

    items.value = items.value.filter((item) => item.id !== id);
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match && match[1] ? decodeURIComponent(match[1]) : '';
}

function formatDate(dateStr: string): string {
    try {
        const date = new Date(dateStr);
        return new Intl.DateTimeFormat(locale.value.replaceAll('_', '-'), {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(date);
    } catch {
        return dateStr;
    }
}

defineExpose({
    reload: loadHistory,
});

onMounted(() => {
    loadHistory();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Indicador de Carregamento -->
        <div v-if="isLoading" class="flex justify-center p-12">
            <Spinner class="h-6 w-6 text-primary" />
        </div>

        <!-- Lista Vazia -->
        <div
            v-else-if="items.length === 0"
            class="flex flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center text-muted-foreground"
        >
            <Clock class="h-10 w-10 stroke-1 text-muted-foreground/60 mb-2" />
            <p class="text-sm font-medium">{{ t('tools.base64-image.history_empty_title') }}</p>
            <p class="text-xs text-muted-foreground/70 mt-1 max-w-sm">
                {{ t('tools.base64-image.history_empty_desc') }}
            </p>
        </div>

        <!-- Tabela/Cards de Itens do Historico -->
        <div v-else class="space-y-3">
            <div
                v-for="item in items"
                :key="item.id"
                class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-xl border bg-card p-4 transition-colors"
                :class="item.is_expired ? 'opacity-70 bg-muted/20' : 'hover:border-primary/40'"
            >
                <div class="flex items-center gap-4 min-w-0">
                    <!-- Previa ou Icone -->
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg border bg-muted/40 overflow-hidden">
                        <img
                            v-if="!item.is_expired && item.preview_url"
                            :src="item.preview_url"
                            :alt="item.filename"
                            class="h-full w-full object-cover"
                        />
                        <FileX v-else-if="item.is_expired" class="h-6 w-6 text-destructive/70" />
                        <ImageIcon v-else class="h-6 w-6 text-muted-foreground" />
                    </div>

                    <div class="min-w-0 space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm truncate max-w-[200px] sm:max-w-xs" :title="item.filename">
                                {{ item.filename }}
                            </span>
                            <Badge v-if="item.is_expired" variant="destructive" class="text-[10px] px-1.5 py-0 h-4">
                                {{ t('tools.base64-image.expired_badge') }}
                            </Badge>
                            <Badge v-else variant="secondary" class="text-[10px] px-1.5 py-0 h-4">
                                {{ t('tools.base64-image.active_badge') }}
                            </Badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span>{{ item.mime_type }}</span>
                            <span>•</span>
                            <span>{{ formatBytes(item.file_size_bytes, locale) }}</span>
                            <span>•</span>
                            <span>{{ formatDate(item.created_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Acoes -->
                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 text-xs"
                        :disabled="item.is_expired"
                        as="a"
                        :href="item.is_expired ? undefined : item.download_url"
                    >
                        <Download class="mr-1 h-3.5 w-3.5" />
                        {{ t('tools.base64-image.download_txt') }}
                    </Button>

                    <Button
                        v-if="item.preview_url"
                        size="sm"
                        variant="ghost"
                        class="h-8 text-xs"
                        :disabled="item.is_expired"
                        as="a"
                        :href="item.is_expired ? undefined : item.preview_url"
                        target="_blank"
                    >
                        <ExternalLink class="mr-1 h-3.5 w-3.5" />
                        {{ t('tools.base64-image.preview_img') }}
                    </Button>

                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-8 text-xs text-muted-foreground hover:text-destructive"
                        @click="deleteItem(item.id)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
