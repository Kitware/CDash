<template>
  <div>
    <filter-builder
      filter-type="QueryProjectsFiltersMultiFilterInput"
      primary-record-name="tests"
      :initial-filters="initialFilters"
      @changeFilters="filters => changedFilters = filters"
    />
    {{changedFilters}}
  </div>
</template>

<script>

import DataTable from './shared/DataTable.vue';
import gql from 'graphql-tag';
import { useQuery } from '@vue/apollo-composable';
import FilterBuilder from './shared/FilterBuilder.vue';

export default {
  name: 'BuildTestsPage',

  components: {
    FilterBuilder,
    DataTable,
  },

  props: {
    buildId: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      initialFilters: {any: [{ne: {name: 'abcd'}},{all: [{eq: {visibility: 'PROTECTED'}}]}]},
      changedFilters: [],
    };
  },
};
</script>
