<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Copy,
    KeyRound,
    RefreshCw,
    Shield,
    ShieldAlert,
    ShieldCheck,
} from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
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

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);

const enabling = ref(false);
const confirming = ref(false);
const qrCodeSvg = ref<string | null>(null);
const setupKey = ref<string | null>(null);
const confirmationCode = ref('');
const confirmationError = ref<string | undefined>(undefined);

const showRecoveryCodesDialog = ref(false);
const recoveryCodes = ref<string[]>([]);
const copiedKey = ref(false);
const copiedCodes = ref(false);

const twoFactorEnabled = computed(() => Boolean(user.value.two_factor_enabled));

const fetchQrCode = async () => {
    try {
        const response = await fetch('/user/two-factor-qr-code', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (response.ok) {
            const data = await response.json();
            qrCodeSvg.value = data.svg;
        }
    } catch {
        // Ignora erro de rede
    }
};

const fetchSetupKey = async () => {
    try {
        const response = await fetch('/user/two-factor-secret-key', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (response.ok) {
            const data = await response.json();
            setupKey.value = data.secretKey;
        }
    } catch {
        // Ignora erro de rede
    }
};

const fetchRecoveryCodes = async () => {
    try {
        const response = await fetch('/user/two-factor-recovery-codes', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (response.ok) {
            const data = await response.json();
            recoveryCodes.value = data;
        }
    } catch {
        // Ignora erro de rede
    }
};

const enableTwoFactor = () => {
    router.post(
        '/user/two-factor-authentication',
        {},
        {
            preserveScroll: true,
            onSuccess: async () => {
                enabling.value = true;
                confirmationError.value = undefined;
                confirmationCode.value = '';
                await Promise.all([fetchQrCode(), fetchSetupKey()]);
            },
        },
    );
};

const confirmTwoFactor = () => {
    confirming.value = true;
    confirmationError.value = undefined;

    router.post(
        '/user/confirmed-two-factor-authentication',
        {
            code: confirmationCode.value,
        },
        {
            preserveScroll: true,
            onSuccess: async () => {
                enabling.value = false;
                confirming.value = false;
                confirmationCode.value = '';
                await fetchRecoveryCodes();
                showRecoveryCodesDialog.value = true;
            },
            onError: (errors) => {
                confirming.value = false;
                confirmationError.value =
                    errors.code ||
                    t('settings.security.two_factor_invalid_code');
            },
        },
    );
};

const cancelEnabling = () => {
    router.delete('/user/two-factor-authentication', {
        preserveScroll: true,
        onSuccess: () => {
            enabling.value = false;
            qrCodeSvg.value = null;
            setupKey.value = null;
            confirmationCode.value = '';
            confirmationError.value = undefined;
        },
    });
};

const disableTwoFactor = () => {
    router.delete('/user/two-factor-authentication', {
        preserveScroll: true,
        onSuccess: () => {
            enabling.value = false;
            qrCodeSvg.value = null;
            setupKey.value = null;
            recoveryCodes.value = [];
        },
    });
};

const openRecoveryCodes = async () => {
    await fetchRecoveryCodes();
    showRecoveryCodesDialog.value = true;
};

const regenerateRecoveryCodes = () => {
    router.post(
        '/user/two-factor-recovery-codes',
        {},
        {
            preserveScroll: true,
            onSuccess: async () => {
                await fetchRecoveryCodes();
            },
        },
    );
};

const copySetupKey = async () => {
    if (setupKey.value) {
        await navigator.clipboard.writeText(setupKey.value);
        copiedKey.value = true;
        setTimeout(() => {
            copiedKey.value = false;
        }, 2000);
    }
};

const copyAllRecoveryCodes = async () => {
    if (recoveryCodes.value.length > 0) {
        await navigator.clipboard.writeText(recoveryCodes.value.join('\n'));
        copiedCodes.value = true;
        setTimeout(() => {
            copiedCodes.value = false;
        }, 2000);
    }
};
</script>

<template>
    <div class="space-y-6 pt-6">
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="t('settings.security.two_factor_title')"
                :description="t('settings.security.two_factor_description')"
            />

            <Badge
                :variant="twoFactorEnabled ? 'default' : 'secondary'"
                class="w-fit"
            >
                <ShieldCheck v-if="twoFactorEnabled" class="mr-1 size-3.5" />
                <Shield v-else class="mr-1 size-3.5" />
                <span>
                    {{
                        twoFactorEnabled
                            ? t('settings.security.two_factor_enabled_badge')
                            : t('settings.security.two_factor_disabled_badge')
                    }}
                </span>
            </Badge>
        </div>

        <!-- Estado 1: 2FA Desativado e nao em processo de ativacao -->
        <div
            v-if="!twoFactorEnabled && !enabling"
            class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-xs"
        >
            <div class="space-y-3">
                <p class="text-sm leading-relaxed text-muted-foreground">
                    {{ t('settings.security.two_factor_disabled_help') }}
                </p>

                <Button
                    type="button"
                    variant="outline"
                    data-test="enable-2fa-button"
                    @click="enableTwoFactor"
                >
                    <KeyRound class="mr-1.5 size-4" />
                    <span>{{
                        t('settings.security.two_factor_enable_button')
                    }}</span>
                </Button>
            </div>
        </div>

        <!-- Estado 2: Em processo de ativacao (exibe QR Code + input de confirmacao) -->
        <div
            v-if="!twoFactorEnabled && enabling"
            class="space-y-6 rounded-lg border border-brand/40 bg-brand/5 p-4"
        >
            <div class="space-y-2">
                <h4 class="font-medium text-foreground">
                    {{ t('settings.security.two_factor_scan_title') }}
                </h4>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    {{ t('settings.security.two_factor_scan_instructions') }}
                </p>
            </div>

            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div
                    v-if="qrCodeSvg"
                    class="rounded-md border border-border bg-white p-3 shadow-xs"
                    v-html="qrCodeSvg"
                />

                <div class="flex-1 space-y-4">
                    <div v-if="setupKey" class="space-y-1.5">
                        <Label class="text-xs text-muted-foreground">
                            {{ t('settings.security.two_factor_setup_key') }}
                        </Label>
                        <div class="flex items-center gap-2">
                            <code
                                class="rounded bg-muted px-2 py-1 font-mono text-xs font-semibold"
                            >
                                {{ setupKey }}
                            </code>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 px-2"
                                @click="copySetupKey"
                            >
                                <Copy class="size-3.5" />
                                <span class="text-xs">
                                    {{
                                        copiedKey
                                            ? t(
                                                  'settings.security.two_factor_copied',
                                              )
                                            : t(
                                                  'settings.security.two_factor_copy',
                                              )
                                    }}
                                </span>
                            </Button>
                        </div>
                    </div>

                    <div class="max-w-xs space-y-2">
                        <Label for="two-factor-code">
                            {{ t('settings.security.two_factor_code_label') }}
                        </Label>
                        <Input
                            id="two-factor-code"
                            v-model="confirmationCode"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            placeholder="123456"
                            maxlength="6"
                            class="font-mono text-base tracking-widest"
                            @keyup.enter="confirmTwoFactor"
                        />
                        <InputError :message="confirmationError" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            :disabled="
                                confirming || confirmationCode.length !== 6
                            "
                            data-test="confirm-2fa-button"
                            @click="confirmTwoFactor"
                        >
                            {{
                                t('settings.security.two_factor_confirm_button')
                            }}
                        </Button>

                        <Button
                            type="button"
                            variant="secondary"
                            @click="cancelEnabling"
                        >
                            {{
                                t('settings.security.two_factor_cancel_button')
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado 3: 2FA Ativado e Confirmado -->
        <div
            v-if="twoFactorEnabled"
            class="space-y-4 rounded-lg border border-border bg-card p-4 text-card-foreground shadow-xs"
        >
            <div class="flex items-start gap-3">
                <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-brand" />
                <div class="space-y-1">
                    <p class="text-sm font-medium text-foreground">
                        {{ t('settings.security.two_factor_active_title') }}
                    </p>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        {{ t('settings.security.two_factor_active_help') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="show-recovery-codes-button"
                    @click="openRecoveryCodes"
                >
                    <ShieldAlert class="mr-1.5 size-4" />
                    <span>{{
                        t('settings.security.two_factor_recovery_codes_button')
                    }}</span>
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                    data-test="disable-2fa-button"
                    @click="disableTwoFactor"
                >
                    <span>{{
                        t('settings.security.two_factor_disable_button')
                    }}</span>
                </Button>
            </div>
        </div>

        <!-- Dialog de Codigos de Recuperacao -->
        <Dialog v-model:open="showRecoveryCodesDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ t('settings.security.two_factor_recovery_title') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'settings.security.two_factor_recovery_description',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div
                        class="grid grid-cols-2 gap-2 rounded-lg bg-muted p-4 font-mono text-xs"
                    >
                        <span
                            v-for="code in recoveryCodes"
                            :key="code"
                            class="font-semibold tracking-wider text-foreground select-all"
                        >
                            {{ code }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="copyAllRecoveryCodes"
                        >
                            <Copy class="mr-1.5 size-3.5" />
                            <span>
                                {{
                                    copiedCodes
                                        ? t(
                                              'settings.security.two_factor_copied',
                                          )
                                        : t(
                                              'settings.security.two_factor_copy_codes',
                                          )
                                }}
                            </span>
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="regenerateRecoveryCodes"
                        >
                            <RefreshCw class="mr-1.5 size-3.5" />
                            <span>{{
                                t(
                                    'settings.security.two_factor_regenerate_codes',
                                )
                            }}</span>
                        </Button>
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ t('settings.security.two_factor_close') }}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
