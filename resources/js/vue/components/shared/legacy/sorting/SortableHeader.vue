<template>
  <th @click="$emit('sort', $event.shiftKey)">
    <slot />
    <!-- A margin stands in for the space before the icon, which Vue's templates leave out. -->
    <span
      class="glyphicon"
      style="margin-left: 0.25em;"
      :class="ICONS[direction]"
      :data-test="`sort-${direction}`"
    />
  </th>
</template>

<script>
const ICONS = {
  ascending: 'glyphicon-chevron-up',
  descending: 'glyphicon-chevron-down',
  none: 'glyphicon-none',
};

/**
 * A column header which sorts its table when clicked, and shift-clicked to sort by several columns.
 */
export default {
  name: 'SortableHeader',

  props: {
    direction: {
      type: String,
      required: true,
      validator: (direction) => direction in ICONS,
    },
  },

  emits: {
    /** Emitted with whether shift was held down, to sort by this column as well as the others. */
    sort: (append) => typeof append === 'boolean',
  },

  setup() {
    return { ICONS };
  },
};
</script>
