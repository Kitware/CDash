<template>
  <div
    class="tw-flex tw-flex-wrap tw-items-center tw-gap-x-3 tw-gap-y-2"
    data-test="trend-filters"
  >
    <div
      class="tw-flex tw-flex-wrap tw-items-center tw-gap-1.5"
      role="group"
      aria-label="Builds to show"
    >
      <select
        v-for="choice in choices"
        :key="choice.key"
        class="tw-select tw-select-bordered tw-select-xs tw-rounded-full"
        :aria-label="choice.label"
        :title="choice.title"
        :value="modelValue[choice.key] ? 'this' : 'all'"
        :data-test="'trend-filter-' + choice.test"
        @change="update(choice.key, $event.target.value === 'this')"
      >
        <option value="this">
          {{ choice.thisOption }}
        </option>
        <option value="all">
          {{ choice.allOption }}
        </option>
      </select>
      <select
        class="tw-select tw-select-bordered tw-select-xs tw-rounded-full"
        aria-label="Number of builds"
        :value="modelValue.limit"
        data-test="trend-filter-limit"
        @change="update('limit', Number($event.target.value))"
      >
        <option
          v-for="limit in limits"
          :key="limit"
          :value="limit"
        >
          Last {{ limit }} builds
        </option>
      </select>
    </div>
    <!-- Controls which depend on the filters, such as a link to the builds they select. -->
    <slot />
  </div>
</template>

<script>
import { TREND_LIMITS } from './TrendFilters';

/**
 * Dropdowns which choose the builds a trend chart compares the current build with: builds from this
 * build's site or from all sites, and so on.  The current build's details can be very long, so they're
 * only shown in the dropdowns' tooltips.
 */
export default {
  name: 'TrendFilterBar',

  props: {
    /**
     * @type {import('./TrendFilters').TrendFilters}
     */
    modelValue: {
      type: Object,
      required: true,
    },

    /**
     * The current build's details which the dropdowns filter on.
     *
     * @type {{siteName: string, name: string, group: string}}
     */
    build: {
      type: Object,
      required: true,
    },

    /**
     * The filters to offer, of 'sameSite', 'sameName' and 'sameGroup'.
     */
    filters: {
      type: Array,
      default: () => ['sameSite', 'sameName', 'sameGroup'],
    },
  },

  emits: {
    'update:modelValue': (filters) => typeof filters === 'object',
  },

  data() {
    return {
      limits: TREND_LIMITS,
    };
  },

  computed: {
    choices() {
      return [
        {
          key: 'sameSite',
          test: 'site',
          label: 'Sites',
          thisOption: 'This site',
          allOption: 'All sites',
          thisTitle: `Builds from site ${this.build.siteName}`,
          allTitle: 'Builds from all sites',
        },
        {
          key: 'sameName',
          test: 'name',
          label: 'Build names',
          thisOption: 'This build name',
          allOption: 'All build names',
          thisTitle: `Builds named ${this.build.name}`,
          allTitle: 'Builds with any name',
        },
        {
          key: 'sameGroup',
          test: 'group',
          label: 'Groups',
          thisOption: 'This group',
          allOption: 'All groups',
          thisTitle: `Builds in the ${this.build.group} group`,
          allTitle: 'Builds in all groups',
        },
      ]
        .filter((choice) => this.filters.includes(choice.key))
        .map((choice) => ({
          ...choice,
          title: this.modelValue[choice.key] ? choice.thisTitle : choice.allTitle,
        }));
    },
  },

  methods: {
    update(key, value) {
      this.$emit('update:modelValue', {
        ...this.modelValue,
        [key]: value,
      });
    },
  },
};
</script>
