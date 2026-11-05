<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeBadge } from '@/routes/badgeforge/badges';
import type { BadgeFormState } from './builder';
import {
    findDefinition,
    findProvider,
    invalidProviderParam,
    type ProviderFormState,
} from './lib/providerUrl';
import type {
    BadgeForgeToolProps,
    BadgeMode,
} from './types';

/**
 * Criacao de badge pessoal (F4, ADR-081).
 *
 * So plano pago cria; free ve o caminho de upgrade. O formulario parte do que
 * esta no construtor (estatico ou provider), sem fetch na criacao: o provider
 * so e consultado no primeiro GET publico. O PAT e write-only - nunca vem
 * preenchido e e limpo ao fechar ou concluir.
 */

const props = defineProps<{
    open: boolean;
    toolProps: BadgeForgeToolProps;
    mode: BadgeMode;
    builderForm: BadgeFormState;
    providerForm: ProviderFormState;
    upgradeUrl: string;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t, te } = useI18n();

const form = useForm({
    name: '',
    private: false,
    credential: '',
    credential_username: '',
});

const isProvider = (): boolean => props.mode === 'provider';

/**
 * Erros de campo que o servidor devolve fora do shape do formulario
 * (`params`, `provider_slug`); o `useForm` nao os conhece de antemao.
 */
const fieldError = (key: string): string | undefined =>
    (form.errors as Record<string, string | undefined>)[key];

const provider = ref(findProvider(props.toolProps, props.providerForm.provider));
const definition = ref(findDefinition(provider.value, props.providerForm.definition));

watch(
    () => [props.providerForm.provider, props.providerForm.definition],
    () => {
        provider.value = findProvider(
            props.toolProps,
            props.providerForm.provider,
        );
        definition.value = findDefinition(
            provider.value,
            props.providerForm.definition,
        );
    },
);

const supportsPrivate = (): boolean => definition.value?.supportsPrivate === true;

const requiresUsername = (): boolean =>
    provider.value?.requiresUsername === true;

const paramsValid = (): boolean => {
    if (!isProvider()) {
        return true;
    }

    return (
        definition.value !== undefined &&
        invalidProviderParam(
            definition.value,
            props.providerForm.params,
            props.toolProps.limits.providers.maxParamLength,
        ) === null
    );
};

function clearSecret(): void {
    form.credential = '';
    form.credential_username = '';
    form.private = false;
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            clearSecret();
            form.clearErrors();
        }
    },
);

function paramsPayload(definitionParams: string[]): Record<string, string> {
    const payload: Record<string, string> = {};

    for (const name of definitionParams) {
        payload[name] = (props.providerForm.params[name] ?? '').trim();
    }

    return payload;
}

/** Snapshot cosmetico do construtor; no provider o label/message vem do dado. */
function cosmeticPayload(): Record<string, unknown> {
    const cosmetics = isProvider() ? props.providerForm : props.builderForm;

    return {
        label: isProvider() ? '' : props.builderForm.label,
        message: isProvider() ? '' : props.builderForm.message,
        style: cosmetics.style,
        font: cosmetics.font,
        color: cosmetics.color,
        labelColor: cosmetics.labelColor,
        logo: cosmetics.logo,
        logoColor: cosmetics.logoColor,
        logoSize: cosmetics.logoSize,
        logoPosition: cosmetics.logoPosition,
        link: cosmetics.link,
        cacheSeconds: isProvider() ? '' : props.builderForm.cacheSeconds,
    };
}

/** Nome traduzido da metrica; o nome do catalogo e so fallback. */
function metricLabel(): string {
    const current = definition.value;

    if (current === undefined) {
        return '';
    }

    const key = `tools.badgeforge.provider.metrics.${props.providerForm.provider}_${current.slug}`;

    return te(key) ? t(key) : current.name;
}

function submit(): void {
    if (form.processing || !paramsValid()) {
        return;
    }

    const currentDefinition = definition.value;
    const currentProvider = provider.value;

    const privateItem = supportsPrivate() && form.private;

    form.transform(() => {
        if (isProvider() && currentDefinition !== undefined && currentProvider !== undefined) {
            return {
                name: form.name,
                type: 'provider' as const,
                provider_slug: currentProvider.slug,
                definition_slug: currentDefinition.slug,
                params: paramsPayload(
                    currentDefinition.params.map((param) => param.name),
                ),
                private: privateItem,
                credential: privateItem ? form.credential : null,
                credential_username:
                    privateItem && requiresUsername()
                        ? form.credential_username
                        : null,
                form: cosmeticPayload(),
            };
        }

        return {
            name: form.name,
            type: 'static' as const,
            form: cosmeticPayload(),
        };
    })
        .post(storeBadge.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                form.reset();
                clearSecret();
                emit('update:open', false);
            },
        });
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="(value: boolean) => emit('update:open', value)"
  >
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>
          {{ t('tools.badgeforge.personal.dialog_title') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('tools.badgeforge.personal.dialog_description') }}
        </DialogDescription>
      </DialogHeader>

      <div
        v-if="!toolProps.management.canCreatePersonalBadges"
        class="space-y-3 rounded-md border border-dashed p-4 text-sm"
        data-test="badge-personal-upgrade"
      >
        <p>
          {{ t('tools.badgeforge.personal.upgrade_cta') }}
        </p>
        <Button
          as-child
          size="sm"
        >
          <a :href="upgradeUrl">
            {{ t('tools.badgeforge.personal.upgrade') }}
          </a>
        </Button>
      </div>

      <form
        v-else
        class="space-y-4"
        data-test="badge-personal-form"
        @submit.prevent="submit"
      >
        <div class="space-y-2">
          <Label for="badge-personal-name">
            {{ t('tools.badgeforge.personal.name') }}
          </Label>
          <Input
            id="badge-personal-name"
            v-model="form.name"
            :maxlength="toolProps.limits.personalBadges.nameMaxLength"
            :placeholder="t('tools.badgeforge.personal.name_placeholder')"
            data-test="badge-personal-name"
          />
          <InputError :message="form.errors.name" />
        </div>

        <p
          v-if="isProvider() && definition !== undefined"
          class="text-xs text-muted-foreground"
          data-test="badge-personal-metric"
        >
          {{
            t('tools.badgeforge.personal.metric_note', {
              metric: metricLabel(),
            })
          }}
        </p>

        <div
          v-if="supportsPrivate()"
          class="space-y-3 rounded-md border p-3"
          data-test="badge-personal-private"
        >
          <Label
            for="badge-personal-private-check"
            class="flex items-center gap-2"
          >
            <Checkbox
              id="badge-personal-private-check"
              v-model="form.private"
              data-test="badge-personal-private-check"
            />
            {{ t('tools.badgeforge.personal.private') }}
          </Label>
          <p class="text-xs text-muted-foreground">
            {{ t('tools.badgeforge.personal.private_hint') }}
          </p>

          <div
            v-if="form.private"
            class="space-y-2"
          >
            <Label for="badge-personal-credential">
              {{ t('tools.badgeforge.personal.credential') }}
            </Label>
            <Input
              id="badge-personal-credential"
              v-model="form.credential"
              type="password"
              autocomplete="new-password"
              :placeholder="
                t('tools.badgeforge.personal.credential_placeholder')
              "
              data-test="badge-personal-credential"
            />
            <InputError :message="form.errors.credential" />

            <div
              v-if="requiresUsername()"
              class="space-y-2"
            >
              <Label for="badge-personal-credential-username">
                {{ t('tools.badgeforge.personal.credential_username') }}
              </Label>
              <Input
                id="badge-personal-credential-username"
                v-model="form.credential_username"
                autocomplete="off"
                :placeholder="
                  t(
                    'tools.badgeforge.personal.credential_username_placeholder',
                  )
                "
                data-test="badge-personal-credential-username"
              />
              <InputError :message="form.errors.credential_username" />
            </div>
          </div>
        </div>

        <InputError :message="fieldError('params')" />
        <InputError :message="fieldError('provider_slug')" />
        <InputError :message="form.errors.private" />

        <p
          v-if="!paramsValid()"
          class="text-xs text-destructive"
          data-test="badge-personal-invalid"
        >
          {{ t('tools.badgeforge.personal.params_invalid') }}
        </p>

        <div class="flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            type="button"
            data-test="badge-personal-cancel"
            @click="emit('update:open', false)"
          >
            {{ t('tools.badgeforge.management.cancel') }}
          </Button>
          <Button
            size="sm"
            type="submit"
            :disabled="form.processing || !paramsValid()"
            data-test="badge-personal-submit"
          >
            {{
              form.processing
                ? t('tools.badgeforge.personal.creating')
                : t('tools.badgeforge.personal.create')
            }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
