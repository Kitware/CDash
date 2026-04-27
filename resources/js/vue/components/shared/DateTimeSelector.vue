<template>
  <div class="tw-flex tw-flex-row tw-gap-1">
    <select
      v-model="month"
      class="tw-select tw-select-xs tw-select-bordered"
    >
      <option
        v-for="(name, index) in monthOptions"
        :key="index"
        :value="index + 1"
      >
        {{ name }}
      </option>
    </select>
    <select
      v-model="day"
      class="tw-select tw-select-xs tw-select-bordered"
    >
      <option
        v-for="d in dayOptions"
        :key="d"
        :value="d"
      >
        {{ d }}
      </option>
    </select>
    <select
      v-model="year"
      class="tw-select tw-select-xs tw-select-bordered"
    >
      <option
        v-for="y in yearOptions"
        :key="y"
        :value="y"
      >
        {{ y }}
      </option>
    </select>
    <select
      v-model="hour"
      class="tw-select tw-select-xs tw-select-bordered"
    >
      <option
        v-for="h in hourOptions"
        :key="h"
        :value="h"
      >
        {{ h.toString().padStart(2, '0') }}
      </option>
    </select>
    :
    <select
      v-model="minute"
      class="tw-select tw-select-xs tw-select-bordered"
    >
      <option
        v-for="m in minuteOptions"
        :key="m"
        :value="m"
      >
        {{ m.toString().padStart(2, '0') }}
      </option>
    </select>
    :
    <select
      v-model="second"
      class="tw-select tw-select-xs tw-select-bordered"
    >
      <option
        v-for="s in secondOptions"
        :key="s"
        :value="s"
      >
        {{ s.toString().padStart(2, '0') }}
      </option>
    </select>
    <select
      v-model="timezone"
      class="tw-select tw-select-xs tw-select-bordered"
    >
      <option
        v-for="tz in timezoneOptions"
        :key="tz.value"
        :value="tz.value"
      >
        {{ tz.text }}
      </option>
    </select>
  </div>
</template>

<script>
import { DateTime } from 'luxon';

export default {
  name: 'DateTimeSelector',
  props: {
    modelValue: {
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue'],
  data() {
    let dt = DateTime.fromISO(this.modelValue, { setZone: true });
    if (!dt.isValid) {
      dt = DateTime.now().toUTC();
    }
    return {
      year: dt.year,
      month: dt.month,
      day: dt.day,
      hour: dt.hour,
      minute: dt.minute,
      second: dt.second,
      timezone: this.getOffsetString(dt.offset / 60),
      lastEmittedValue: this.modelValue,
    };
  },
  computed: {
    yearOptions() {
      const currentYear = DateTime.now().year;
      const startYear = 1980;
      return Array.from({ length: currentYear - startYear + 1 }, (_, i) => currentYear - i);
    },
    monthOptions() {
      return [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
      ];
    },
    dayOptions() {
      const daysInMonth = DateTime.local(this.year, this.month).daysInMonth;
      return Array.from({ length: daysInMonth }, (_, i) => i + 1);
    },
    hourOptions() {
      return Array.from({ length: 24 }, (_, i) => i);
    },
    minuteOptions() {
      return Array.from({ length: 60 }, (_, i) => i);
    },
    secondOptions() {
      return Array.from({ length: 60 }, (_, i) => i);
    },
    timezoneOptions() {
      // https://en.wikipedia.org/wiki/List_of_UTC_offsets
      return Array.from({ length: 27 }, (_, i) => {
        const offsetHours = i - 12;
        const value = this.getOffsetString(offsetHours);
        const text = `UTC${DateTime.local().setZone(value).toFormat('Z')}`;
        return { value, text };
      });
    },
  },
  watch: {
    year() {
      this.clampDay();
      this.updateDateTime();
    },
    month() {
      this.clampDay();
      this.updateDateTime();
    },
    day: 'updateDateTime',
    hour: 'updateDateTime',
    minute: 'updateDateTime',
    second: 'updateDateTime',
    timezone: 'updateDateTime',
    modelValue(newValue) {
      if (newValue === this.lastEmittedValue) {
        return;
      }
      let dt = DateTime.fromISO(newValue, { setZone: true });
      const isValid = dt.isValid;
      if (!isValid) {
        dt = DateTime.now().toUTC();
      }
      this.year = dt.year;
      this.month = dt.month;
      this.day = dt.day;
      this.hour = dt.hour;
      this.minute = dt.minute;
      this.second = dt.second;
      this.timezone = this.getOffsetString(dt.offset / 60);

      if (isValid) {
        this.lastEmittedValue = dt.toISO({ suppressMilliseconds: true });
      }
    },
  },
  mounted() {
    this.updateDateTime();
  },
  methods: {
    getOffsetString(offsetHours) {
      return `UTC${offsetHours === 0 ? '' : (offsetHours > 0 ? '+' : '') + offsetHours}`;
    },
    clampDay() {
      const daysInMonth = DateTime.local(this.year, this.month).daysInMonth;
      if (this.day > daysInMonth) {
        this.day = daysInMonth;
      }
    },
    updateDateTime() {
      const newDateTime = DateTime.local(
        this.year,
        this.month,
        this.day,
        this.hour,
        this.minute,
        this.second,
        { zone: this.timezone },
      );
      if (newDateTime.isValid) {
        const isoString = newDateTime.toISO({ suppressMilliseconds: true });
        if (isoString !== this.lastEmittedValue) {
          this.lastEmittedValue = isoString;
          this.$emit('update:modelValue', isoString);
        }
      }
    },
  },
};
</script>
