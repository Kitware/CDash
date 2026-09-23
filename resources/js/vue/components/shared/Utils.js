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
};
