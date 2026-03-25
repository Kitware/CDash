<template>
  <section class="tw-container tw-mx-auto">
    <h4 class="tw-text-xl tw-font-semibold tw-border-b tw-pb-2 tw-mb-2">
      {{ title }}
    </h4>
    <div class="tw-flex tw-flex-col tw-gap-1">
      <slot /> <!-- We expect the slot to be filled with a list of form fields. -->
    </div>
  </section>
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
