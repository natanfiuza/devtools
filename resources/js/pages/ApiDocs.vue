<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, Copy, Download, KeyRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHead from '@/components/PageHead.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type ApiDocTarget = {
    slug: string;
    label: string;
    language: string;
    supportedDialects: string[];
};

export type ApiDocsProps = {
    baseUrl: string;
    openApiUrl: string;
    tokensSettingsUrl: string;
    rateLimits: {
        postPerMinute: number;
        postPerHour: number;
        readPerMinute: number;
    };
    paginationPerPage: number;
    targets: ApiDocTarget[];
};

const props = defineProps<ApiDocsProps>();

const { t } = useI18n();

const copiedBaseUrl = ref(false);
const copiedCode = ref(false);

type ExampleTab = 'curl' | 'js' | 'php' | 'python' | 'n8n';
const activeTab = ref<ExampleTab>('curl');

const tabs: ExampleTab[] = ['curl', 'js', 'php', 'python', 'n8n'];

const codeSnippets = computed<Record<ExampleTab, string>>(() => {
    const base = props.baseUrl;

    return {
        curl: `# 1. POST /sql-queries
curl -sS -X POST "${base}/sql-queries" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{
    "schema_source": "ddl",
    "dialect": "mysql",
    "output_target": "sql",
    "schema": "CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120), active TINYINT(1));",
    "description": "Select active users ordered by name"
  }'

# 2. Poll until data.poll_url is null (status: done | failed)
curl -sS "${base}/sql-queries/01JCX9Y8Z1Q2W3E4R5T6Y7U8I9" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Accept: application/json"`,

        js: `const BASE_URL = '${base}';
const TOKEN = process.env.DEVTOOLS_API_TOKEN;

const headers = {
  Authorization: \`Bearer \${TOKEN}\`,
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

// 1. Submit SQL query generation
const res = await fetch(\`\${BASE_URL}/sql-queries\`, {
  method: 'POST',
  headers,
  body: JSON.stringify({
    schema_source: 'ddl',
    dialect: 'mysql',
    output_target: 'sql',
    schema: 'CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120));',
    description: 'Select all users ordered by name',
  }),
});

let { data } = await res.json();

// 2. If 202 Accepted, poll data.poll_url until done or failed
while (data.poll_url) {
  await new Promise((r) => setTimeout(r, data.poll_after_ms ?? 1500));
  const pollRes = await fetch(data.poll_url, { headers });
  ({ data } = await pollRes.json());
}

console.log(data.status, data.result);`,

        php: `<?php

use Illuminate\\Support\\Facades\\Http;

$baseUrl = '${base}';
$token = config('services.devtools.token');

$response = Http::withToken($token)
    ->acceptJson()
    ->post("{$baseUrl}/sql-queries", [
        'schema_source' => 'ddl',
        'dialect' => 'mysql',
        'output_target' => 'sql',
        'schema' => 'CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120));',
        'description' => 'Select all users ordered by name',
    ])
    ->throw()
    ->json('data');

while (! empty($response['poll_url'])) {
    usleep((int) (($response['poll_after_ms'] ?? 1500) * 1000));

    $response = Http::withToken($token)
        ->acceptJson()
        ->get($response['poll_url'])
        ->throw()
        ->json('data');
}

// $response['result']['code']`,

        python: `import time
import requests

BASE_URL = "${base}"
HEADERS = {
    "Authorization": "Bearer YOUR_API_TOKEN",
    "Accept": "application/json",
    "Content-Type": "application/json",
}

resp = requests.post(
    f"{BASE_URL}/sql-queries",
    headers=HEADERS,
    json={
        "schema_source": "ddl",
        "dialect": "mysql",
        "output_target": "sql",
        "schema": "CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120));",
        "description": "Select all users ordered by name",
    },
    timeout=15,
)
resp.raise_for_status()
data = resp.json()["data"]

while data.get("poll_url"):
    time.sleep((data.get("poll_after_ms") or 1500) / 1000)
    poll = requests.get(data["poll_url"], headers=HEADERS, timeout=15)
    poll.raise_for_status()
    data = poll.json()["data"]

print(data["status"], data["result"])`,

        n8n: `{
  "method": "POST",
  "url": "${base}/sql-queries",
  "authentication": "genericCredentialType",
  "genericAuthType": "httpHeaderAuth",
  "sendHeaders": true,
  "headerParameters": {
    "parameters": [
      { "name": "Accept", "value": "application/json" }
    ]
  },
  "sendBody": true,
  "specifyBody": "json",
  "jsonBody": {
    "schema_source": "ddl",
    "dialect": "mysql",
    "output_target": "sql",
    "schema": "CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120));",
    "description": "Select all users ordered by name"
  }
}`,
    };
});

async function copyBaseUrl(): Promise<void> {
    await navigator.clipboard.writeText(props.baseUrl);
    copiedBaseUrl.value = true;
    setTimeout(() => {
        copiedBaseUrl.value = false;
    }, 2000);
}

async function copyCurrentSnippet(): Promise<void> {
    await navigator.clipboard.writeText(codeSnippets.value[activeTab.value]);
    copiedCode.value = true;
    setTimeout(() => {
        copiedCode.value = false;
    }, 2000);
}

const errorRows = [
    { status: '401', code: 'unauthenticated', key: '401' },
    { status: '402', code: 'quota_exceeded', key: '402' },
    { status: '403', code: 'forbidden / account_suspended', key: '403' },
    { status: '404', code: 'not_found', key: '404' },
    {
        status: '422',
        code: 'validation_error / schema_too_large / unsupported_dialect',
        key: '422',
    },
    { status: '429', code: 'rate_limited', key: '429' },
    { status: '503', code: 'ai_unavailable', key: '503' },
] as const;
</script>

<template>
  <PageHead />

  <div class="mx-auto w-full max-w-[1120px] space-y-12 px-4 py-12">
    <!-- Hero -->
    <section class="space-y-6">
      <div class="space-y-2">
        <p class="font-mono text-xs tracking-wider text-accent uppercase">
          {{ t('api_docs.hero.eyebrow') }}
        </p>
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
          {{ t('api_docs.hero.title') }}
        </h1>
        <p class="max-w-3xl text-base leading-relaxed text-muted-foreground">
          {{ t('api_docs.hero.subtitle') }}
        </p>
      </div>

      <div
        class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border bg-surface p-4"
      >
        <div class="min-w-0 flex-1 space-y-1">
          <p class="text-xs text-muted-foreground">
            {{ t('api_docs.hero.base_url_label') }}
          </p>
          <div class="flex flex-wrap items-center gap-2">
            <code
              data-test="api-docs-base-url"
              class="rounded border border-border bg-background px-3 py-1.5 font-mono text-xs sm:text-sm"
            >{{ baseUrl }}</code>
            <Button
              variant="outline"
              size="sm"
              data-test="api-docs-copy-base-url"
              @click="copyBaseUrl"
            >
              <Check
                v-if="copiedBaseUrl"
                class="size-3.5"
              />
              <Copy
                v-else
                class="size-3.5"
              />
              {{
                copiedBaseUrl
                  ? t('api_docs.hero.copied')
                  : t('api_docs.hero.copy_base_url')
              }}
            </Button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Button
            variant="default"
            size="sm"
            as-child
          >
            <Link
              :href="tokensSettingsUrl"
              data-test="api-docs-manage-tokens"
            >
              <KeyRound class="size-3.5" />
              {{ t('api_docs.hero.manage_tokens') }}
            </Link>
          </Button>
          <Button
            variant="outline"
            size="sm"
            as-child
          >
            <a
              :href="openApiUrl"
              target="_blank"
              rel="noopener"
              data-test="api-docs-openapi-link"
            >
              <Download class="size-3.5" />
              {{ t('api_docs.hero.openapi_spec') }}
            </a>
          </Button>
        </div>
      </div>
    </section>

    <!-- Autenticacao e Fluxo Assincrono -->
    <section
      data-test="api-docs-auth"
      class="space-y-4"
    >
      <div class="space-y-1">
        <h2 class="text-xl font-semibold">
          {{ t('api_docs.auth.title') }}
        </h2>
        <p class="text-sm text-muted-foreground">
          {{ t('api_docs.auth.description') }}
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-border p-4">
          <p class="text-sm font-medium">
            {{ t('api_docs.auth.step_1_title') }}
          </p>
          <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
            {{ t('api_docs.auth.step_1_body') }}
          </p>
        </div>
        <div class="rounded-lg border border-border p-4">
          <p class="text-sm font-medium">
            {{ t('api_docs.auth.step_2_title') }}
          </p>
          <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
            {{ t('api_docs.auth.step_2_body') }}
          </p>
        </div>
        <div class="rounded-lg border border-border p-4">
          <p class="text-sm font-medium">
            {{ t('api_docs.auth.step_3_title') }}
          </p>
          <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
            {{ t('api_docs.auth.step_3_body') }}
          </p>
        </div>
      </div>

      <div class="rounded-lg border border-border bg-surface p-4">
        <p class="text-xs font-medium text-muted-foreground">
          {{ t('api_docs.auth.headers_label') }}
        </p>
        <pre
          class="mt-2 overflow-x-auto font-mono text-xs leading-relaxed"
        >Authorization: Bearer &lt;token&gt;
Accept: application/json
Content-Type: application/json</pre>
      </div>
    </section>

    <!-- Exemplos de Integracao -->
    <section
      data-test="api-docs-examples"
      class="space-y-4"
    >
      <div class="space-y-1">
        <h2 class="text-xl font-semibold">
          {{ t('api_docs.examples.title') }}
        </h2>
        <p class="text-sm text-muted-foreground">
          {{ t('api_docs.examples.description') }}
        </p>
      </div>

      <div class="overflow-hidden rounded-lg border border-border">
        <div
          class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/30 px-3 py-2"
        >
          <div
            class="flex flex-wrap gap-1"
            role="tablist"
          >
            <button
              v-for="tab in tabs"
              :key="tab"
              type="button"
              role="tab"
              :aria-selected="activeTab === tab"
              :data-test="`api-docs-tab-${tab}`"
              class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
              :class="
                activeTab === tab
                  ? 'bg-background text-foreground shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              "
              @click="activeTab = tab"
            >
              {{ t(`api_docs.examples.tabs.${tab}`) }}
            </button>
          </div>

          <Button
            variant="ghost"
            size="sm"
            data-test="api-docs-copy-code"
            @click="copyCurrentSnippet"
          >
            <Check
              v-if="copiedCode"
              class="size-3.5"
            />
            <Copy
              v-else
              class="size-3.5"
            />
            {{
              copiedCode
                ? t('api_docs.examples.copied')
                : t('api_docs.examples.copy')
            }}
          </Button>
        </div>

        <pre
          data-test="api-docs-code-block"
          class="overflow-x-auto bg-background p-4 font-mono text-xs leading-relaxed"
        ><code>{{ codeSnippets[activeTab] }}</code></pre>

        <div
          v-if="activeTab === 'n8n'"
          class="border-t border-border bg-muted/20 px-4 py-3 text-xs text-muted-foreground"
        >
          {{ t('api_docs.examples.n8n_note') }}
        </div>
      </div>
    </section>

    <!-- Endpoints -->
    <section
      data-test="api-docs-endpoints"
      class="space-y-6"
    >
      <div class="space-y-1">
        <h2 class="text-xl font-semibold">
          {{ t('api_docs.endpoints.title') }}
        </h2>
        <p class="text-sm text-muted-foreground">
          {{ t('api_docs.endpoints.description') }}
        </p>
      </div>

      <div class="space-y-4">
        <!-- GET /me -->
        <div class="rounded-lg border border-border p-4 space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <Badge
              variant="secondary"
              class="font-mono text-xs"
            >
              GET
            </Badge>
            <code class="font-mono text-sm font-medium">/api/v1/me</code>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ t('api_docs.endpoints.items.me.summary') }}
          </p>
        </div>

        <!-- GET /output-targets -->
        <div class="rounded-lg border border-border p-4 space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <Badge
              variant="secondary"
              class="font-mono text-xs"
            >
              GET
            </Badge>
            <code class="font-mono text-sm font-medium">/api/v1/output-targets</code>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ t('api_docs.endpoints.items.targets.summary') }}
          </p>
        </div>

        <!-- POST /sql-queries -->
        <div class="rounded-lg border border-border p-4 space-y-4">
          <div class="flex flex-wrap items-center gap-2">
            <Badge class="font-mono text-xs">
              POST
            </Badge>
            <code class="font-mono text-sm font-medium">/api/v1/sql-queries</code>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ t('api_docs.endpoints.items.store_query.summary') }}
          </p>

          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
              <thead>
                <tr class="border-b border-border text-muted-foreground uppercase">
                  <th class="py-2 pr-4">
                    {{ t('api_docs.endpoints.field') }}
                  </th>
                  <th class="py-2 pr-4">
                    {{ t('api_docs.endpoints.type') }}
                  </th>
                  <th class="py-2 pr-4">
                    {{ t('api_docs.endpoints.required') }}
                  </th>
                  <th class="py-2">
                    {{ t('api_docs.endpoints.details') }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <tr>
                  <td class="py-2 pr-4 font-mono">
                    schema_source
                  </td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">
                    string
                  </td>
                  <td class="py-2 pr-4">
                    {{ t('api_docs.endpoints.yes') }}
                  </td>
                  <td class="py-2 text-muted-foreground">
                    {{
                      t(
                        'api_docs.endpoints.items.store_query.schema_source_desc',
                      )
                    }}
                  </td>
                </tr>
                <tr>
                  <td class="py-2 pr-4 font-mono">
                    dialect
                  </td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">
                    string
                  </td>
                  <td class="py-2 pr-4">
                    {{ t('api_docs.endpoints.yes') }}
                  </td>
                  <td class="py-2 text-muted-foreground">
                    {{
                      t(
                        'api_docs.endpoints.items.store_query.dialect_desc',
                      )
                    }}
                  </td>
                </tr>
                <tr>
                  <td class="py-2 pr-4 font-mono">
                    output_target
                  </td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">
                    string
                  </td>
                  <td class="py-2 pr-4">
                    {{ t('api_docs.endpoints.yes') }}
                  </td>
                  <td class="py-2 text-muted-foreground">
                    {{
                      t(
                        'api_docs.endpoints.items.store_query.output_target_desc',
                      )
                    }}
                  </td>
                </tr>
                <tr>
                  <td class="py-2 pr-4 font-mono">
                    schema
                  </td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">
                    string
                  </td>
                  <td class="py-2 pr-4">
                    {{ t('api_docs.endpoints.yes') }}
                  </td>
                  <td class="py-2 text-muted-foreground">
                    {{
                      t(
                        'api_docs.endpoints.items.store_query.schema_desc',
                      )
                    }}
                  </td>
                </tr>
                <tr>
                  <td class="py-2 pr-4 font-mono">
                    description
                  </td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">
                    string
                  </td>
                  <td class="py-2 pr-4">
                    {{ t('api_docs.endpoints.yes') }}
                  </td>
                  <td class="py-2 text-muted-foreground">
                    {{
                      t(
                        'api_docs.endpoints.items.store_query.description_desc',
                      )
                    }}
                  </td>
                </tr>
                <tr>
                  <td class="py-2 pr-4 font-mono">
                    locale
                  </td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">
                    string
                  </td>
                  <td class="py-2 pr-4">
                    {{ t('api_docs.endpoints.no') }}
                  </td>
                  <td class="py-2 text-muted-foreground">
                    {{
                      t(
                        'api_docs.endpoints.items.store_query.locale_desc',
                      )
                    }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- GET /sql-queries -->
        <div class="rounded-lg border border-border p-4 space-y-4">
          <div class="flex flex-wrap items-center gap-2">
            <Badge
              variant="secondary"
              class="font-mono text-xs"
            >
              GET
            </Badge>
            <code class="font-mono text-sm font-medium">/api/v1/sql-queries</code>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ t('api_docs.endpoints.items.list_queries.summary') }}
          </p>

          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
              <thead>
                <tr class="border-b border-border text-muted-foreground uppercase">
                  <th class="py-2 pr-4">
                    {{ t('api_docs.endpoints.field') }}
                  </th>
                  <th class="py-2 pr-4">
                    {{ t('api_docs.endpoints.type') }}
                  </th>
                  <th class="py-2 pr-4">
                    {{ t('api_docs.endpoints.required') }}
                  </th>
                  <th class="py-2">
                    {{ t('api_docs.endpoints.details') }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <tr>
                  <td class="py-2 pr-4 font-mono">
                    status
                  </td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">
                    string
                  </td>
                  <td class="py-2 pr-4">
                    {{ t('api_docs.endpoints.no') }}
                  </td>
                  <td class="py-2 text-muted-foreground">
                    {{
                      t(
                        'api_docs.endpoints.items.list_queries.status_desc',
                      )
                    }}
                  </td>
                </tr>
                <tr>
                  <td class="py-2 pr-4 font-mono">
                    page
                  </td>
                  <td class="py-2 pr-4 font-mono text-muted-foreground">
                    integer
                  </td>
                  <td class="py-2 pr-4">
                    {{ t('api_docs.endpoints.no') }}
                  </td>
                  <td class="py-2 text-muted-foreground">
                    {{
                      t(
                        'api_docs.endpoints.items.list_queries.page_desc',
                        { perPage: paginationPerPage },
                      )
                    }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- GET /sql-queries/{ulid} -->
        <div class="rounded-lg border border-border p-4 space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <Badge
              variant="secondary"
              class="font-mono text-xs"
            >
              GET
            </Badge>
            <code class="font-mono text-sm font-medium">/api/v1/sql-queries/{ulid}</code>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ t('api_docs.endpoints.items.show_query.summary') }}
          </p>
        </div>
      </div>

      <!-- Tabela de Output Targets habilitados -->
      <div
        v-if="targets.length > 0"
        data-test="api-docs-targets"
        class="rounded-lg border border-border p-4 space-y-3"
      >
        <div class="space-y-1">
          <h3 class="text-sm font-medium">
            {{ t('api_docs.endpoints.targets_title') }}
          </h3>
          <p class="text-xs text-muted-foreground">
            {{ t('api_docs.endpoints.targets_description') }}
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-left text-xs">
            <thead>
              <tr class="border-b border-border text-muted-foreground uppercase">
                <th class="py-2 pr-4">
                  {{ t('api_docs.endpoints.target_slug') }}
                </th>
                <th class="py-2 pr-4">
                  {{ t('api_docs.endpoints.target_name') }}
                </th>
                <th class="py-2 pr-4">
                  {{ t('api_docs.endpoints.target_language') }}
                </th>
                <th class="py-2">
                  {{ t('api_docs.endpoints.target_dialects') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr
                v-for="target in targets"
                :key="target.slug"
              >
                <td class="py-2 pr-4 font-mono font-medium">
                  {{ target.slug }}
                </td>
                <td class="py-2 pr-4">
                  {{ target.label }}
                </td>
                <td class="py-2 pr-4 font-mono text-muted-foreground">
                  {{ target.language }}
                </td>
                <td class="py-2 font-mono text-muted-foreground">
                  {{ target.supportedDialects.join(', ') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Limites de taxa -->
    <section
      data-test="api-docs-limits"
      class="space-y-4"
    >
      <div class="space-y-1">
        <h2 class="text-xl font-semibold">
          {{ t('api_docs.limits.title') }}
        </h2>
        <p class="text-sm text-muted-foreground">
          {{ t('api_docs.limits.description') }}
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-border p-4">
          <p class="text-xs text-muted-foreground">
            {{ t('api_docs.limits.post_title') }}
          </p>
          <p class="mt-2 font-mono text-sm font-medium">
            {{
              t('api_docs.limits.per_minute', {
                count: rateLimits.postPerMinute,
              })
            }}
            ·
            {{
              t('api_docs.limits.per_hour', {
                count: rateLimits.postPerHour,
              })
            }}
          </p>
        </div>

        <div class="rounded-lg border border-border p-4">
          <p class="text-xs text-muted-foreground">
            {{ t('api_docs.limits.read_title') }}
          </p>
          <p class="mt-2 font-mono text-sm font-medium">
            {{
              t('api_docs.limits.per_minute', {
                count: rateLimits.readPerMinute,
              })
            }}
          </p>
        </div>
      </div>

      <p class="text-xs text-muted-foreground">
        {{ t('api_docs.limits.headers_note') }}
      </p>
    </section>

    <!-- Codigos de erro -->
    <section
      data-test="api-docs-errors"
      class="space-y-4"
    >
      <div class="space-y-1">
        <h2 class="text-xl font-semibold">
          {{ t('api_docs.errors.title') }}
        </h2>
        <p class="text-sm text-muted-foreground">
          {{ t('api_docs.errors.description') }}
        </p>
      </div>

      <div class="overflow-x-auto rounded-lg border border-border">
        <table class="w-full border-collapse text-left text-xs">
          <thead>
            <tr class="border-b border-border bg-muted/30 text-muted-foreground uppercase">
              <th class="px-4 py-2.5">
                {{ t('api_docs.errors.status') }}
              </th>
              <th class="px-4 py-2.5">
                {{ t('api_docs.errors.code') }}
              </th>
              <th class="px-4 py-2.5">
                {{ t('api_docs.errors.when') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr
              v-for="row in errorRows"
              :key="row.status"
            >
              <td class="px-4 py-2.5 font-mono font-medium">
                {{ row.status }}
              </td>
              <td class="px-4 py-2.5 font-mono text-muted-foreground">
                {{ row.code }}
              </td>
              <td class="px-4 py-2.5 text-muted-foreground">
                {{ t(`api_docs.errors.items.${row.key}`) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
