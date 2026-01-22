import {Duration} from 'luxon';

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
};
