<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import ExportData from '@/components/ExportData.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useRoutes } from '@/composables/useRoutes';
import type { Locale } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);

const { t } = useI18n();
const routes = useRoutes();

const locales = computed<Locale[]>(() => page.props.locales ?? []);

/**
 * Idioma da conta e o dos e-mails; sem escolha explicita ainda, cai no idioma
 * em que a pessoa esta navegando agora.
 */
const locale = ref(user.value.locale ?? page.props.locale);
</script>

<template>
    <Head :title="t('settings.profile.title')" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t('settings.profile.title')"
            :description="t('settings.profile.description')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('settings.profile.name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="t('settings.profile.name_placeholder')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="username">{{ t('settings.profile.username') }}</Label>
                <div class="relative mt-1">
                    <span
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 font-mono text-sm text-muted-foreground"
                        aria-hidden="true"
                    >
                        @
                    </span>
                    <Input
                        id="username"
                        class="block w-full pl-7 font-mono"
                        name="username"
                        :default-value="user.username ?? ''"
                        autocomplete="off"
                        :placeholder="t('settings.profile.username_placeholder')"
                        aria-describedby="username-help"
                    />
                </div>
                <p
                    id="username-help"
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    {{ t('settings.profile.username_help') }}
                </p>
                <InputError class="mt-2" :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('settings.profile.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :placeholder="t('settings.profile.email_placeholder')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{ t('settings.profile.locale') }}</Label>
                <Select v-model="locale" name="locale">
                    <SelectTrigger
                        id="locale"
                        class="mt-1 w-full"
                        aria-describedby="locale-help"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in locales"
                            :key="option.code"
                            :value="option.code"
                        >
                            <span :lang="option.html">{{ option.name }}</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    id="locale-help"
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    {{ t('settings.profile.locale_help') }}
                </p>
                <InputError class="mt-2" :message="errors.locale" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ t('auth.verify.unverified') }}
                    <Link
                        :href="routes.form.resendVerification.value.action"
                        method="post"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        {{ t('auth.verify.unverified_action') }}
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-brand"
                >
                    {{ t('auth.verify.sent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{ t('settings.profile.save') }}
                </Button>
            </div>
        </Form>
    </div>

    <ExportData />

    <DeleteUser />
</template>
