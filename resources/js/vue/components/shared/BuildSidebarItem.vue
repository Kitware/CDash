<template>
  <component
    :is="disabled ? 'div' : 'a'"
    :href="disabled ? undefined : href"
    class="tw-flex tw-flex-col tw-items-center tw-justify-center tw-p-2 tw-transition-colors tw-text-base-content"
    :class="{
      'tw-bg-base-300': selected && !disabled,
      'hover:tw-bg-base-300 tw-cursor-pointer': !disabled && !selected,
      'tw-opacity-40 tw-cursor-not-allowed': disabled,
    }"
    :title="title"
  >
    <div class="tw-relative">
      <font-awesome-icon
        :icon="icon"
        class="tw-w-5 tw-h-5 tw-mb-1"
      />
      <div
        v-if="badges && badges.length > 0"
        class="tw-absolute -tw-top-1.5 -tw-right-5 tw-flex tw-flex-col tw-gap-0.5 tw-items-center"
      >
        <div
          v-for="(badge, i) in badges"
          :key="i"
          class="tw-flex tw-items-center tw-justify-center tw-min-w-[1rem] tw-h-3.5 tw-px-1 tw-text-[8px] tw-font-bold tw-rounded-full tw-gap-0.5"
          :class="[badge.colorClass, badge.textClass || 'tw-text-white']"
        >
          <font-awesome-icon
            v-if="badge.icon"
            :icon="badge.icon"
          />
          <span>{{ badge.count }}</span>
        </div>
      </div>
    </div>
    <span
      class="tw-text-[10px] tw-text-center tw-leading-tight"
      :class="{'tw-font-bold': selected && !disabled}"
    >
      {{ title }}
    </span>
  </component>
</template>

<script>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

export default {
  name: 'BuildSidebarItem',
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
      required: true,
    },
    selected: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    badges: {
      type: Array,
      default: () => [],
    },
  },
};
</script>
