<template>
  <a
    id="cal"
    class="cdash-link"
    href="#"
    data-test="calendar-toggle"
    @click.prevent="toggle"
  >Calendar</a>
  <Teleport to="#header">
    <div
      id="calendar"
      ref="calendar"
      data-test="calendar"
      :style="{ display: visible ? 'block' : 'none' }"
    />
  </Teleport>
</template>

<script>
import { dateUrl } from './filters/filterUrls';

/**
 * The header's Calendar menu item, which shows a jQuery UI date picker to view the current page on
 * another day.  Mounted by the header on the pages which show it.
 */
export default {
  name: 'HeaderCalendar',

  data() {
    return {
      visible: false,
      initialized: false,
    };
  },

  methods: {
    async toggle() {
      if (!this.initialized) {
        this.initialized = true;
        const { default: $ } = await import('./jqueryUi');
        const options = {
          maxDate: '0D', // Restrict to the past.
          onSelect: (date) => this.select(date),
        };
        const date = new URLSearchParams(window.location.search).get('date');
        if (date) {
          // The date may be written as "20250115" or "2025-01-15".
          const year = date.substring(0, 4);
          const month = date.length === 8 ? date.substring(4, 6) : date.substring(5, 7);
          const day = date.length === 8 ? date.substring(6, 8) : date.substring(8, 10);
          options.defaultDate = new Date(`${month}/${day}/${year}`);
        }
        $(this.$refs.calendar).datepicker(options);
      }
      this.visible = !this.visible;
    },

    /**
     * @param {string} date The selected date, e.g. "01/15/2025".
     */
    select(date) {
      const isoDate = `${date.substring(6, 10)}-${date.substring(0, 2)}-${date.substring(3, 5)}`;
      window.location = dateUrl(window.location.href, isoDate);
      this.visible = false;
    },
  },
};
</script>
