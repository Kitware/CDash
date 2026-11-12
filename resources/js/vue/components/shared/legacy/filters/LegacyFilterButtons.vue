<template>
  <!-- Margins stand in for the spaces between elements which Vue's templates leave out. -->
  <button
    class="btn btn-default btn-xs"
    style="margin-left: 0.25em;"
    data-test="filter-add-block"
    title="Add filter block. All items added to this sub-list will be combined into a single filter clause."
    @click="$emit('add-block')"
  >
    <img
      class="smallicon"
      src="img/nested_list.svg"
    >
  </button>
  <select
    v-if="count > 1 && index === 0"
    :value="filtercombine"
    style="margin-left: 0.25em;"
    data-test="filter-combine"
    @change="$emit('update:filtercombine', $event.target.value)"
  >
    <option value="and">
      and
    </option>
    <option value="or">
      or
    </option>
  </select>
  <span
    v-if="index > 0 && index < count - 1"
    style="margin-left: 0.25em;"
    data-test="filter-combine-label"
  >
    {{ filtercombine }}
  </span>
</template>

<script>
/**
 * The buttons after each top-level filter, and how the top-level filters are combined.  The way they
 * are combined is chosen after the first filter and repeated between the others.
 */
export default {
  name: 'LegacyFilterButtons',

  props: {
    /** The filter's position among the top-level filters. */
    index: {
      type: Number,
      required: true,
    },

    /** The number of top-level filters. */
    count: {
      type: Number,
      required: true,
    },

    filtercombine: {
      type: String,
      required: true,
    },
  },

  emits: {
    'add-block': () => true,
    'update:filtercombine': (filtercombine) => ['and', 'or'].includes(filtercombine),
  },
};
</script>
