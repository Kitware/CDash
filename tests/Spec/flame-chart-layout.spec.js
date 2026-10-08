import { describe, expect, it } from 'vitest';
import { DateTime, Duration } from 'luxon';
import FlameChartLayout, { Dimension } from '../../resources/js/vue/components/shared/Charts/FlameChartLayout';

const BASE_TIME = DateTime.fromISO('2026-01-01T00:00:00Z');

function item(startOffsetMs, durationMs, measurements = undefined) {
  return {
    startTime: BASE_TIME.plus({ milliseconds: startOffsetMs }),
    duration: Duration.fromMillis(durationMs),
    measurements,
  };
}

function values(layout, dimension) {
  return layout.data.map((datum) => datum.value[dimension]);
}

describe('FlameChartLayout', () => {
  describe('layout', () => {
    it('handles an empty list of items', () => {
      expect(FlameChartLayout.layout([], 'MaxRSS')).toEqual({
        data: [],
        trackCount: 0,
        overallStartTime: 0,
        overallEndTime: 0,
        heatmapRange: null,
      });
    });

    it('places sequential items on a single track', () => {
      const layout = FlameChartLayout.layout([item(0, 100), item(100, 50), item(200, 10)], 'MaxRSS');
      expect(layout.trackCount).toBe(1);
      expect(values(layout, Dimension.TRACK)).toEqual([0, 0, 0]);
    });

    it('places overlapping items on separate tracks and reuses freed tracks', () => {
      const layout = FlameChartLayout.layout([item(0, 100), item(50, 100), item(120, 10)], 'MaxRSS');
      expect(layout.trackCount).toBe(2);
      expect(values(layout, Dimension.TRACK)).toEqual([0, 1, 0]);
    });

    it('sorts by start time and records each item\'s original index', () => {
      const layout = FlameChartLayout.layout([item(200, 10), item(0, 10), item(100, 10)], 'MaxRSS');
      expect(values(layout, Dimension.ITEM_INDEX)).toEqual([1, 2, 0]);
      expect(values(layout, Dimension.START_TIME)).toEqual([0, 100, 200].map((offset) => BASE_TIME.toMillis() + offset));
    });

    it('computes the overall start and end times', () => {
      const layout = FlameChartLayout.layout([item(100, 500), item(0, 50), item(300, 50)], 'MaxRSS');
      expect(layout.overallStartTime).toBe(BASE_TIME.toMillis());
      expect(layout.overallEndTime).toBe(BASE_TIME.toMillis() + 600);
      expect(values(layout, Dimension.END_TIME)).toEqual([50, 600, 350].map((offset) => BASE_TIME.toMillis() + offset));
    });

    it('extracts the heatmap measurement and its range', () => {
      const layout = FlameChartLayout.layout([
        item(0, 10, [{ name: 'MaxRSS', value: 300 }, { name: 'UserTime', value: 1 }]),
        item(10, 10, [{ name: 'UserTime', value: 1000 }]),
        item(20, 10),
        item(30, 10, [{ name: 'MaxRSS', value: 100 }]),
        item(40, 10, [{ name: 'MaxRSS', value: NaN }]),
      ], 'MaxRSS');
      expect(values(layout, Dimension.HEATMAP_VALUE)).toEqual([300, null, null, 100, null]);
      expect(layout.heatmapRange).toEqual({ min: 100, max: 300 });
    });

    it('has no heatmap range when no item has the heatmap measurement', () => {
      const layout = FlameChartLayout.layout([item(0, 10, [{ name: 'UserTime', value: 1 }]), item(10, 10)], 'MaxRSS');
      expect(layout.heatmapRange).toBeNull();
    });
  });
});
