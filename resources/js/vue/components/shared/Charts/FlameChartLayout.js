/** The position of each field in a laid out item's ECharts `value` array. */
export const Dimension = Object.freeze({
  TRACK: 0,
  START_TIME: 1,
  END_TIME: 2,
  HEATMAP_VALUE: 3,
  ITEM_INDEX: 4,
});

export default {
  /**
   * Lays out timeline items for the FlameChart. Each item is placed on the first track
   * which is free at its start time, so concurrently running items are drawn on separate rows.
   *
   * @param {Array} items Items with Luxon DateTime `startTime` and Duration `duration` values,
   *                      and optional numeric `measurements` ({ name, value } objects).
   * @param {string} heatmapMeasurement The name of the measurement used to color items in heatmap mode.
   * @returns {{
   *   data: Array<{ value: Array }>,
   *   trackCount: number,
   *   overallStartTime: number,
   *   overallEndTime: number,
   *   heatmapRange: ?{ min: number, max: number },
   * }} ECharts series data, indexed by Dimension, and the extent of the times and heatmap values.
   */
  layout(items, heatmapMeasurement) {
    const trackEndTimes = [];
    let overallStartTime = 0;
    let overallEndTime = 0;
    let heatmapRange = null;

    const data = items
      .map((item, index) => {
        const startTime = item.startTime.toMillis();
        const heatmapValue = item.measurements?.find((measurement) => measurement.name === heatmapMeasurement)?.value;
        return {
          startTime,
          endTime: startTime + item.duration.toMillis(),
          heatmapValue: Number.isFinite(heatmapValue) ? heatmapValue : null,
          index,
        };
      })
      .sort((a, b) => a.startTime - b.startTime)
      .map(({ startTime, endTime, heatmapValue, index }, sortedIndex) => {
        let track = trackEndTimes.findIndex((trackEndTime) => startTime >= trackEndTime);
        if (track === -1) {
          track = trackEndTimes.length;
        }
        trackEndTimes[track] = endTime;

        overallStartTime = sortedIndex === 0 ? startTime : Math.min(overallStartTime, startTime);
        overallEndTime = sortedIndex === 0 ? endTime : Math.max(overallEndTime, endTime);
        if (heatmapValue !== null) {
          heatmapRange = {
            min: Math.min(heatmapRange?.min ?? heatmapValue, heatmapValue),
            max: Math.max(heatmapRange?.max ?? heatmapValue, heatmapValue),
          };
        }

        return {
          value: [track, startTime, endTime, heatmapValue, index],
        };
      });

    return {
      data,
      trackCount: trackEndTimes.length,
      overallStartTime,
      overallEndTime,
      heatmapRange,
    };
  },
};
