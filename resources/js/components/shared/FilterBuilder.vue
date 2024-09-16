<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-1">
    <div
      class="table-heading1 tw-font-bold"
      style="font-size: 16px; padding: 6px;"
    >
      <font-awesome-icon icon="fa-filter" /> Filters
    </div>
    <filter-group
      :type="filterType"
      :primary-record-name="primaryRecordName"
      :initial-filters="initialFilters"
      @changeFilters="filters => $emit('changeFilters', filters)"
    />
    <div class="tw-flex tw-flex-row tw-w-full tw-gap-1">
      <a
        role="button"
        class="tw-btn tw-btn-xs"
        :href="executeQueryLink"
      >
        <font-awesome-icon icon="fa-magnifying-glass" /> Apply
      </a>
      <a
        role="button"
        class="tw-btn tw-btn-xs"
        :href="$baseURL + '/graphql/explorer'"
        target="_blank"
        rel="noopener noreferrer"
      >
        <font-awesome-icon icon="fa-terminal" /> GraphQL
      </a>
    </div>
  </div>
</template>

<script>
import FilterGroup from './FilterGroup.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

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
};
</script>
