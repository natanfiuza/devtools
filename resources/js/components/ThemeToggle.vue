<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

const { t } = useI18n();
const { appearance, updateAppearance } = useAppearance();

const options: Array<{ value: Appearance; Icon: typeof Sun; label: string }> = [
    { value: 'light', Icon: Sun, label: 'light' },
    { value: 'dark', Icon: Moon, label: 'dark' },
    { value: 'system', Icon: Monitor, label: 'system' },
];

const icons: Record<Appearance, typeof Sun> = {
    light: Sun,
    dark: Moon,
    system: Monitor,
};

function onChange(value: unknown): void {
    if (value === 'light' || value === 'dark' || value === 'system') {
        updateAppearance(value);
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon-sm"
                :aria-label="t('app.theme.label')"
            >
                <component
                    :is="icons[appearance]"
                    class="size-4"
                    aria-hidden="true"
                />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-40">
            <DropdownMenuRadioGroup
                :model-value="appearance"
                @update:model-value="onChange"
            >
                <DropdownMenuRadioItem
                    v-for="{ value, Icon, label } in options"
                    :key="value"
                    :value="value"
                >
                    <component
                        :is="Icon"
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {{ t(`app.theme.${label}`) }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
