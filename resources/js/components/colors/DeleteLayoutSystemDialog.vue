<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import systems from '@/routes/dashboard/colors/systems';

/**
 * Confirmacao da exclusao definitiva do sistema (F4, ADR-164).
 *
 * Excluir aposenta os slugs para sempre e libera a vaga da cota; nao ha
 * lixeira. O dono digita o nome exato e o servidor compara com o nome gravado,
 * nunca com um id enviado pelo cliente. Arquivar continua reversivel.
 */

const props = defineProps<{
    open: boolean;
    systemId: string;
    systemName: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const confirmName = ref('');
const serverError = ref<string | null>(null);
const deleting = ref(false);

function reset(): void {
    confirmName.value = '';
    serverError.value = null;
    deleting.value = false;
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        }
    },
);

const matches = computed(() => confirmName.value === props.systemName);

function destroy(): void {
    if (!matches.value || deleting.value) {
        return;
    }

    deleting.value = true;
    serverError.value = null;

    router.delete(systems.destroy.url({ layoutSystem: props.systemId }), {
        data: { confirm_name: confirmName.value },
        preserveScroll: true,
        onError: (errors) => {
            serverError.value = errors.confirm_name ?? null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

function setOpen(value: boolean): void {
    if (!value) {
        emit('close');
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="setOpen">
        <DialogContent
            class="sm:max-w-md"
            data-test="layout-system-delete-dialog"
        >
            <DialogHeader>
                <DialogTitle>
                    {{ t('colors.systems.delete.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('colors.systems.delete.description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-3" @submit.prevent="destroy">
                <div class="flex flex-col gap-2">
                    <Label for="layout-system-delete-name">
                        {{ t('colors.systems.delete.label') }}
                    </Label>
                    <Input
                        id="layout-system-delete-name"
                        v-model="confirmName"
                        type="text"
                        autocomplete="off"
                        :placeholder="systemName"
                        :aria-invalid="serverError !== null"
                        data-test="layout-system-delete-name"
                    />
                    <InputError :message="serverError ?? undefined" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="deleting"
                        data-test="layout-system-delete-cancel"
                        @click="emit('close')"
                    >
                        {{ t('colors.systems.delete.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        size="sm"
                        :disabled="!matches || deleting"
                        data-test="layout-system-delete-confirm"
                    >
                        {{
                            deleting
                                ? t('colors.systems.delete.deleting')
                                : t('colors.systems.delete.confirm')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
