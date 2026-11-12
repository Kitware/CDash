<template>
  <!-- Margins stand in for the spaces between elements which Vue's templates leave out. -->
  <label for="begin">Begin</label>
  <input
    id="begin"
    ref="begin"
    type="text"
    style="margin-left: 0.25em;"
    name="begin"
    data-test="filter-begin"
    :value="begin"
    @input="$emit('update:begin', $event.target.value)"
  >
  <label
    for="end"
    style="margin-left: 0.25em;"
  >End</label>
  <input
    id="end"
    ref="end"
    type="text"
    style="margin-left: 0.25em;"
    name="end"
    data-test="filter-end"
    :value="end"
    @input="$emit('update:end', $event.target.value)"
  >
</template>

<script>
const DATE_FORMAT = 'yy-mm-dd';

/**
 * The first and last dates for the legacy filters, with jQuery UI date pickers.
 */
export default {
  name: 'DateRangeInputs',

  props: {
    begin: {
      type: String,
      default: '',
    },

    end: {
      type: String,
      default: '',
    },

    /** The date which the date pickers show first. */
    defaultDate: {
      type: String,
      default: '',
    },
  },

  emits: {
    'update:begin': (begin) => typeof begin === 'string',
    'update:end': (end) => typeof end === 'string',
  },

  async mounted() {
    const { default: $ } = await import('../jqueryUi');
    // Not reactive, since Vue doesn't need to track jQuery.
    this.jquery = $;

    const parseDate = (input) => {
      try {
        return $.datepicker.parseDate(DATE_FORMAT, input.value);
      } catch {
        return null;
      }
    };
    const options = {
      constrainInput: false,
      changeMonth: true,
      dateFormat: DATE_FORMAT,
      defaultDate: this.defaultDate,
    };

    // The date pickers set the inputs' values and trigger jQuery change events, which don't reach Vue's
    // listeners.  Typing also triggers change events once the inputs lose focus.
    const begin = $(this.$refs.begin);
    const end = $(this.$refs.end);
    begin.datepicker(options).on('change', () => {
      const date = parseDate(this.$refs.begin);
      if (date) {
        end.datepicker('option', 'minDate', date);
      }
      this.$emit('update:begin', this.$refs.begin.value);
    });
    end.datepicker(options).on('change', () => {
      const date = parseDate(this.$refs.end);
      if (date) {
        begin.datepicker('option', 'maxDate', date);
      }
      this.$emit('update:end', this.$refs.end.value);
    });
  },

  beforeUnmount() {
    this.jquery?.([this.$refs.begin, this.$refs.end]).datepicker('destroy');
  },
};
</script>
