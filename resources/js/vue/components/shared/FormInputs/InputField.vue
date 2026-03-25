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
      :data-test="testId"
    >
    <span
      v-if="validationError"
      class="tw-label tw-text-error"
    >
      {{ validationError }}
    </span>
  </label>
</template>

<script setup>
const model = defineModel({
  type: [String, Number, null],
  required: true,
});

defineProps({
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
</script>
