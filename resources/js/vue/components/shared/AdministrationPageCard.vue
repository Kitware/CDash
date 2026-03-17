<template>
  <a
    :href="$baseURL + href"
    class="tw-flex tw-items-center tw-p-6 tw-bg-base-100 tw-border tw-border-base-300 tw-rounded-xl tw-shadow hover:tw-bg-base-200 tw-transition-colors tw-gap-4"
  >
    <div
      v-if="icon"
      class="tw-flex-shrink-0 tw-flex tw-items-center tw-justify-center"
    >
      <font-awesome-icon
        :icon="icon"
        class="tw-h-8 tw-w-8 tw-text-black"
      />
    </div>
    <div class="tw-flex tw-flex-col tw-justify-center">
      <h5 class="tw-text-xl tw-font-bold tw-m-0">
        {{ title }}
      </h5>
      <slot />
    </div>
  </a>
</template>

<script>
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

export default {
  name: 'AdministrationPageCard',
  components: {
    FontAwesomeIcon,
  },
  props: {
    href: {
      type: String,
      required: true,
    },
    title: {
      type: String,
      required: true,
    },
    icon: {
      type: Object,
      required: false,
      default: null,
    },
  },
};
</script>
