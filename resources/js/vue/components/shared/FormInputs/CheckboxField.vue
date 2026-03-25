<template>
  <div class="tw-form-control">
    <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
      <input
        v-model="model"
        type="checkbox"
        class="tw-checkbox"
        :class="{'tw-input-error': validationError}"
        :disabled="disabled"
        :data-test="testId"
      >
      <span>
        <span
          v-if="label"
          class="tw-label-text tw-font-bold"
        >
          {{ label }}
        </span>
        <div
          v-if="description"
          class="tw-text-xs tw-text-neutral-500"
        >
          {{ description }}
        </div>
      </span>
    </label>
  </div>
</template>

<script setup>
const model = defineModel({
  type: [String, Boolean],
  required: true,
});

defineProps({
  label: {
    type: String,
    required: false,
    default: undefined,
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
