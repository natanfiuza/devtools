<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Copy, Eye, History, Lock, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { history as historyRoute } from '@/routes/dashboard';
import {
    destroy as destroyHistory,
    preference as historyPreference,
} from '@/routes/dashboard/history';
import type { AccountHistoryItem, AccountHistoryProps } from '@/types';

/**
 * Historico de uso e consultas da conta.
 *
 * Exibe as requisicoes executadas pelo usuario nas ferramentas (AI SQL Builder),
 * incluindo status, dialeto, cota e resumo/SQL retornado. O historico e opt-in
 * e so existe nos planos pagos: no Free a tela mostra o CTA de upgrade.
 */
const props = defineProps<AccountHistoryProps>();

const { t } = useI18n();

const selectedItem = ref<AccountHistoryItem | null>(null);
const isDialogOpen = ref(false);
const copied = ref(false);

// O toggle e otimista: muda na hora e volta se o servidor recusar.
const enabled = ref(props.enabled ?? false);
const isDeleting = ref(false);
const isDeleteDialogOpen = ref(false);

const hasHistory = computed(() => (props.history?.total ?? 0) > 0);
const retentionDays = computed(() => props.retentionDays ?? 90);

watch(
    () => props.enabled,
    (value) => {
        enabled.value = value ?? false;
    },
);

function updatePreference(value: boolean) {
    const previous = enabled.value;
    enabled.value = value;

    router.patch(
        historyPreference.url(),
        { history_enabled: value },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                enabled.value = previous;
            },
        },
    );
}

function deleteHistory() {
    if (isDeleting.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroyHistory.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteDialogOpen.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
}

function openDetails(item: AccountHistoryItem) {
    selectedItem.value = item;
    copied.value = false;
    isDialogOpen.value = true;
}

async function copySql() {
    if (!selectedItem.value?.sql) {
        return;
    }
    await navigator.clipboard.writeText(selectedItem.value.sql);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function statusVariant(
    status: AccountHistoryItem['status'],
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'done':
            return 'secondary';
        case 'failed':
            return 'destructive';
        case 'processing':
        case 'queued':
        default:
            return 'outline';
    }
}

function statusLabel(status: AccountHistoryItem['status']): string {
    return t(`account.history.statuses.${status}`);
}

function urlDaPagina(page: number): string {
    return historyRoute.url({ query: { page } });
}
</script>

<template>
  <Head :title="t('account.history.title')" />

  <div class="space-y-6">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('account.history.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('account.history.description') }}
      </p>
    </header>

    <!-- Conta no Free: o historico e recurso dos planos pagos -->
    <div
      v-if="locked"
      data-test="historico-bloqueado"
      class="rounded-lg border border-border px-4 py-12 text-center"
    >
      <Lock
        class="mx-auto size-5 text-subtle"
        aria-hidden="true"
      />
      <p class="mt-3 text-sm font-medium">
        {{ t('account.history.locked.title') }}
      </p>
      <p class="mx-auto mt-1 max-w-md text-sm text-subtle">
        {{ t('account.history.locked.description') }}
      </p>
      <Button
        as-child
        class="mt-4"
      >
        <Link :href="upgradeUrl ?? '/pricing'">
          {{ t('account.history.locked.cta') }}
        </Link>
      </Button>
    </div>

    <template v-else>
      <!-- Preferencias do historico -->
      <div
        data-test="historico-preferencias"
        class="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-border p-4"
      >
        <div class="flex items-start gap-3">
          <Checkbox
            id="history-enabled"
            data-test="historico-toggle"
            :model-value="enabled"
            @update:model-value="
              (value) => updatePreference(value === true)
            "
          />
          <div>
            <Label
              for="history-enabled"
              class="font-medium"
            >
              {{ t('account.history.opt_in.label') }}
            </Label>
            <p class="mt-1 max-w-md text-sm text-subtle">
              {{ t('account.history.opt_in.hint') }}
            </p>
            <p class="mt-1 max-w-md text-xs text-subtle">
              {{
                t('account.history.retention', {
                  days: retentionDays,
                })
              }}
            </p>
          </div>
        </div>

        <Button
          variant="destructive"
          size="sm"
          data-test="historico-apagar"
          :disabled="!hasHistory"
          @click="isDeleteDialogOpen = true"
        >
          <Trash2
            class="size-3.5"
            aria-hidden="true"
          />
          {{ t('account.history.delete.action') }}
        </Button>
      </div>

      <!-- Estado com registros -->
      <div
        v-if="history?.data && history.data.length > 0"
        data-test="historico-tabela"
        class="overflow-hidden rounded-lg border border-border"
      >
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead
              class="border-b border-border bg-muted/40 text-xs text-subtle"
            >
              <tr>
                <th class="px-3 py-2 font-medium">
                  {{ t('account.history.table.date') }}
                </th>
                <th class="px-3 py-2 font-medium">
                  {{ t('account.history.table.tool') }}
                </th>
                <th class="px-3 py-2 font-medium">
                  {{ t('account.history.table.dialect') }}
                </th>
                <th class="px-3 py-2 font-medium">
                  {{ t('account.history.table.usage') }}
                </th>
                <th class="px-3 py-2 font-medium">
                  {{ t('account.history.table.cache') }}
                </th>
                <th class="px-3 py-2 font-medium">
                  {{ t('account.history.table.status') }}
                </th>
                <th class="px-3 py-2 text-right font-medium">
                  {{ t('account.history.table.actions') }}
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="row in history.data"
                :key="row.id"
                class="h-10 border-b border-border transition-colors last:border-b-0 hover:bg-muted/20"
                :data-test="`historico-linha-${row.id}`"
              >
                <td class="px-3 py-1 font-mono text-[13px]">
                  {{ row.createdAt }}
                </td>
                <td class="px-3 py-1 font-medium">
                  {{ row.tool }}
                </td>
                <td class="px-3 py-1">
                  <span
                    class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                  >
                    {{ row.dialect }}
                  </span>
                </td>
                <td class="px-3 py-1 font-mono text-[13px]">
                  {{ row.tokensUsed }} {{ row.quotaUnit }}
                </td>
                <td class="px-3 py-1">
                  <Badge
                    :variant="
                      row.cacheHit
                        ? 'secondary'
                        : 'outline'
                    "
                    class="text-xs"
                  >
                    {{
                      row.cacheHit
                        ? t(
                          'account.history.table.cache_hit',
                        )
                        : t(
                          'account.history.table.cache_miss',
                        )
                    }}
                  </Badge>
                </td>
                <td class="px-3 py-1">
                  <Badge :variant="statusVariant(row.status)">
                    {{ statusLabel(row.status) }}
                  </Badge>
                </td>
                <td class="px-3 py-1 text-right">
                  <Button
                    v-if="
                      row.sql ||
                        row.summary ||
                        row.errorCode
                    "
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1 px-2 text-xs"
                    :data-test="`detalhes-btn-${row.id}`"
                    @click="openDetails(row)"
                  >
                    <Eye
                      class="size-3.5"
                      aria-hidden="true"
                    />
                    <span>{{
                      t(
                        'account.history.table.view_details',
                      )
                    }}</span>
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Rodape com paginacao -->
        <div
          v-if="history"
          class="flex items-center justify-between border-t border-border px-3 py-2 text-sm text-subtle"
        >
          <span>
            {{
              t('account.history.total', {
                total: history.total,
              })
            }}
          </span>

          <div class="flex items-center gap-3">
            <span>
              {{
                t('account.history.page_of', {
                  page: history.current_page,
                  last: history.last_page,
                })
              }}
            </span>

            <div class="flex items-center gap-1">
              <Button
                as-child
                variant="outline"
                size="sm"
                :disabled="history.current_page <= 1"
              >
                <Link
                  v-if="history.current_page > 1"
                  :href="
                    urlDaPagina(history.current_page - 1)
                  "
                  preserve-scroll
                >
                  {{ t('account.history.previous') }}
                </Link>
                <span v-else>{{
                  t('account.history.previous')
                }}</span>
              </Button>

              <Button
                as-child
                variant="outline"
                size="sm"
                :disabled="
                  history.current_page >= history.last_page
                "
              >
                <Link
                  v-if="
                    history.current_page < history.last_page
                  "
                  :href="
                    urlDaPagina(history.current_page + 1)
                  "
                  preserve-scroll
                >
                  {{ t('account.history.next') }}
                </Link>
                <span v-else>{{
                  t('account.history.next')
                }}</span>
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Estado vazio -->
      <div
        v-else
        class="rounded-lg border border-border px-4 py-12 text-center"
        data-test="historico-vazio"
      >
        <History
          class="mx-auto size-5 text-subtle"
          aria-hidden="true"
        />
        <p class="mt-3 text-sm font-medium">
          {{ t('account.history.empty_title') }}
        </p>
        <p class="mx-auto mt-1 max-w-md text-sm text-subtle">
          {{ t('account.history.empty') }}
        </p>
      </div>
    </template>

    <!-- Modal de Detalhes da Consulta -->
    <Dialog v-model:open="isDialogOpen">
      <DialogContent
        class="max-w-2xl"
        data-test="historico-modal-detalhes"
      >
        <DialogHeader>
          <DialogTitle>
            {{
              t('account.history.modal.title')
            }}
          </DialogTitle>
          <DialogDescription>
            {{ t('account.history.modal.description') }}
          </DialogDescription>
        </DialogHeader>

        <div
          v-if="selectedItem"
          class="space-y-4 py-2"
        >
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="font-medium text-foreground">{{
              selectedItem.tool
            }}</span>
            <span>•</span>
            <span
              class="rounded bg-muted px-1.5 py-0.5 font-mono"
            >{{ selectedItem.dialect }}</span>
            <span>•</span>
            <span class="font-mono">{{
              selectedItem.createdAt
            }}</span>
            <span>•</span>
            <Badge :variant="statusVariant(selectedItem.status)">
              {{ statusLabel(selectedItem.status) }}
            </Badge>
          </div>

          <!-- Tokens utilizados -->
          <div
            v-if="
              selectedItem.tokensIn !== null ||
                selectedItem.tokensOut !== null
            "
            class="flex gap-4 rounded-md border border-border bg-muted/20 p-2.5 font-mono text-xs"
          >
            <div v-if="selectedItem.tokensIn !== null">
              <span class="text-subtle">{{
                t('account.history.modal.tokens_in')
              }}:</span>
              <span class="ml-1 font-medium">{{
                selectedItem.tokensIn
              }}</span>
            </div>
            <div v-if="selectedItem.tokensOut !== null">
              <span class="text-subtle">{{
                t('account.history.modal.tokens_out')
              }}:</span>
              <span class="ml-1 font-medium">{{
                selectedItem.tokensOut
              }}</span>
            </div>
          </div>

          <!-- Erro (se houver) -->
          <div
            v-if="selectedItem.errorCode"
            class="rounded-md border border-destructive/20 bg-destructive/10 p-3 text-sm text-destructive"
          >
            <span class="font-medium">{{ t('account.history.modal.error') }}:</span>
            <span class="ml-1 font-mono">{{
              selectedItem.errorCode
            }}</span>
          </div>

          <!-- Resumo / Explicação -->
          <div
            v-if="selectedItem.summary"
            class="space-y-1.5"
          >
            <label class="text-xs font-medium text-subtle">
              {{ t('account.history.modal.summary') }}
            </label>
            <p
              class="rounded-md border border-border bg-muted/20 p-3 text-sm leading-relaxed text-foreground"
            >
              {{ selectedItem.summary }}
            </p>
          </div>

          <!-- SQL Gerado -->
          <div
            v-if="selectedItem.sql"
            class="space-y-1.5"
          >
            <div class="flex items-center justify-between">
              <label class="text-xs font-medium text-subtle">
                {{ t('account.history.modal.sql') }}
              </label>
              <Button
                variant="outline"
                size="sm"
                class="h-6 gap-1 px-2 text-xs"
                @click="copySql"
              >
                <Check
                  v-if="copied"
                  class="size-3 text-green-500"
                />
                <Copy
                  v-else
                  class="size-3"
                />
                <span>{{
                  copied
                    ? t('account.history.modal.copied')
                    : t('account.history.modal.copy_sql')
                }}</span>
              </Button>
            </div>
            <pre
              class="max-h-60 overflow-x-auto rounded-md border border-border bg-muted/40 p-3 font-mono text-xs leading-relaxed text-foreground"
            >{{ selectedItem.sql }}</pre>
          </div>
        </div>

        <DialogFooter>
          <DialogClose as-child>
            <Button
              variant="secondary"
              size="sm"
            >
              {{ t('account.history.modal.close') }}
            </Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Confirmacao de exclusao do historico -->
    <Dialog v-model:open="isDeleteDialogOpen">
      <DialogContent data-test="historico-modal-apagar">
        <DialogHeader>
          <DialogTitle>
            {{
              t('account.history.delete.title')
            }}
          </DialogTitle>
          <DialogDescription>
            {{ t('account.history.delete.description') }}
          </DialogDescription>
        </DialogHeader>

        <DialogFooter>
          <DialogClose as-child>
            <Button
              variant="secondary"
              size="sm"
            >
              {{ t('account.history.delete.cancel') }}
            </Button>
          </DialogClose>
          <Button
            variant="destructive"
            size="sm"
            data-test="historico-apagar-confirmar"
            :disabled="isDeleting"
            @click="deleteHistory"
          >
            {{ t('account.history.delete.confirm') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
