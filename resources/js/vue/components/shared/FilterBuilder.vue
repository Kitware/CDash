<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-1">
    <div
      class="table-heading1 tw-font-bold"
      style="font-size: 16px; padding: 6px;"
    >
      <font-awesome-icon :icon="FA.faFilter" /> Filters
    </div>
    <filter-group
      :type="filterType"
      :primary-record-name="primaryRecordName"
      :initial-filters="initialFilters"
      @change-filters="filters => $emit('changeFilters', filters)"
    />
    <div class="tw-flex tw-flex-row tw-w-full tw-gap-1">
      <a
        role="button"
        class="tw-btn tw-btn-xs"
        :href="executeQueryLink"
      >
        <font-awesome-icon :icon="FA.faMagnifyingGlass" /> Apply
      </a>
    </div>
  </div>
</template>

<script>
import FilterGroup from './FilterGroup.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {
  faMagnifyingGlass,
  faTerminal,
  faFilter,
} from '@fortawesome/free-solid-svg-icons';

export default {
  components: { FilterGroup, FontAwesomeIcon },

  props: {
    /**
     * The GraphQL input filter type.
     *
     * Example: QueryProjectsFiltersMultiFilterInput
     */
    filterType: {
      type: String,
      required: true,
    },

    /**
     * A human-readable string to provide context for the plural type of record being filtered.
     *
     * For example: "test measurements", "builds", "tests", etc...
     */
    primaryRecordName: {
      type: String,
      required: true,
    },

    initialFilters: {
      type: Object,
      default() {
        return {
          all: [],
        };
      },
    },

    /**
     * A link provided by the parent, presumably containing information this component has provided about
     * its current value.
     */
    executeQueryLink: {
      type: String,
      required: true,
    },
  },

  emits: [
    'changeFilters',
  ],

  computed: {
    FA() {
      return {
        faMagnifyingGlass,
        faTerminal,
        faFilter,
      };
    },
  },
};
</script>
