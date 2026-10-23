<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Trash2 } from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';

const { t } = useI18n();
const { getInitials } = useInitials();
const page = usePage();
const user = computed(() => page.props.auth.user);

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');
const previewUrl = ref<string | null>(null);

const form = useForm({
    photo: null as File | null,
});

const currentAvatarUrl = computed(() => {
    if (previewUrl.value) {
        return previewUrl.value;
    }
    return user.value.avatar || null;
});

const triggerFileInput = () => {
    fileInput.value?.click();
};

const onFileSelected = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) {
        return;
    }

    const file = input.files[0];
    if (!file) {
        return;
    }

    form.photo = file;
    previewUrl.value = URL.createObjectURL(file);

    form.post('/settings/profile/photo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
            }
            previewUrl.value = null;
            form.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
        onError: () => {
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
            }
            previewUrl.value = null;
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const removePhoto = () => {
    router.delete('/settings/profile/photo', {
        preserveScroll: true,
        onSuccess: () => {
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
            }
            previewUrl.value = null;
        },
    });
};
</script>

<template>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
    <input
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/png,image/jpg,image/webp"
      class="hidden"
      @change="onFileSelected"
    >

    <div class="relative">
      <Avatar
        class="size-20 overflow-hidden rounded-full border border-border shadow-xs"
      >
        <AvatarImage
          v-if="currentAvatarUrl"
          :src="currentAvatarUrl"
          :alt="user.name"
        />
        <AvatarFallback class="text-lg font-semibold text-foreground">
          {{ getInitials(user.name) }}
        </AvatarFallback>
      </Avatar>
    </div>

    <div class="space-y-1.5">
      <div class="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="form.processing"
          @click="triggerFileInput"
        >
          <Camera class="mr-1.5 size-4" />
          <span>
            {{
              form.processing
                ? t('settings.profile.photo_uploading')
                : t('settings.profile.photo_upload')
            }}
          </span>
        </Button>

        <Button
          v-if="user.avatar && !form.processing"
          type="button"
          variant="ghost"
          size="sm"
          class="text-destructive hover:bg-destructive/10 hover:text-destructive"
          @click="removePhoto"
        >
          <Trash2 class="mr-1.5 size-4" />
          <span>{{ t('settings.profile.photo_remove') }}</span>
        </Button>
      </div>

      <p class="text-xs text-muted-foreground">
        {{ t('settings.profile.photo_help') }}
      </p>

      <InputError :message="form.errors.photo" />
    </div>
  </div>
</template>
