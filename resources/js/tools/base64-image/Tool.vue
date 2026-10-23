<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Image as ImageIcon, Binary, History } from '@lucide/vue';
import EncoderPanel from './EncoderPanel.vue';
import DecoderPanel from './DecoderPanel.vue';
import HistoryPanel from './HistoryPanel.vue';
import type { Base64ImageToolProps, ConversionItem } from './types';

const props = defineProps<{
    toolProps: Base64ImageToolProps;
}>();

const { t } = useI18n();

const activeTab = ref('encoder');
const historyRef = ref<InstanceType<typeof HistoryPanel> | null>(null);

function onConversionDone(_item: ConversionItem) {
    historyRef.value?.reload();
}
</script>

<template>
    <div class="mx-auto max-w-5xl py-4 space-y-6">
        <Tabs v-model="activeTab" class="w-full">
            <div class="flex items-center justify-between border-b pb-3 mb-6">
                <TabsList class="grid grid-cols-3 w-full sm:w-auto">
                    <TabsTrigger value="encoder" class="gap-2">
                        <ImageIcon class="h-4 w-4" />
                        <span>{{ t('tools.base64-image.tabs.encoder') }}</span>
                    </TabsTrigger>
                    <TabsTrigger value="decoder" class="gap-2">
                        <Binary class="h-4 w-4" />
                        <span>{{ t('tools.base64-image.tabs.decoder') }}</span>
                    </TabsTrigger>
                    <TabsTrigger value="history" class="gap-2">
                        <History class="h-4 w-4" />
                        <span>{{ t('tools.base64-image.tabs.history') }}</span>
                    </TabsTrigger>
                </TabsList>
            </div>

            <!-- Aba do Codificador (Imagem -> Base64) -->
            <TabsContent value="encoder" class="focus-visible:outline-none">
                <EncoderPanel
                    :tool-props="props.toolProps"
                    @conversion-done="onConversionDone"
                />
            </TabsContent>

            <!-- Aba do Decodificador (Base64 -> Imagem) -->
            <TabsContent value="decoder" class="focus-visible:outline-none">
                <DecoderPanel />
            </TabsContent>

            <!-- Aba do Historico -->
            <TabsContent value="history" class="focus-visible:outline-none">
                <HistoryPanel
                    ref="historyRef"
                    :tool-props="props.toolProps"
                />
            </TabsContent>
        </Tabs>
    </div>
</template>
