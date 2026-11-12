<template>
  <label>Items per page</label>
  <select
    :value="modelValue"
    data-test="items-per-page"
    @change="select(Number($event.target.value))"
  >
    <option
      v-for="option in options"
      :key="option"
      :value="option"
    >
      {{ option }}
    </option>
    <option :value="-1">
      All
    </option>
  </select>
</template>

<script>
import { setCookie } from '../cookies';

/**
 * Choose how many rows of a table to show on each page.  The choice is saved in a cookie for a year.
 * See savedNumPerPage() in paging.js.
 */
export default {
  name: 'ItemsPerPage',

  props: {
    /** The number of rows per page, or -1 for all of them. */
    modelValue: {
      type: Number,
      required: true,
    },

    /** The numbers of rows which can be chosen, apart from all of them. */
    options: {
      type: Array,
      required: true,
    },

    /** The cookie which remembers the choice. */
    cookie: {
      type: String,
      required: true,
    },
  },

  emits: {
    'update:modelValue': (numPerPage) => Number.isInteger(numPerPage),
  },

  methods: {
    select(numPerPage) {
      setCookie(this.cookie, numPerPage, { expires: 365 });
      this.$emit('update:modelValue', numPerPage);
    },
  },
};
</script>
