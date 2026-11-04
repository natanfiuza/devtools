<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Clock,
    Copy,
    ExternalLink,
    KeyRound,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHead from '@/components/PageHead.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useRoutes } from '@/composables/useRoutes';
import { markdownSnippet } from '@/tools/badgeforge/lib/snippets';

/**
 * Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
 *
 * A pagina e o contrato lido: o catalogo de providers vem do servidor (os
 * mesmos metadados que alimentam o builder) e os exemplos sao montados aqui a
 * partir do endpoint e do `examplePath` de cada definicao. Nenhuma credencial
 * ou asset de conta chega nestas props.
 */

export type BadgeForgeDocsParam = {
    name: string;
    required: boolean;
    pattern: string;
    allowSlash: boolean;
    placeholderKey: string;
};

export type BadgeForgeDocsDefinition = {
    slug: string;
    name: string;
    label: string;
    supportsPrivate: boolean;
    examplePath: string;
    pathPattern: string;
    params: BadgeForgeDocsParam[];
};

export type BadgeForgeDocsProvider = {
    slug: string;
    name: string;
    category: string;
    requiresAuth: boolean;
    requiresUsername: boolean;
    documentationUrl: string | null;
    definitions: BadgeForgeDocsDefinition[];
};

export type BadgeForgeDocsEndpointRateLimits = {
    anonymous: number;
    free: number;
    paid: number;
};

export type BadgeForgeDocsProps = {
    providers: BadgeForgeDocsProvider[];
    staticEndpoint: string;
    providerEndpoint: string;
    personalEndpoint: string;
    jsonEndpoint: string;
    cacheTtl: number;
    endpointCacheTtl: number;
    endpointRateLimits: BadgeForgeDocsEndpointRateLimits;
    origin: string;
};

const props = defineProps<BadgeForgeDocsProps>();

const { t, te } = useI18n();
const routes = useRoutes();

const { message: copyMessage, copy } = useCopyFeedback();

const copiado = ref<string | null>(null);

const trimSlash = (value: string): string => value.replace(/\/+$/, '');

const origin = computed(() => trimSlash(props.origin));
const staticEndpoint = computed(() => trimSlash(props.staticEndpoint));
const providerEndpoint = computed(() => trimSlash(props.providerEndpoint));
const personalEndpoint = computed(() => trimSlash(props.personalEndpoint));
const jsonEndpoint = computed(() => trimSlash(props.jsonEndpoint));

const toolUrl = computed(() => `/${routes.localeSlug.value}/tools/badgeforge`);
const dashboardUrl = computed(
    () => `/${routes.localeSlug.value}/dashboard/badgeforge`,
);

const staticExample = computed(
    () => `${origin.value}${staticEndpoint.value}/build/passing.svg`,
);

const staticMarkdown = computed(() =>
    markdownSnippet(staticExample.value, 'build: passing'),
);

const personalUrlFormat = computed(
    () => `${origin.value}${personalEndpoint.value}/{username}/{ulid}.svg`,
);

const jsonUrlFormat = computed(
    () => `${origin.value}${jsonEndpoint.value}/{ulid}.svg`,
);

const jsonContract = `{
  "schemaVersion": 1,
  "label": "deploys",
  "message": "green",
  "color": "brightgreen",
  "labelColor": "blue"
}`;

function metricLabel(
    providerSlug: string,
    definition: BadgeForgeDocsDefinition,
): string {
    const key = `tools.badgeforge.provider.metrics.${providerSlug}_${definition.slug}`;

    return te(key) ? t(key) : definition.name;
}

function providerDescription(slug: string): string {
    const key = `docs.badgeforge.providers.descriptions.${slug}`;

    return te(key) ? t(key) : '';
}

function providerPath(
    providerSlug: string,
    definition: BadgeForgeDocsDefinition,
): string {
    return `${providerEndpoint.value}/${providerSlug}/${definition.slug}/${definition.examplePath}.svg`;
}

function providerUrl(
    providerSlug: string,
    definition: BadgeForgeDocsDefinition,
): string {
    return `${origin.value}${providerPath(providerSlug, definition)}`;
}

async function copyValue(value: string): Promise<void> {
    await copy(value, t('docs.badgeforge.quick_start.copied'));
    copiado.value = value;

    window.setTimeout(() => {
        if (copiado.value === value) {
            copiado.value = null;
        }
    }, 2500);
}
</script>

<template>
  <PageHead />

  <div class="mx-auto w-full max-w-[1120px] space-y-14 px-4 py-12">
    <!-- Hero -->
    <section
      class="space-y-6"
      data-test="badgeforge-docs-hero"
    >
      <div class="space-y-3">
        <p class="font-mono text-xs tracking-wider text-accent uppercase">
          {{ t('docs.badgeforge.hero.eyebrow') }}
        </p>
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
          {{ t('docs.badgeforge.hero.title') }}
        </h1>
        <p class="max-w-3xl text-base leading-relaxed text-muted-foreground">
          {{ t('docs.badgeforge.hero.subtitle') }}
        </p>
      </div>

      <Button
        as-child
        data-test="badgeforge-docs-open-tool"
      >
        <Link :href="toolUrl">
          {{ t('docs.badgeforge.hero.open_tool') }}
          <ArrowRight class="size-4" />
        </Link>
      </Button>
    </section>

    <!-- Endpoints -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold tracking-tight">
        {{ t('docs.badgeforge.endpoints.title') }}
      </h2>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
          <p class="text-xs text-muted-foreground">
            {{ t('docs.badgeforge.endpoints.static') }}
          </p>
          <code
            class="block font-mono text-xs break-all"
            data-test="badgeforge-docs-endpoint-static"
          >{{ staticEndpoint }}/…</code>
        </div>
        <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
          <p class="text-xs text-muted-foreground">
            {{ t('docs.badgeforge.endpoints.provider') }}
          </p>
          <code
            class="block font-mono text-xs break-all"
            data-test="badgeforge-docs-endpoint-provider"
          >{{ providerEndpoint }}/{provider}/{metric}/…</code>
        </div>
        <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
          <p class="text-xs text-muted-foreground">
            {{ t('docs.badgeforge.endpoints.personal') }}
          </p>
          <code
            class="block font-mono text-xs break-all"
            data-test="badgeforge-docs-endpoint-personal"
          >{{ personalEndpoint }}/{username}/{ulid}.svg</code>
        </div>
        <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
          <p class="text-xs text-muted-foreground">
            {{ t('docs.badgeforge.endpoints.json') }}
          </p>
          <code
            class="block font-mono text-xs break-all"
            data-test="badgeforge-docs-endpoint-json"
          >{{ jsonEndpoint }}/{ulid}.svg</code>
        </div>
      </div>
    </section>

    <!-- Quick start -->
    <section
      class="space-y-4"
      data-test="badgeforge-docs-quick-start"
    >
      <h2 class="text-xl font-semibold tracking-tight">
        {{ t('docs.badgeforge.quick_start.title') }}
      </h2>
      <p class="max-w-3xl text-sm text-muted-foreground">
        {{ t('docs.badgeforge.quick_start.body') }}
      </p>

      <div class="space-y-3 rounded-lg border border-border bg-surface p-4">
        <img
          :src="staticExample"
          alt="build: passing"
          class="h-5"
        >
        <pre
          class="overflow-x-auto rounded-md bg-muted p-3 text-xs break-all whitespace-pre-wrap"
          data-test="badgeforge-docs-static-markdown"
        >{{ staticMarkdown }}</pre>
        <div class="flex items-center gap-3">
          <Button
            variant="outline"
            size="sm"
            data-test="badgeforge-docs-copy-static"
            @click="copyValue(staticMarkdown)"
          >
            <Check
              v-if="copiado === staticMarkdown"
              class="size-4"
            />
            <Copy
              v-else
              class="size-4"
            />
            {{
              copiado === staticMarkdown
                ? t('docs.badgeforge.quick_start.copied')
                : t('docs.badgeforge.quick_start.copy')
            }}
          </Button>
          <span
            v-if="copyMessage"
            class="text-xs text-muted-foreground"
          >{{ copyMessage }}</span>
        </div>
      </div>
    </section>

    <!-- Static reference -->
    <section class="space-y-3">
      <h2 class="text-xl font-semibold tracking-tight">
        {{ t('docs.badgeforge.static.title') }}
      </h2>
      <p class="max-w-3xl text-sm text-muted-foreground">
        {{ t('docs.badgeforge.static.body') }}
      </p>
      <div class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-surface p-4">
        <span class="text-xs text-muted-foreground">
          {{ t('docs.badgeforge.static.example_label') }}
        </span>
        <code
          class="font-mono text-xs break-all"
          data-test="badgeforge-docs-static-example"
        >{{ staticExample }}</code>
      </div>
    </section>

    <!-- Providers -->
    <section class="space-y-6">
      <div class="space-y-2">
        <h2 class="text-xl font-semibold tracking-tight">
          {{ t('docs.badgeforge.providers.title') }}
        </h2>
        <p class="max-w-3xl text-sm text-muted-foreground">
          {{ t('docs.badgeforge.providers.intro') }}
        </p>
      </div>

      <div class="space-y-6">
        <article
          v-for="provider in providers"
          :key="provider.slug"
          class="space-y-4 rounded-lg border border-border bg-surface p-5"
          :data-test="`badgeforge-docs-provider-${provider.slug}`"
        >
          <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
              <h3 class="text-base font-semibold">
                {{ provider.name }}
              </h3>
              <p
                v-if="providerDescription(provider.slug) !== ''"
                class="max-w-2xl text-sm text-muted-foreground"
              >
                {{ providerDescription(provider.slug) }}
              </p>
            </div>
            <a
              v-if="provider.documentationUrl"
              :href="provider.documentationUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
              {{ t('docs.badgeforge.providers.documentation') }}
              <ExternalLink class="size-3" />
            </a>
          </header>

          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
              <thead>
                <tr class="border-b border-border text-xs text-muted-foreground">
                  <th class="py-2 pr-4 font-medium">
                    {{ t('docs.badgeforge.providers.metric') }}
                  </th>
                  <th class="py-2 pr-4 font-medium">
                    {{ t('docs.badgeforge.providers.path') }}
                  </th>
                  <th class="py-2 font-medium">
                    {{ t('docs.badgeforge.providers.access') }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="definition in provider.definitions"
                  :key="definition.slug"
                  class="border-b border-border/60 last:border-0"
                  :data-test="`badgeforge-docs-metric-${provider.slug}-${definition.slug}`"
                >
                  <td class="py-2 pr-4 align-top">
                    {{ metricLabel(provider.slug, definition) }}
                  </td>
                  <td class="py-2 pr-4 align-top">
                    <div class="flex flex-wrap items-center gap-2">
                      <code class="font-mono text-xs break-all">{{ providerPath(provider.slug, definition) }}</code>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="h-6 px-2"
                        :data-test="`badgeforge-docs-copy-${provider.slug}-${definition.slug}`"
                        @click="copyValue(providerUrl(provider.slug, definition))"
                      >
                        <Check
                          v-if="copiado === providerUrl(provider.slug, definition)"
                          class="size-3"
                        />
                        <Copy
                          v-else
                          class="size-3"
                        />
                      </Button>
                    </div>
                  </td>
                  <td class="py-2 align-top">
                    <Badge
                      v-if="definition.supportsPrivate"
                      variant="outline"
                    >
                      <KeyRound class="size-3" />
                      {{ t('docs.badgeforge.providers.private') }}
                    </Badge>
                    <Badge
                      v-else
                      variant="secondary"
                    >
                      {{ t('docs.badgeforge.providers.public') }}
                    </Badge>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
      </div>

      <p class="text-xs text-muted-foreground">
        {{ t('docs.badgeforge.providers.unknown_note') }}
      </p>
    </section>

    <!-- Personal badges -->
    <section
      class="space-y-4"
      data-test="badgeforge-docs-personal"
    >
      <h2 class="text-xl font-semibold tracking-tight">
        {{ t('docs.badgeforge.personal.title') }}
      </h2>
      <p class="max-w-3xl text-sm text-muted-foreground">
        {{ t('docs.badgeforge.personal.intro') }}
      </p>

      <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
        <p class="text-xs text-muted-foreground">
          {{ t('docs.badgeforge.personal.url_label') }}
        </p>
        <code
          class="block font-mono text-xs break-all"
          data-test="badgeforge-docs-personal-url"
        >{{ personalUrlFormat }}</code>
      </div>

      <div class="space-y-2 rounded-lg border border-border bg-surface p-4">
        <h3 class="text-sm font-semibold">
          {{ t('docs.badgeforge.personal.providers_title') }}
        </h3>
        <p class="text-sm text-muted-foreground">
          {{ t('docs.badgeforge.personal.providers_body') }}
        </p>
        <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
          <li>{{ t('docs.badgeforge.personal.github') }}</li>
          <li>{{ t('docs.badgeforge.personal.npm') }}</li>
          <li>{{ t('docs.badgeforge.personal.docker') }}</li>
          <li>{{ t('docs.badgeforge.personal.packagist') }}</li>
        </ul>
        <p class="text-xs text-muted-foreground">
          {{ t('docs.badgeforge.personal.username_required') }}
        </p>
      </div>

      <div class="space-y-2 rounded-lg border border-border bg-surface p-4">
        <h3 class="inline-flex items-center gap-2 text-sm font-semibold">
          <ShieldCheck class="size-4 text-accent" />
          {{ t('docs.badgeforge.personal.security_title') }}
        </h3>
        <p class="text-sm text-muted-foreground">
          {{ t('docs.badgeforge.personal.security_body') }}
        </p>
        <p class="text-xs text-muted-foreground">
          {{ t('docs.badgeforge.personal.refresh_note') }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Button
          as-child
          data-test="badgeforge-docs-manage"
        >
          <Link :href="dashboardUrl">
            {{ t('docs.badgeforge.personal.manage_cta') }}
            <ArrowRight class="size-4" />
          </Link>
        </Button>
        <Button
          as-child
          variant="outline"
          data-test="badgeforge-docs-create-personal"
        >
          <Link :href="toolUrl">
            {{ t('docs.badgeforge.personal.tool_cta') }}
          </Link>
        </Button>
      </div>
    </section>

    <!-- JSON endpoint -->
    <section
      class="space-y-4"
      data-test="badgeforge-docs-json-endpoint"
    >
      <h2 class="text-xl font-semibold tracking-tight">
        {{ t('docs.badgeforge.endpoint.title') }}
      </h2>
      <p class="max-w-3xl text-sm text-muted-foreground">
        {{ t('docs.badgeforge.endpoint.intro') }}
      </p>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
          <p class="text-xs text-muted-foreground">
            {{ t('docs.badgeforge.endpoint.url_label') }}
          </p>
          <code
            class="block font-mono text-xs break-all"
            data-test="badgeforge-docs-json-url"
          >{{ jsonUrlFormat }}</code>
        </div>

        <div class="space-y-2 rounded-lg border border-border bg-surface p-4">
          <h3 class="text-sm font-semibold">
            {{ t('docs.badgeforge.endpoint.contract_title') }}
          </h3>
          <p class="text-sm text-muted-foreground">
            {{ t('docs.badgeforge.endpoint.contract_body') }}
          </p>
          <pre
            class="overflow-x-auto rounded-md bg-muted p-3 text-xs break-all whitespace-pre-wrap"
            data-test="badgeforge-docs-json-contract"
          >{{ jsonContract }}</pre>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2 rounded-lg border border-border bg-surface p-4">
          <h3 class="inline-flex items-center gap-2 text-sm font-semibold">
            <Clock class="size-4 text-accent" />
            {{ t('docs.badgeforge.endpoint.cache_title') }}
          </h3>
          <p
            class="text-sm text-muted-foreground"
            data-test="badgeforge-docs-json-cache"
          >
            {{ t('docs.badgeforge.endpoint.cache_body', { ttl: endpointCacheTtl }) }}
          </p>
        </div>

        <div class="space-y-2 rounded-lg border border-border bg-surface p-4">
          <h3 class="text-sm font-semibold">
            {{ t('docs.badgeforge.endpoint.limits_title') }}
          </h3>
          <ul
            class="list-disc space-y-1 pl-5 text-sm text-muted-foreground"
            data-test="badgeforge-docs-json-limits"
          >
            <li>{{ t('docs.badgeforge.endpoint.limits_anonymous', { limit: endpointRateLimits.anonymous }) }}</li>
            <li>{{ t('docs.badgeforge.endpoint.limits_free', { limit: endpointRateLimits.free }) }}</li>
            <li>{{ t('docs.badgeforge.endpoint.limits_paid', { limit: endpointRateLimits.paid }) }}</li>
          </ul>
        </div>
      </div>

      <div class="space-y-2 rounded-lg border border-border bg-surface p-4">
        <h3 class="inline-flex items-center gap-2 text-sm font-semibold">
          <ShieldCheck class="size-4 text-accent" />
          {{ t('docs.badgeforge.endpoint.security_title') }}
        </h3>
        <p class="text-sm text-muted-foreground">
          {{ t('docs.badgeforge.endpoint.security_body') }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Button
          as-child
          data-test="badgeforge-docs-json-manage"
        >
          <Link :href="dashboardUrl">
            {{ t('docs.badgeforge.endpoint.manage_cta') }}
            <ArrowRight class="size-4" />
          </Link>
        </Button>
      </div>
    </section>

    <!-- Cache -->
    <section
      class="space-y-4"
      data-test="badgeforge-docs-cache"
    >
      <h2 class="inline-flex items-center gap-2 text-xl font-semibold tracking-tight">
        <Clock class="size-5 text-accent" />
        {{ t('docs.badgeforge.cache.title') }}
      </h2>
      <p
        class="max-w-3xl text-sm text-muted-foreground"
        data-test="badgeforge-docs-cache-ttl"
      >
        {{ t('docs.badgeforge.cache.body', { ttl: cacheTtl }) }}
      </p>

      <div class="grid gap-4 sm:grid-cols-3">
        <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
          <h3 class="text-sm font-semibold">
            {{ t('docs.badgeforge.cache.stale_title') }}
          </h3>
          <p class="text-sm text-muted-foreground">
            {{ t('docs.badgeforge.cache.stale_body') }}
          </p>
        </div>
        <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
          <h3 class="text-sm font-semibold">
            {{ t('docs.badgeforge.cache.refresh_title') }}
          </h3>
          <p class="text-sm text-muted-foreground">
            {{ t('docs.badgeforge.cache.refresh_body') }}
          </p>
        </div>
        <div class="space-y-1 rounded-lg border border-border bg-surface p-4">
          <h3 class="text-sm font-semibold">
            {{ t('docs.badgeforge.errors.title') }}
          </h3>
          <p class="text-sm text-muted-foreground">
            {{ t('docs.badgeforge.errors.intro') }}
          </p>
        </div>
      </div>
    </section>

    <!-- Errors and security -->
    <section class="grid gap-4 sm:grid-cols-2">
      <div
        class="space-y-2 rounded-lg border border-border bg-surface p-5"
        data-test="badgeforge-docs-errors"
      >
        <h2 class="text-base font-semibold">
          {{ t('docs.badgeforge.errors.title') }}
        </h2>
        <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
          <li>{{ t('docs.badgeforge.errors.cached') }}</li>
          <li>{{ t('docs.badgeforge.errors.fallback') }}</li>
          <li>{{ t('docs.badgeforge.errors.unknown') }}</li>
          <li>{{ t('docs.badgeforge.errors.not_found') }}</li>
          <li>{{ t('docs.badgeforge.errors.private') }}</li>
        </ul>
      </div>

      <div
        class="space-y-2 rounded-lg border border-border bg-surface p-5"
        data-test="badgeforge-docs-security"
      >
        <h2 class="inline-flex items-center gap-2 text-base font-semibold">
          <ShieldCheck class="size-4 text-accent" />
          {{ t('docs.badgeforge.security.title') }}
        </h2>
        <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
          <li>{{ t('docs.badgeforge.security.anonymous') }}</li>
          <li>{{ t('docs.badgeforge.security.credential') }}</li>
          <li>{{ t('docs.badgeforge.security.logs') }}</li>
          <li>{{ t('docs.badgeforge.security.url') }}</li>
        </ul>
      </div>
    </section>
  </div>
</template>
