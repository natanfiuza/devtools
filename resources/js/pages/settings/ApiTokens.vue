<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/api-tokens';
import type { ApiToken, ApiTokensProps } from '@/types';

/**
 * Tokens da API publica v1 (ADR-050).
 *
 * O valor em texto puro e mostrado uma unica vez, na requisicao seguinte ao
 * POST: depois disso so existe o hash no banco. Revogar e definitivo - a
 * integracao que usava o token perde o acesso no pedido seguinte.
 */
const props = defineProps<ApiTokensProps>();

const { t } = useI18n();

const form = useForm({ name: '' });
const copied = ref(false);
const tokenToRevoke = ref<ApiToken | null>(null);
const isRevoking = ref(false);

const hasTokens = computed(() => props.tokens.length > 0);

function createToken(): void {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}

function revokeToken(): void {
    const token = tokenToRevoke.value;

    if (token === null || isRevoking.value) {
        return;
    }

    isRevoking.value = true;

    router.delete(destroy.url(token.id), {
        preserveScroll: true,
        onSuccess: () => {
            tokenToRevoke.value = null;
        },
        onFinish: () => {
            isRevoking.value = false;
        },
    });
}

async function copyToken(): Promise<void> {
    if (props.plainTextToken === null) {
        return;
    }

    await navigator.clipboard.writeText(props.plainTextToken);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

/** Data ISO sem timezone: o dia e a hora do servidor bastam para a lista. */
function formatDate(value: string | null): string {
    return value === null ? '' : value.slice(0, 16).replace('T', ' ');
}

function lastUsed(token: ApiToken): string {
    return token.lastUsedAt === null
        ? t('settings.api_tokens.never')
        : formatDate(token.lastUsedAt);
}
</script>

<template>
    <Head :title="t('settings.api_tokens.title')" />

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.api_tokens.title')"
            :description="t('settings.api_tokens.description')"
        />

        <!-- Valor recem-criado: aparece uma vez e some no proximo carregamento -->
        <div
            v-if="plainTextToken"
            data-test="api-token-novo"
            class="rounded-lg border border-border bg-muted/20 p-4"
        >
            <p class="text-sm font-medium">
                {{ t('settings.api_tokens.once.title') }}
            </p>
            <p class="mt-1 text-sm text-subtle">
                {{ t('settings.api_tokens.once.description') }}
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <code
                    class="min-w-0 flex-1 overflow-x-auto rounded-md border border-border bg-background px-3 py-2 font-mono text-xs"
                    >{{ plainTextToken }}</code
                >
                <Button
                    variant="outline"
                    size="sm"
                    data-test="api-token-copiar"
                    @click="copyToken"
                >
                    <Check v-if="copied" class="size-3.5" />
                    <Copy v-else class="size-3.5" />
                    {{
                        copied
                            ? t('settings.api_tokens.once.copied')
                            : t('settings.api_tokens.once.copy')
                    }}
                </Button>
            </div>
        </div>

        <!-- Criacao -->
        <form
            data-test="api-token-form"
            class="flex flex-wrap items-end gap-3 rounded-lg border border-border p-4"
            @submit.prevent="createToken"
        >
            <div class="grid min-w-64 flex-1 gap-2">
                <Label for="api-token-name">
                    {{ t('settings.api_tokens.create.name') }}
                </Label>
                <Input
                    id="api-token-name"
                    v-model="form.name"
                    data-test="api-token-name"
                    :placeholder="
                        t('settings.api_tokens.create.name_placeholder')
                    "
                    maxlength="80"
                    autocomplete="off"
                />
                <InputError :message="form.errors.name" />
            </div>

            <Button
                type="submit"
                data-test="api-token-criar"
                :disabled="form.processing"
            >
                {{ t('settings.api_tokens.create.submit') }}
            </Button>
        </form>

        <!-- Lista -->
        <div
            v-if="hasTokens"
            data-test="api-tokens-lista"
            class="overflow-hidden rounded-lg border border-border"
        >
            <ul class="divide-y divide-border">
                <li
                    v-for="token in tokens"
                    :key="token.id"
                    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ token.name }}
                        </p>
                        <p class="mt-0.5 text-xs text-subtle">
                            {{ t('settings.api_tokens.created') }}
                            {{ formatDate(token.createdAt) }} ·
                            {{ t('settings.api_tokens.last_used') }}
                            {{ lastUsed(token) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Badge
                            v-for="ability in token.abilities"
                            :key="ability"
                            variant="outline"
                            class="font-mono text-xs"
                        >
                            {{ ability }}
                        </Badge>

                        <Button
                            variant="destructive"
                            size="sm"
                            :data-test="`api-token-revogar-${token.id}`"
                            @click="tokenToRevoke = token"
                        >
                            <Trash2 class="size-3.5" />
                            {{ t('settings.api_tokens.revoke') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </div>

        <div
            v-else
            data-test="api-tokens-vazio"
            class="rounded-lg border border-border px-4 py-12 text-center"
        >
            <KeyRound class="mx-auto size-5 text-subtle" aria-hidden="true" />
            <p class="mt-3 text-sm font-medium">
                {{ t('settings.api_tokens.empty_title') }}
            </p>
            <p class="mx-auto mt-1 max-w-md text-sm text-subtle">
                {{ t('settings.api_tokens.empty') }}
            </p>
        </div>

        <!-- Confirmacao de revogacao -->
        <Dialog
            :open="tokenToRevoke !== null"
            @update:open="
                (open) => {
                    if (!open) tokenToRevoke = null;
                }
            "
        >
            <DialogContent data-test="api-token-modal-revogar">
                <DialogHeader>
                    <DialogTitle>{{
                        t('settings.api_tokens.revoke_title')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ t('settings.api_tokens.revoke_description') }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary" size="sm">
                            {{ t('settings.api_tokens.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        size="sm"
                        data-test="api-token-revogar-confirmar"
                        :disabled="isRevoking"
                        @click="revokeToken"
                    >
                        {{ t('settings.api_tokens.confirm') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
