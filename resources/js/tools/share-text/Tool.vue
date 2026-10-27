<script setup lang="ts">
import { json } from '@codemirror/lang-json';
import { markdown } from '@codemirror/lang-markdown';
import { sql } from '@codemirror/lang-sql';
import type { Extension } from '@codemirror/state';
import {
    Check,
    Clock,
    Copy,
    CopyPlus,
    ExternalLink,
    FileCode,
    FilePlus,
    History,
    Loader2,
    Save,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { byteLength, formatBytes } from '@/lib/bytes';

defineOptions({ inheritAttrs: false });

export interface InitialDocument {
    key: string;
    data: string;
    extension?: string | null;
    created_at?: string;
    size_bytes?: number;
    is_owner?: boolean;
}

export interface ShareTextToolProps {
    isAuthenticated?: boolean;
    isPaidPlan?: boolean;
    maxUploadBytes?: number;
    retentionHours?: number | null;
    initialDocument?: InitialDocument | null;
}

const props = withDefaults(defineProps<ShareTextToolProps>(), {
    isAuthenticated: false,
    isPaidPlan: false,
    maxUploadBytes: 1024 * 1024,
    retentionHours: 72,
    initialDocument: null,
});

const { t, locale } = useI18n();
const copyShareFeedback = useCopyFeedback();
const copyRawFeedback = useCopyFeedback();

const content = ref(props.initialDocument?.data ?? '');
const currentKey = ref<string | null>(props.initialDocument?.key ?? null);
const currentExtension = ref<string | null>(
    props.initialDocument?.extension ?? null,
);
const isOwner = ref(props.initialDocument?.is_owner ?? false);

const isSaving = ref(false);
const errorMessage = ref<string | null>(null);
const showShareDialog = ref(false);
const showHistoryDialog = ref(false);

interface HistoryItem {
    id: string;
    size_bytes: number;
    extension: string | null;
    created_at: string;
}

const historyItems = ref<HistoryItem[]>([]);
const isLoadingHistory = ref(false);

const selectedLanguage = ref<string>(
    props.initialDocument?.extension
        ? detectLang(props.initialDocument.extension)
        : 'auto',
);

function detectLang(ext: string): string {
    const clean = ext.toLowerCase().replace(/^\./, '');
    if (['json'].includes(clean)) return 'json';
    if (['sql'].includes(clean)) return 'sql';
    if (['md', 'markdown'].includes(clean)) return 'markdown';
    return 'auto';
}

const editorExtensions = computed<Extension[]>(() => {
    switch (selectedLanguage.value) {
        case 'json':
            return [json()];
        case 'sql':
            return [sql()];
        case 'markdown':
            return [markdown()];
        default:
            return [];
    }
});

const currentByteLength = computed(() => byteLength(content.value));
const isTooLarge = computed(
    () => currentByteLength.value > props.maxUploadBytes,
);
const linesCount = computed(() => {
    if (content.value === '') return 0;
    return content.value.split('\n').length;
});

const shareUrl = computed(() => {
    if (!currentKey.value) return '';
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const ext = currentExtension.value ? `.${currentExtension.value}` : '';
    return `${origin}/share/${currentKey.value}${ext}`;
});

const rawUrl = computed(() => {
    if (!currentKey.value) return '';
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const ext = currentExtension.value ? `.${currentExtension.value}` : '';
    return `${origin}/raw/${currentKey.value}${ext}`;
});

async function saveDocument(): Promise<void> {
    if (isSaving.value) return;
    if (content.value.trim() === '') {
        errorMessage.value = t('tools.share-text.errors.empty_content');
        return;
    }
    if (isTooLarge.value) {
        const maxMb = Math.round(props.maxUploadBytes / (1024 * 1024));
        errorMessage.value = t('tools.share-text.errors.size_exceeded', {
            max: maxMb,
        });
        return;
    }

    errorMessage.value = null;
    isSaving.value = true;

    try {
        const ext =
            selectedLanguage.value !== 'auto' ? selectedLanguage.value : null;

        const response = await fetch('/documents', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                data: content.value,
                extension: ext,
            }),
        });

        if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
                errData.message ||
                    errData.errors?.data?.[0] ||
                    t('tools.share-text.errors.save_failed'),
            );
        }

        const data = (await response.json()) as { key: string };
        currentKey.value = data.key;
        currentExtension.value = ext;
        isOwner.value = props.isAuthenticated;

        // Atualiza a URL sem recarregar a pagina
        const extSuffix = ext ? `.${ext}` : '';
        const newUrl = `/${locale.value}/tools/share-text/${data.key}${extSuffix}`;
        if (typeof window !== 'undefined') {
            window.history.pushState({}, '', newUrl);
        }

        showShareDialog.value = true;
    } catch (err) {
        errorMessage.value =
            err instanceof Error
                ? err.message
                : t('tools.share-text.errors.save_failed');
    } finally {
        isSaving.value = false;
    }
}

function newDocument(): void {
    content.value = '';
    currentKey.value = null;
    currentExtension.value = null;
    isOwner.value = false;
    errorMessage.value = null;
    selectedLanguage.value = 'auto';

    if (typeof window !== 'undefined') {
        const newUrl = `/${locale.value}/tools/share-text`;
        window.history.pushState({}, '', newUrl);
    }
}

function duplicateDocument(): void {
    currentKey.value = null;
    currentExtension.value = null;
    isOwner.value = false;
    errorMessage.value = null;

    if (typeof window !== 'undefined') {
        const newUrl = `/${locale.value}/tools/share-text`;
        window.history.pushState({}, '', newUrl);
    }
}

function openRaw(): void {
    if (!currentKey.value || typeof window === 'undefined') return;
    window.open(rawUrl.value, '_blank');
}

async function copyShareUrl(): Promise<void> {
    if (!shareUrl.value) return;
    await copyShareFeedback.copy(
        shareUrl.value,
        t('tools.share-text.actions.copied'),
    );
}

async function copyRawUrl(): Promise<void> {
    if (!rawUrl.value) return;
    await copyRawFeedback.copy(
        rawUrl.value,
        t('tools.share-text.actions.copied'),
    );
}

async function openHistory(): Promise<void> {
    showHistoryDialog.value = true;
    isLoadingHistory.value = true;

    try {
        const response = await fetch('/api/tools/share-text/history', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const json = (await response.json()) as { data: HistoryItem[] };
            historyItems.value = json.data;
        }
    } catch {
        // Falha silenciosa no historico
    } finally {
        isLoadingHistory.value = false;
    }
}

async function deleteFromHistory(id: string): Promise<void> {
    if (!confirm(t('tools.share-text.history.confirm_delete'))) {
        return;
    }

    try {
        const response = await fetch(`/api/tools/share-text/${id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            historyItems.value = historyItems.value.filter(
                (item) => item.id !== id,
            );
            if (currentKey.value === id) {
                newDocument();
            }
        }
    } catch {
        // Erro na exclusao
    }
}

function handleGlobalKeydown(e: KeyboardEvent): void {
    const isMod = e.ctrlKey || e.metaKey;
    if (!isMod) return;

    if (e.key === 's' || e.key === 'S') {
        e.preventDefault();
        saveDocument();
    } else if (e.key === 'n' || e.key === 'N') {
        e.preventDefault();
        newDocument();
    } else if (e.key === 'd' || e.key === 'D') {
        if (currentKey.value) {
            e.preventDefault();
            duplicateDocument();
        }
    } else if (e.key === 'r' || e.key === 'R') {
        if (currentKey.value) {
            e.preventDefault();
            openRaw();
        }
    }
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleGlobalKeydown);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleGlobalKeydown);
    }
});
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-6xl flex-col gap-4 py-2"
        data-test="share-text-tool"
    >
        <!-- Barra de Acoes Superior / Toolbar -->
        <header
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-surface p-3 shadow-xs"
        >
            <!-- Esquerda: Status / Badge do Documento -->
            <div class="flex flex-wrap items-center gap-2">
                <template v-if="currentKey">
                    <Badge variant="outline" class="font-mono text-xs">
                        {{ currentKey }}
                    </Badge>
                    <Badge
                        v-if="props.isAuthenticated"
                        variant="secondary"
                        class="gap-1 text-xs"
                    >
                        <ShieldCheck class="h-3 w-3 text-emerald-500" />
                        {{ t('tools.share-text.badges.permanent') }}
                    </Badge>
                    <Badge v-else variant="secondary" class="gap-1 text-xs">
                        <Clock class="h-3 w-3 text-amber-500" />
                        {{ t('tools.share-text.badges.anonymous') }}
                    </Badge>
                </template>
                <template v-else>
                    <Badge variant="outline" class="text-xs text-subtle">
                        {{ t('tools.share-text.badges.draft') }}
                    </Badge>
                    <Badge
                        v-if="props.isAuthenticated"
                        variant="secondary"
                        class="text-xs"
                    >
                        {{ t('tools.share-text.badges.permanent') }}
                    </Badge>
                    <Badge v-else variant="secondary" class="text-xs">
                        {{ t('tools.share-text.badges.anonymous') }}
                    </Badge>
                </template>
            </div>

            <!-- Direita: Acoes e Atalhos de Teclado -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Seletor de Linguagem -->
                <Select v-model="selectedLanguage">
                    <SelectTrigger class="h-8 w-[130px] text-xs">
                        <SelectValue
                            :placeholder="t('tools.share-text.languages.auto')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="auto">
                            {{ t('tools.share-text.languages.auto') }}
                        </SelectItem>
                        <SelectItem value="json">
                            {{ t('tools.share-text.languages.json') }}
                        </SelectItem>
                        <SelectItem value="sql">
                            {{ t('tools.share-text.languages.sql') }}
                        </SelectItem>
                        <SelectItem value="markdown">
                            {{ t('tools.share-text.languages.markdown') }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- Salvar -->
                <Button
                    size="sm"
                    class="h-8 gap-1.5"
                    :disabled="isSaving || isTooLarge"
                    @click="saveDocument"
                >
                    <Loader2 v-if="isSaving" class="h-3.5 w-3.5 animate-spin" />
                    <Save v-else class="h-3.5 w-3.5" />
                    <span>{{ t('tools.share-text.actions.save') }}</span>
                    <kbd
                        class="hidden rounded bg-primary-foreground/20 px-1 py-0.5 text-[10px] sm:inline"
                        >Ctrl+S</kbd
                    >
                </Button>

                <!-- Novo -->
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5"
                    @click="newDocument"
                >
                    <FilePlus class="h-3.5 w-3.5" />
                    <span>{{ t('tools.share-text.actions.new') }}</span>
                    <kbd
                        class="hidden rounded bg-muted px-1 py-0.5 text-[10px] sm:inline"
                        >Ctrl+N</kbd
                    >
                </Button>

                <!-- Duplicar & Editar (apenas para paste existente) -->
                <Button
                    v-if="currentKey"
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5"
                    @click="duplicateDocument"
                >
                    <CopyPlus class="h-3.5 w-3.5" />
                    <span>{{ t('tools.share-text.actions.duplicate') }}</span>
                    <kbd
                        class="hidden rounded bg-muted px-1 py-0.5 text-[10px] sm:inline"
                        >Ctrl+D</kbd
                    >
                </Button>

                <!-- Raw (apenas para paste existente) -->
                <Button
                    v-if="currentKey"
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5"
                    @click="openRaw"
                >
                    <FileCode class="h-3.5 w-3.5" />
                    <span>{{ t('tools.share-text.actions.raw') }}</span>
                    <kbd
                        class="hidden rounded bg-muted px-1 py-0.5 text-[10px] sm:inline"
                        >Ctrl+R</kbd
                    >
                </Button>

                <!-- Copiar Link (apenas para paste existente) -->
                <Button
                    v-if="currentKey"
                    variant="secondary"
                    size="sm"
                    class="h-8 gap-1.5"
                    @click="copyShareUrl"
                >
                    <Check
                        v-if="copyShareFeedback.message.value"
                        class="h-3.5 w-3.5 text-emerald-500"
                    />
                    <Copy v-else class="h-3.5 w-3.5" />
                    <span>{{
                        copyShareFeedback.message.value ||
                        t('tools.share-text.actions.copy_link')
                    }}</span>
                </Button>

                <!-- Historico (para usuarios logados) -->
                <Button
                    v-if="props.isAuthenticated"
                    variant="ghost"
                    size="sm"
                    class="h-8 gap-1.5"
                    @click="openHistory"
                >
                    <History class="h-3.5 w-3.5" />
                    <span>{{ t('tools.share-text.actions.history') }}</span>
                </Button>
            </div>
        </header>

        <!-- Mensagem de Erro -->
        <div
            v-if="errorMessage"
            class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-2.5 text-xs text-destructive"
        >
            {{ errorMessage }}
        </div>

        <!-- Editor de Codigo (CodeMirror 6) -->
        <div
            class="min-h-[500px] flex-1 overflow-hidden rounded-xl border bg-surface shadow-xs"
        >
            <CodeEditor
                v-model="content"
                :placeholder="t('tools.share-text.placeholder')"
                :extensions="editorExtensions"
                testid="share-text-editor"
            />
        </div>

        <!-- Barra Inferior / Metricas -->
        <footer
            class="flex flex-wrap items-center justify-between gap-2 px-1 text-xs text-subtle"
        >
            <div class="flex items-center gap-4">
                <span
                    >{{ linesCount }}
                    {{ linesCount === 1 ? 'linha' : 'linhas' }}</span
                >
                <span>{{ currentByteLength }} caracteres</span>
                <span :class="{ 'font-semibold text-destructive': isTooLarge }">
                    {{ formatBytes(currentByteLength, locale) }} /
                    {{ formatBytes(props.maxUploadBytes, locale) }}
                </span>
            </div>

            <div v-if="currentKey" class="flex items-center gap-2">
                <span class="font-mono text-[11px]">{{ shareUrl }}</span>
            </div>
        </footer>

        <!-- Modal de Compartilhamento Apos Salvar -->
        <Dialog v-model:open="showShareDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        t('tools.share-text.dialog.title')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ t('tools.share-text.dialog.description') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <!-- URL Principal -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-subtle">
                            {{ t('tools.share-text.dialog.url_label') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                readonly
                                :value="shareUrl"
                                class="flex-1 rounded-md border bg-muted/40 px-3 py-1.5 font-mono text-xs text-foreground select-all"
                            />
                            <Button
                                size="sm"
                                variant="secondary"
                                class="gap-1"
                                @click="copyShareUrl"
                            >
                                <Check
                                    v-if="copyShareFeedback.message.value"
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                <Copy v-else class="h-3.5 w-3.5" />
                                <span>{{
                                    copyShareFeedback.message.value
                                        ? t('tools.share-text.actions.copied')
                                        : t(
                                              'tools.share-text.actions.copy_link',
                                          )
                                }}</span>
                            </Button>
                        </div>
                    </div>

                    <!-- URL Raw -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-subtle">
                            {{ t('tools.share-text.dialog.raw_url_label') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                readonly
                                :value="rawUrl"
                                class="flex-1 rounded-md border bg-muted/40 px-3 py-1.5 font-mono text-xs text-foreground select-all"
                            />
                            <Button
                                size="sm"
                                variant="secondary"
                                class="gap-1"
                                @click="copyRawUrl"
                            >
                                <Check
                                    v-if="copyRawFeedback.message.value"
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                <Copy v-else class="h-3.5 w-3.5" />
                                <span>{{
                                    copyRawFeedback.message.value
                                        ? t('tools.share-text.actions.copied')
                                        : t(
                                              'tools.share-text.actions.copy_link',
                                          )
                                }}</span>
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="gap-1"
                                @click="openRaw"
                            >
                                <ExternalLink class="h-3.5 w-3.5" />
                                <span>Raw</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="sm:justify-end">
                    <Button
                        variant="secondary"
                        @click="showShareDialog = false"
                    >
                        {{ t('tools.share-text.dialog.close') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal de Historico de Textos do Usuario -->
        <Dialog v-model:open="showHistoryDialog">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('tools.share-text.history.title')
                    }}</DialogTitle>
                </DialogHeader>

                <div class="max-h-[380px] overflow-y-auto py-2">
                    <div
                        v-if="isLoadingHistory"
                        class="flex items-center justify-center py-8"
                    >
                        <Loader2
                            class="h-6 w-6 animate-spin text-muted-foreground"
                        />
                    </div>

                    <div
                        v-else-if="historyItems.length === 0"
                        class="py-8 text-center text-xs text-subtle"
                    >
                        {{ t('tools.share-text.history.empty') }}
                    </div>

                    <div
                        v-else
                        class="divide-y divide-border rounded-md border"
                    >
                        <div
                            v-for="item in historyItems"
                            :key="item.id"
                            class="flex items-center justify-between p-3 text-xs"
                        >
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-medium">{{
                                        item.id
                                    }}</span>
                                    <Badge
                                        v-if="item.extension"
                                        variant="outline"
                                        class="text-[10px]"
                                    >
                                        {{ item.extension }}
                                    </Badge>
                                </div>
                                <span class="text-[11px] text-subtle">
                                    {{ formatBytes(item.size_bytes, locale) }} •
                                    {{
                                        new Date(
                                            item.created_at,
                                        ).toLocaleString(
                                            locale.replace('_', '-'),
                                        )
                                    }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <a
                                    :href="`/${locale}/tools/share-text/${item.id}${item.extension ? '.' + item.extension : ''}`"
                                    class="inline-flex h-7 items-center rounded-md border px-2 text-xs font-medium hover:bg-muted"
                                >
                                    {{ t('tools.share-text.history.open') }}
                                </a>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-destructive hover:bg-destructive/10"
                                    @click="deleteFromHistory(item.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
