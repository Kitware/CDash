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
    <select
      v-model="model"
      class="tw-select tw-select-bordered tw-w-full"
      :class="{'tw-input-error': validationError}"
      :disabled="disabled"
      :data-test="testId"
    >
      <option
        v-for="{ text, value } in options"
        :value="value"
      >
        {{ text }}
      </option>
    </select>
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
  /**
   * An array of objects of the form:
   * {
   *   text: String,
   *   value: ?String,
   * }
   */
  options: {
    type: Array,
    required: true,
  },

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
