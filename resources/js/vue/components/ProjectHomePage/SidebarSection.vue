<template>
  <section class="tw-py-4 first:tw-pt-0">
    <div class="tw-flex tw-flex-row tw-items-center tw-justify-between tw-gap-2 tw-mb-2">
      <h2 class="tw-text-base tw-font-semibold tw-m-0">
        {{ title }}
      </h2>
      <slot name="actions" />
    </div>
    <slot />
  </section>
</template>

<script>
export default {
  name: 'SidebarSection',

  props: {
    title: {
      type: String,
      required: true,
    },
  },
};
</script>
