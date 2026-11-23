<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Toaster } from '@/components/ui/sonner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Pagina /styleguide (entregavel do DESIGN.md): os tokens e os componentes
 * em uso, lado a lado, para conferir os dois temas. Os nomes entre parenteses
 * sao os tokens de resources/css/app.css.
 */
const { t } = useI18n();

/** Tokens de cor, na ordem em que aparecem no CSS. */
const colorTokens = [
    '--background',
    '--surface',
    '--surface-2',
    '--foreground',
    '--muted-foreground',
    '--subtle',
    '--brand',
    '--accent-2',
    '--elevated',
    '--border',
    '--border-strong',
    '--destructive',
];

const syntaxTokens = [
    '--json-key',
    '--json-string',
    '--json-number',
    '--json-literal',
    '--json-punct',
];

const dialect = ref<string>('');
</script>

<template>
  <Head :title="t('styleguide.title')" />

  <div class="mx-auto w-full max-w-[1120px] px-4 py-12 sm:py-16">
    <header class="max-w-2xl">
      <h1 class="text-[32px] font-semibold tracking-[-0.02em]">
        {{ t('styleguide.title') }}
      </h1>
      <p class="mt-2 text-muted-foreground">
        {{ t('styleguide.subtitle') }}
      </p>
    </header>

    <section class="mt-12">
      <h2 class="text-xl font-semibold tracking-[-0.02em]">
        {{ t('styleguide.sections.colors') }}
      </h2>
      <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <li
          v-for="token in colorTokens"
          :key="token"
          class="flex items-center gap-3 rounded-md border p-3"
        >
          <span
            class="size-8 shrink-0 rounded border"
            :style="{ backgroundColor: `var(${token})` }"
            aria-hidden="true"
          />
          <code class="font-mono text-[13px]">{{ token }}</code>
        </li>
      </ul>
      <p class="mt-3 text-sm text-subtle">
        {{ t('styleguide.notes.contrast') }}
      </p>
    </section>

    <section class="mt-12">
      <h2 class="text-xl font-semibold tracking-[-0.02em]">
        {{ t('styleguide.sections.typography') }}
      </h2>
      <div class="mt-4 space-y-3 rounded-md border p-6">
        <p
          class="text-[56px] leading-tight font-semibold tracking-[-0.02em]"
        >
          devtools
        </p>
        <p class="text-[32px] font-semibold tracking-[-0.02em]">
          32 / 600
        </p>
        <p class="text-xl font-medium">
          20 / 500
        </p>
        <p class="text-base">
          16 / 400
        </p>
        <p class="text-sm">
          14 / 400
        </p>
        <p class="text-[13px]">
          13 / 400
        </p>
        <p class="text-xs">
          12 / 400
        </p>
        <p class="font-mono text-sm">
          const query = 'select 1'; // JetBrains Mono
        </p>
      </div>
      <p class="mt-3 text-sm text-subtle">
        {{ t('styleguide.notes.fonts') }}
      </p>
    </section>

    <section class="mt-12">
      <h2 class="text-xl font-semibold tracking-[-0.02em]">
        {{ t('styleguide.sections.buttons') }}
      </h2>
      <div class="mt-4 flex flex-wrap items-center gap-3">
        <Button>{{ t('styleguide.labels.primary') }}</Button>
        <Button variant="secondary">
          {{ t('styleguide.labels.secondary') }}
        </Button>
        <Button variant="outline">
          {{ t('styleguide.labels.outline') }}
        </Button>
        <Button variant="ghost">
          {{ t('styleguide.labels.ghost') }}
        </Button>
        <Button variant="destructive">
          {{ t('styleguide.labels.destructive') }}
        </Button>
        <Button disabled>
          {{ t('styleguide.labels.disabled') }}
        </Button>
      </div>
    </section>

    <section class="mt-12">
      <h2 class="text-xl font-semibold tracking-[-0.02em]">
        {{ t('styleguide.sections.forms') }}
      </h2>
      <div class="mt-4 grid max-w-2xl gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
          <Label for="styleguide-input">
            {{ t('styleguide.labels.input_placeholder') }}
          </Label>
          <Input
            id="styleguide-input"
            :placeholder="t('styleguide.labels.input_placeholder')"
          />
        </div>
        <div class="grid gap-2">
          <Label for="styleguide-select">
            {{ t('styleguide.labels.select_placeholder') }}
          </Label>
          <Select v-model="dialect">
            <SelectTrigger id="styleguide-select">
              <SelectValue
                :placeholder="
                  t('styleguide.labels.select_placeholder')
                "
              />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="mysql">
                MySQL
              </SelectItem>
              <SelectItem value="mariadb">
                MariaDB
              </SelectItem>
              <SelectItem value="postgres">
                PostgreSQL
              </SelectItem>
              <SelectItem value="sqlite">
                SQLite
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="grid gap-2">
          <Label for="styleguide-invalid">
            {{ t('styleguide.labels.destructive') }}
          </Label>
          <Input
            id="styleguide-invalid"
            aria-invalid="true"
            :placeholder="t('styleguide.labels.input_placeholder')"
          />
        </div>
      </div>
    </section>

    <section class="mt-12">
      <h2 class="text-xl font-semibold tracking-[-0.02em]">
        {{ t('styleguide.sections.badges') }}
      </h2>
      <div class="mt-4 flex flex-wrap items-center gap-3">
        <Badge>{{ t('styleguide.labels.badge_default') }}</Badge>
        <Badge variant="secondary">
          {{ t('styleguide.labels.badge_muted') }}
        </Badge>
        <Badge variant="outline">
          {{ t('styleguide.labels.outline') }}
        </Badge>
        <Badge variant="destructive">
          {{ t('styleguide.labels.destructive') }}
        </Badge>
      </div>
    </section>

    <section class="mt-12">
      <h2 class="text-xl font-semibold tracking-[-0.02em]">
        {{ t('styleguide.sections.cards') }}
      </h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <Card class="transition-colors hover:border-border-strong">
          <CardHeader>
            <CardTitle>
              {{ t('styleguide.labels.card_title') }}
            </CardTitle>
            <CardDescription>
              {{ t('styleguide.labels.card_body') }}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Badge variant="outline">
              no-shadow
            </Badge>
          </CardContent>
          <CardFooter>
            <Button
              variant="outline"
              size="sm"
            >
              {{ t('styleguide.labels.outline') }}
            </Button>
          </CardFooter>
        </Card>
      </div>
    </section>

    <section class="mt-12">
      <h2 class="text-xl font-semibold tracking-[-0.02em]">
        {{ t('styleguide.sections.feedback') }}
      </h2>
      <div class="mt-4 grid max-w-2xl gap-4">
        <Alert>
          <Info aria-hidden="true" />
          <AlertTitle>
            {{ t('styleguide.labels.card_title') }}
          </AlertTitle>
          <AlertDescription>
            {{ t('styleguide.labels.card_body') }}
          </AlertDescription>
        </Alert>

        <Alert variant="destructive">
          <Info aria-hidden="true" />
          <AlertTitle>
            {{ t('styleguide.labels.destructive') }}
          </AlertTitle>
          <AlertDescription>
            {{ t('styleguide.labels.card_body') }}
          </AlertDescription>
        </Alert>

        <div class="flex flex-wrap items-center gap-4">
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger as-child>
                <Button
                  variant="outline"
                  size="sm"
                >
                  {{ t('styleguide.labels.tooltip') }}
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                {{ t('styleguide.labels.tooltip') }}
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <Button
            variant="outline"
            size="sm"
            @click="toast.success(t('styleguide.labels.toast'))"
          >
            {{ t('styleguide.labels.toast') }}
          </Button>

          <span
            class="inline-flex items-center gap-2 text-sm text-muted-foreground"
          >
            <Spinner class="size-4" />
            {{ t('styleguide.labels.loading') }}
          </span>
        </div>
      </div>
    </section>

    <section class="mt-12">
      <h2 class="text-xl font-semibold tracking-[-0.02em]">
        {{ t('styleguide.sections.syntax') }}
      </h2>
      <ul class="mt-4 flex flex-wrap gap-3">
        <li
          v-for="token in syntaxTokens"
          :key="token"
          class="flex items-center gap-2 rounded-md border px-3 py-1.5"
        >
          <span
            class="size-3 rounded-full"
            :style="{ backgroundColor: `var(${token})` }"
            aria-hidden="true"
          />
          <code class="font-mono text-[13px]">{{ token }}</code>
        </li>
      </ul>

      <pre
        class="mt-4 overflow-x-auto rounded-md border bg-surface-2 p-4 font-mono text-sm"
      ><span class="text-json-punct">{</span>
  <span class="text-json-key">{{ t('styleguide.labels.sample_key') }}</span><span class="text-json-punct">:</span> <span class="text-json-string">{{ t('styleguide.labels.sample_string') }}</span><span class="text-json-punct">,</span>
  <span class="text-json-key">"count"</span><span class="text-json-punct">:</span> <span class="text-json-number">{{ t('styleguide.labels.sample_number') }}</span><span class="text-json-punct">,</span>
  <span class="text-json-key">"valid"</span><span class="text-json-punct">:</span> <span class="text-json-literal">{{ t('styleguide.labels.sample_boolean') }}</span><span class="text-json-punct">,</span>
  <span class="text-json-key">"missing"</span><span class="text-json-punct">:</span> <span class="text-json-literal">{{ t('styleguide.labels.sample_null') }}</span>
<span class="text-json-punct">}</span></pre>
    </section>

    <Toaster />
  </div>
</template>
