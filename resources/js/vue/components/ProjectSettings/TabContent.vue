<template>
  <div class="tw-w-full">
    <h3 class="tw-text-2xl tw-font-bold">
      {{ title }}
    </h3>
    <div class="tw-divider" />
    <div class="tw-flex tw-flex-col tw-w-full tw-justify-center tw-gap-12">
      <slot />
    </div>
  </div>
</template>

<script>
export default {
  props: {
    title: {
      type: String,
      required: true,
    },
  },
};
</script>
