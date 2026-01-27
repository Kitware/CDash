<template>
  <pre
    class="tw-font-mono tw-bg-gray-100 tw-p-2 tw-whitespace-pre-wrap tw-break-all tw-overflow-hidden"
    :class="{ 'tw-border': bordered, 'tw-rounded': bordered }"
    v-html="ansiText"
  />
</template>

<script>
import { AnsiUp } from 'ansi_up';

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

  computed: {
    ansiText() {
      const escapedText = String(this.text).replace(/\[NON-XML-CHAR-0x1B\]/g, '\x1B') ?? '';
      return (new AnsiUp).ansi_to_html(escapedText);
    },
  },
};
</script>
