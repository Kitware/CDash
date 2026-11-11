<template>
  <select
    :id="`id_field${index + 1}`"
    :name="`field${index + 1}`"
    :value="filter.field"
    data-test="filter-field"
    @change="$emit('update', { field: $event.target.value })"
  >
    <option
      v-for="availableFilter in availableFilters"
      :key="availableFilter"
      :value="availableFilter"
    >
      {{ FILTER_DEFINITIONS[availableFilter].text }}
    </option>
  </select>

  &nbsp;

  <select
    :id="`id_compare${index + 1}`"
    :name="`compare${index + 1}`"
    :value="filter.compare"
    data-test="filter-compare"
    @change="$emit('update', { compare: $event.target.value })"
  >
    <option value="0">
      -- choose comparison --
    </option>
    <option
      v-for="comparison in comparisons"
      :key="comparison.value"
      :value="String(comparison.value)"
    >
      {{ comparison.text }}
    </option>
  </select>

  &nbsp;

  <input
    :id="`id_value${index + 1}`"
    type="text"
    size="60"
    :name="`value${index + 1}`"
    :value="filter.value"
    data-test="filter-value"
    @input="$emit('update', { value: $event.target.value })"
  >

  &nbsp;

  <button
    class="btn btn-default btn-xs"
    :name="`remove${index + 1}`"
    data-test="filter-remove"
    :disabled="!removable"
    @click="$emit('remove')"
  >
    <span class="smallicon glyphicon glyphicon-minus" />
  </button>
  <!-- Margins stand in for the spaces between elements which Vue's templates leave out. -->
  <button
    class="btn btn-default btn-xs"
    :name="`add${index + 1}`"
    style="margin-left: 0.25em;"
    data-test="filter-add"
    @click="$emit('add')"
  >
    <span class="smallicon glyphicon glyphicon-plus" />
  </button>
</template>

<script>
import { COMPARISONS, FILTER_DEFINITIONS } from './filterDefinitions';

export default {
  name: 'LegacyFilterRow',

  props: {
    /** @type {{field: string, compare: string, value: string}} */
    filter: {
      type: Object,
      required: true,
    },

    /** The filter's position in its list. */
    index: {
      type: Number,
      required: true,
    },

    /** The fields which can be filtered on. */
    availableFilters: {
      type: Array,
      required: true,
    },

    removable: {
      type: Boolean,
      required: true,
    },
  },

  emits: {
    /** Emitted with the changed properties of the filter. */
    update: (changes) => typeof changes === 'object',
    add: () => true,
    remove: () => true,
  },

  setup() {
    return { FILTER_DEFINITIONS };
  },

  computed: {
    comparisons() {
      return COMPARISONS[FILTER_DEFINITIONS[this.filter.field]?.type] ?? [];
    },
  },
};
</script>
