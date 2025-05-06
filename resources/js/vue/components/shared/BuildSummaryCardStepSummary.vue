<template>
  <!-- We assume that all links have a highlight color. -->
  <a
    v-if="link"
    class="tw-p-2 tw-flex tw-flex-col tw-border-l-8 tw-group hover:tw-border-r-4 hover:tw-border-l-4"
    :class="highlightColor"
    :href="link"
  >
    <div class="tw-text-small tw-font-medium tw-text-neutral-500 tw-flex tw-flex-row tw-gap-2 group-hover:tw-font-bold">
      <div>
        <font-awesome-icon :icon="FA.faLink" />
        {{ upperLeftText }}
      </div>
      <div
        v-if="upperRightText"
        class="tw-flex-grow tw-text-right"
      >
        {{ upperRightText }}
      </div>
    </div>
    <div
      v-if="mainText"
      class="tw-text-lg"
    >
      {{ mainText }}
    </div>
  </a>
  <div
    v-else
    class="tw-p-2 tw-flex tw-flex-col"
    :class="{
      [highlightColor]: highlightColor !== null,
      'tw-border-l-8': highlightColor !== null,
    }"
  >
    <div class="tw-text-small tw-font-medium tw-text-neutral-500 tw-flex tw-flex-row tw-gap-2">
      <div>
        {{ upperLeftText }}
      </div>
      <div
        v-if="upperRightText"
        class="tw-flex-grow tw-text-right"
      >
        {{ upperRightText }}
      </div>
    </div>
    <div
      v-if="mainText"
      class="tw-text-lg tw-italic tw-font-light"
    >
      {{ mainText }}
    </div>
  </div>
</template>

<script>
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faLink} from '@fortawesome/free-solid-svg-icons';

export default {
  components: {FontAwesomeIcon},

  props: {
    upperRightText: {
      type: String,
      default: null,
    },

    upperLeftText: {
      type: String,
      required: true,
    },

    link: {
      type: String,
      default: null,
    },

    mainText: {
      type: String,
      default: null,
    },

    highlightColor: {
      type: String,
      default: null,
    },
  },

  computed: {
    FA() {
      return {
        faLink,
      };
    },
  },
};
</script>
