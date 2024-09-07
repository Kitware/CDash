<template>
  <div>
    <filter-group
      :type="filterType"
      :primary-record-name="primaryRecordName"
      :initial-filters="initialFilters"
      @changeFilters="filters => $emit('changeFilters', filters)"
    />
  </div>
</template>

<script>
import FilterGroup from './FilterGroup.vue';

export default {
  components: { FilterGroup },

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
  },
};
</script>
