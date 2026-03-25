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
    <textarea
      v-model="model"
      class="tw-textarea tw-textarea-bordered tw-w-full"
      :class="{'tw-input-error': validationError}"
      :disabled="disabled"
      :data-test="testId"
    />
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
  type: [String, null],
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

  testId: {
    type: String,
    required: false,
    default: undefined,
  },
});
</script>
