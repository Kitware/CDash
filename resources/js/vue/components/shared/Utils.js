import { DateTime, Duration } from 'luxon';

export default {
  formatDuration(ms) {
    if (ms < 1000) {
      return `${ms}ms`;
    }
    if (ms < 60000) {
      return `${(ms / 1000).toFixed(2)}s`;
    }
    const duration = Duration.fromMillis(ms);
    // Use Luxon's toFormat which intelligently omits larger units if they are zero.
    return duration.toFormat("m'm' ss's'");
  },

  /**
   * If the build started sometime in the last month, display a relative timestamp.
   * Otherwise, display a shortened version of the full date string.
   */
  formatRelativeTimestamp(iso8601TimestampString) {
    const startTime = DateTime.fromISO(iso8601TimestampString);
    if (startTime < DateTime.now().minus({ months: 1 })) {
      return startTime.toLocaleString(DateTime.DATE_MED);
    } else {
      return startTime.toRelative();
    }
  },

  /** Formats a byte count to the largest useful binary unit (Bytes/KiB/MiB/GiB/TiB). */
  formatBytes(bytes) {
    if (bytes < 1024) {
      return `${bytes} Bytes`;
    } else if (bytes < 1024 ** 2) {
      return `${(bytes / 1024).toFixed(2)} KiB`;
    } else if (bytes < 1024 ** 3) {
      return `${(bytes / (1024 ** 2)).toFixed(2)} MiB`;
    } else if (bytes < 1024 ** 4) {
      return `${(bytes / (1024 ** 3)).toFixed(2)} GiB`;
    }
    return `${(bytes / (1024 ** 4)).toFixed(2)} TiB`;
  },

  /** Formats a value already in KiB to the largest useful binary unit. */
  formatBytesFromKib(kib) {
    return this.formatBytes(kib * 1024);
  },

  /** Formats a value already in MiB to the largest useful binary unit. */
  formatBytesFromMib(mib) {
    return this.formatBytes(mib * (1024 ** 2));
  },

  /**
   * Returns the numeric measurements (those with a numeric/* type) from a list of GraphQL test or
   * build command measurements, as { name, value } objects with a number value.  Numeric measurements
   * whose value can't be parsed as a finite number are skipped.
   */
  numericMeasurements(measurements) {
    return measurements
      .filter((measurement) => measurement.type.startsWith('numeric'))
      .map((measurement) => ({ name: measurement.name, value: parseFloat(measurement.value) }))
      .filter((measurement) => Number.isFinite(measurement.value));
  },

  /**
   * Formats a numeric test or build command measurement in its base unit, scaled to
   * the largest useful unit. Measurements with no known unit are shown as a plain number.
   */
  formatMeasurement(name, value) {
    // Known numeric CTest/instrumentation measurements and their base unit, keyed by
    // measurement name. Measurements not listed here (e.g. Processors, a CPU load
    // average, or a project-defined custom measurement) are shown as a plain number.
    const KIB_MEMORY_MEASUREMENTS = new Set(['MaxRSS', 'AfterHostMemoryUsed', 'BeforeHostMemoryUsed']);
    const MICROSECOND_TIME_MEASUREMENTS = new Set(['UserTime', 'SystemTime']);
    const SECOND_TIME_MEASUREMENTS = new Set(['Execution Time']);

    if (!Number.isFinite(value)) {
      return String(value);
    }
    if (KIB_MEMORY_MEASUREMENTS.has(name)) {
      return this.formatBytesFromKib(value);
    }
    if (MICROSECOND_TIME_MEASUREMENTS.has(name)) {
      return this.formatDuration(value / 1000);
    }
    if (SECOND_TIME_MEASUREMENTS.has(name)) {
      return this.formatDuration(value * 1000);
    }
    return Number.isInteger(value) ? value.toLocaleString() : value.toFixed(2);
  },
};
