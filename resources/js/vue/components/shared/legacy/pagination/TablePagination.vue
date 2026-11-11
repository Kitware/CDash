<template>
  <ul class="pagination">
    <li
      class="pagination-first"
      :class="{ disabled: page === 1 }"
    >
      <a
        href="#"
        @click.prevent="select(1)"
      >First</a>
    </li>
    <li
      class="pagination-prev"
      :class="{ disabled: page === 1 }"
    >
      <a
        href="#"
        @click.prevent="select(page - 1)"
      >Previous</a>
    </li>
    <li
      v-for="number in pageNumbers"
      :key="number"
      class="pagination-page"
      :class="{ active: number === page }"
    >
      <a
        href="#"
        @click.prevent="select(number)"
      >{{ number }}</a>
    </li>
    <li
      class="pagination-next"
      :class="{ disabled: page === pageCount }"
    >
      <a
        href="#"
        @click.prevent="select(page + 1)"
      >Next</a>
    </li>
    <li
      class="pagination-last"
      :class="{ disabled: page === pageCount }"
    >
      <a
        href="#"
        @click.prevent="select(pageCount)"
      >Last</a>
    </li>
  </ul>
</template>

<script>
/**
 * Links to the pages of a table.  At most five page numbers are shown, centered on the current page.
 */
export default {
  name: 'TablePagination',

  props: {
    /** The current page, starting from 1. */
    page: {
      type: Number,
      required: true,
    },

    pageCount: {
      type: Number,
      required: true,
    },
  },

  emits: {
    'update:page': (page) => Number.isInteger(page),
  },

  computed: {
    pageNumbers() {
      const maxSize = 5;
      let start = Math.max(this.page - Math.floor(maxSize / 2), 1);
      const end = Math.min(start + maxSize - 1, this.pageCount);
      start = Math.max(end - maxSize + 1, 1);
      const numbers = [];
      for (let number = start; number <= end; number++) {
        numbers.push(number);
      }
      return numbers;
    },
  },

  methods: {
    select(page) {
      if (page >= 1 && page <= this.pageCount && page !== this.page) {
        this.$emit('update:page', page);
      }
    },
  },
};
</script>
