import {DateTime, Duration} from 'luxon';

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
    if (startTime < DateTime.now().minus({months: 1})) {
      return startTime.toLocaleString(DateTime.DATE_MED);
    }
    else {
      return startTime.toRelative();
    }
  },
};
