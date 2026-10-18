<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import profile from '@/routes/profile';

/**
 * Excluir a conta (ADR-028).
 *
 * A exclusao anonimiza: o dado pessoal sai e o que tem guarda fiscal - os
 * pagamentos - fica. Como nao ha volta, o dialogo oferece o download dos dados
 * antes de confirmar.
 */
const { t } = useI18n();

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('account.delete.title')"
            :description="t('account.delete.description')"
        />
        <div
            class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">
                    {{ t('account.delete.warning_title') }}
                </p>
                <p class="text-sm">
                    {{ t('account.delete.warning') }}
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                    >
                        {{ t('account.delete.action') }}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-slot="{ errors, processing, reset, clearErrors }"
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        @error="() => passwordInput?.focus()"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>
                                {{ t('account.delete.dialog_title') }}
                            </DialogTitle>
                            <DialogDescription>
                                {{ t('account.delete.dialog_description') }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">
                                {{ t('account.delete.password') }}
                            </Label>
                            <PasswordInput
                                id="password"
                                ref="passwordInput"
                                name="password"
                                :placeholder="
                                    t('account.delete.password_placeholder')
                                "
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <Button variant="outline" as-child>
                                <a
                                    :href="profile.export.url()"
                                    data-test="delete-download-button"
                                >
                                    {{ t('account.delete.download') }}
                                </a>
                            </Button>

                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    {{ t('account.delete.cancel') }}
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                {{ t('account.delete.confirm') }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
