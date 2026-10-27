<template>
  <label class="tw-form-control tw-w-full">
    <span class="tw-label tw-label-text tw-font-bold">
      {{ label }}
    </span>
    <span
      v-if="description"
      class="tw-text-xs tw-text-neutral-500"
    >
      {{ description }}
    </span>
    <input
      v-model="model"
      :type="type"
      :name="name"
      class="tw-input tw-input-bordered tw-w-full"
      :class="{'tw-input-error': validationError}"
      :disabled="disabled"
      :placeholder="placeholder"
      :min="min"
      :max="max"
      :maxlength="maxlength"
      :data-test="testId"
    >
    <span
      v-if="remainingCharacters < REMAINING_CHARACTERS_WARNING"
      class="tw-label tw-text-xs tw-text-neutral-500"
      :data-test="testId && `${testId}-remaining-characters`"
    >
      {{ remainingCharacters }} {{ remainingCharacters === 1 ? 'character' : 'characters' }} remaining
    </span>
    <span
      v-if="validationError"
      class="tw-label tw-text-error"
    >
      {{ validationError }}
    </span>
  </label>
</template>

<script setup>
import { computed } from 'vue';

// Show how many characters are left once a value is this close to its maximum length.
const REMAINING_CHARACTERS_WARNING = 20;

const model = defineModel({
  type: [String, Number, null],
  required: true,
});

const props = defineProps({
  label: {
    type: String,
    required: true,
  },

  description: {
    type: String,
    required: false,
    default: undefined,
  },

  disabled: {
    type: Boolean,
    required: false,
    default: false,
  },

  validationError: {
    type: String,
    required: false,
    default: undefined,
  },

  type: {
    type: String,
    required: false,
    default: 'text',
  },

  name: {
    type: String,
    required: false,
    default: undefined,
  },

  min: {
    type: [String, Number],
    required: false,
    default: undefined,
  },

  max: {
    type: [String, Number],
    required: false,
    default: undefined,
  },

  maxlength: {
    type: [String, Number],
    required: false,
    default: undefined,
  },

  placeholder: {
    type: [String, Number],
    required: false,
    default: undefined,
  },

  testId: {
    type: String,
    required: false,
    default: undefined,
  },
});

// Counted like the browser's maxlength, in UTF-16 code units.
const remainingCharacters = computed(() => (
  props.maxlength === undefined ? Infinity : Number(props.maxlength) - String(model.value ?? '').length
));
</script>
