<template>
  <div>
    <div class="tw-font-bold">
      Configure Command
    </div>
    <CodeBox :text="command" />
    <br>
    <div class="tw-flex tw-flex-row tw-gap-1">
      <span class="tw-font-bold">Return Value:</span>
      <span class="tw-font-mono">{{ returnValue }}</span>
    </div>
    <br>
    <div class="tw-font-bold">
      Configure Log
    </div>
    <CodeBox :text="log" />
  </div>
</template>

<script>
import CodeBox from './CodeBox.vue';

export default {
  name: 'ConfigureCard',
  components: {CodeBox},

  props: {
    command: {
      type: String,
      required: true,
    },

    log: {
      type: String,
      required: true,
    },

    returnValue: {
      type: Number,
      required: true,
    },
  },
};
</script>
