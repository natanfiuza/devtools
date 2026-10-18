<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthCard from '@/components/AuthCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const { t } = useI18n();
const page = usePage();

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const codeInput = ref<HTMLInputElement | null>(null);
const recoveryCodeInput = ref<HTMLInputElement | null>(null);

const toggleRecovery = async () => {
    recovery.value = !recovery.value;
    form.clearErrors();
    form.code = '';
    form.recovery_code = '';

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value?.focus();
    } else {
        codeInput.value?.focus();
    }
};

const submit = () => {
    form.post(page.url, {
        onFinish: () => {
            form.reset();
        },
    });
};

const title = computed(() =>
    recovery.value
        ? t('auth.two_factor.recovery_title')
        : t('auth.two_factor.title'),
);

const description = computed(() =>
    recovery.value
        ? t('auth.two_factor.recovery_description')
        : t('auth.two_factor.description'),
);
</script>

<template>
    <Head :title="title" />

    <AuthCard :title="title" :description="description">
        <form @submit.prevent="submit" class="flex flex-col gap-5">
            <div class="grid gap-4">
                <div v-if="!recovery" class="grid gap-2">
                    <Label for="code">{{ t('auth.two_factor.code_label') }}</Label>
                    <Input
                        id="code"
                        ref="codeInput"
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="123456"
                        maxlength="6"
                        class="font-mono text-center text-lg tracking-widest"
                        :aria-invalid="form.errors.code ? 'true' : undefined"
                        aria-describedby="code-error"
                    />
                    <InputError id="code-error" :message="form.errors.code" />
                </div>

                <div v-else class="grid gap-2">
                    <Label for="recovery_code">
                        {{ t('auth.two_factor.recovery_code_label') }}
                    </Label>
                    <Input
                        id="recovery_code"
                        ref="recoveryCodeInput"
                        v-model="form.recovery_code"
                        type="text"
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="abcdef-98765"
                        class="font-mono text-center tracking-wider"
                        :aria-invalid="form.errors.recovery_code ? 'true' : undefined"
                        aria-describedby="recovery-code-error"
                    />
                    <InputError
                        id="recovery-code-error"
                        :message="form.errors.recovery_code"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                    data-test="confirm-two-factor-button"
                >
                    <Spinner v-if="form.processing" />
                    {{ t('auth.two_factor.submit') }}
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="toggleRecovery"
                >
                    {{
                        recovery
                            ? t('auth.two_factor.use_auth_code')
                            : t('auth.two_factor.use_recovery_code')
                    }}
                </Button>
            </div>
        </form>
    </AuthCard>
</template>
