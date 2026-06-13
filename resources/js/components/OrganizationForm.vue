<template>
    <form class="panel form-panel" @submit.prevent="submit">
        <div>
            <label for="yandex-url">Ссылка на организацию</label>
            <div class="input-row">
                <input
                    id="yandex-url"
                    v-model.trim="url"
                    type="url"
                    placeholder="https://yandex.ru/maps/org/..."
                    autocomplete="off"
                    :disabled="saving"
                >
                <button type="submit" :disabled="saving || !url">
                    <Save :size="18" />
                    <span>{{ saving ? 'Сохранение' : 'Сохранить' }}</span>
                </button>
            </div>
        </div>
        <p v-if="localError" class="field-error">{{ localError }}</p>
    </form>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Save } from '@lucide/vue';

const props = defineProps({
    initialUrl: {
        type: String,
        default: '',
    },
    saving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['save']);
const url = ref(props.initialUrl);
const localError = ref('');

watch(() => props.initialUrl, (value) => {
    url.value = value || '';
});

function submit() {
    if (!url.value) {
        localError.value = 'Вставьте ссылку на карточку Яндекс.Карт.';
        return;
    }

    localError.value = '';
    emit('save', url.value);
}
</script>
