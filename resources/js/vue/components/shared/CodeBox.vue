<template>
  <div
    class="tw-font-mono tw-bg-gray-100 tw-p-2 tw-whitespace-pre-wrap tw-break-all tw-overflow-hidden"
    :class="{ 'tw-border': bordered, 'tw-rounded': bordered }"
  >
    {{ text }}
  </div>
</template>

<script>
export default {
  name: 'CodeBox',
  props: {
    text: {
      type: String,
      required: true,
    },

    bordered: {
      type: Boolean,
      default: true,
    },
  },
};
</script>
