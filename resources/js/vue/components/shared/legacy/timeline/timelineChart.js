/**
 * Draw a timeline: a stacked area chart of a page's results on each testing day, such as the number of
 * builds with errors, warnings and neither, with a brush to select a range of days.
 *
 * Remove this once the timeline is drawn from GraphQL data with the charts the other Vue pages use.
 */

const DAY = 1000 * 3600 * 24;

/**
 * The time in a sorted list which is closest to a time.
 */
function closestTime(time, times) {
  let lo = 0;
  let hi = times.length - 1;
  while (hi - lo > 1) {
    const mid = Math.floor((lo + hi) / 2);
    if (times[mid] < time) {
      lo = mid;
    } else {
      hi = mid;
    }
  }
  return time - times[lo] <= times[hi] - time ? times[lo] : times[hi];
}

/**
 * The dates of the testing days in a selection.
 *
 * @param {Object} timeline The timeline API's response.
 * @param {number[]} extent The start of the first day and the end of the last day of the selection.
 * @returns {{begin: string, end: string}}
 */
export function selectedDates(timeline, extent) {
  const timestamps = Object.keys(timeline.time_to_date);

  // The extent should be on day boundaries, which are the keys of time_to_date.  Otherwise, fall back
  // to the timeline's first and last days.
  const begin = timestamps.includes(String(extent[0]))
    ? timeline.time_to_date[extent[0]]
    : timeline.time_to_date[timestamps[0]];

  // The end of the selection is the end of a testing day, which is the start of the next one.
  const idx = timestamps.indexOf(String(extent[1]));
  const end = idx > 0
    ? timeline.time_to_date[timestamps[idx - 1]]
    : timeline.time_to_date[timestamps[timestamps.length - 1]];

  return { begin, end };
}

/**
 * @param {Object} options
 * @param {Element} options.svg The element to draw the chart in.
 * @param {Object} options.timeline The timeline API's response.
 * @param {Object} options.d3 d3 v3.
 * @param {Object} options.nv nvd3.
 * @param {function({begin: string, end: string}): void} options.onSelect Called with the selected dates.
 */
export function drawTimelineChart({ svg, timeline, d3, nv, onSelect }) {
  const dayStarts = Object.keys(timeline.time_to_date).map(Number);
  let brush;

  nv.addGraph(() => {
    const chart = nv.models.stackedAreaChart()
      .x((d) => d[0])
      .y((d) => d[1])
      .interpolate('step-after')
      .margin({ top: 30, right: 60, bottom: 30, left: 60 })
      .rightAlignYAxis(false)
      .showControls(false)
      .showLegend(true)
      .showTotalInTooltip(false)
      .useInteractiveGuideline(true);

    if ('colors' in timeline) {
      chart.color(timeline.colors);
    }

    chart.xAxis.showMaxMin(false);
    const selection = d3.select(svg).datum(timeline.data);
    selection.call(chart);
    selection.select('.nv-axislabel')
      .style('font-size', '16')
      .style('font-weight', 'bold');

    chart.update();
    nv.utils.windowResize(chart.update);

    // Calculate how many ticks can comfortably fit on the X-axis.
    const bbox = selection.select('.nv-stackedAreaChart g rect').node().getBBox();
    const text = selection.append('text')
      .attr('class', 'nvd3')
      .text('2999-12-31')
      .style('visibility', 'hidden');
    const labelWidth = text.node().getBBox().width;
    text.remove();
    const numTicks = Math.floor(Math.round(bbox.width) / (Math.ceil(labelWidth) * 2));

    // Don't show the final tick, which is one day past the end of the range.
    const tickTimes = dayStarts.slice(0, -1);
    let tickValues = tickTimes;
    if (tickTimes.length > numTicks) {
      tickValues = [tickTimes[0]];
      const interval = tickTimes.length / numTicks;
      for (let i = 1; i < numTicks; i++) {
        tickValues.push(tickTimes[Math.round(i * interval)]);
      }
    }

    chart.xAxis
      .showMaxMin(false)
      .tickValues(tickValues)
      .tickFormat((d) => timeline.time_to_date[d]);
    chart.update();

    // d3 calls brushstart() before and after brushend().  Only hide the tooltips for the first call.
    let startBrushing = true;
    function brushstart() {
      if (!startBrushing) {
        startBrushing = true;
        return;
      }
      d3.select('.nvtooltip').style('display', 'none');
      startBrushing = false;
    }

    // Snap the selection to day boundaries.
    function brushend() {
      if (!d3.event.sourceEvent) {
        // Only respond to the user's input.
        return;
      }

      const extent = brush.extent();
      const newExtent = [closestTime(extent[0], dayStarts), closestTime(extent[1], dayStarts)];
      newExtent[0] = Math.max(newExtent[0], timeline.min);
      newExtent[1] = Math.min(newExtent[1], timeline.max);

      // Select at least one day.
      if (newExtent[0] === newExtent[1]) {
        if (extent[0] !== newExtent[0]) {
          newExtent[0] = newExtent[1] - DAY;
        } else if (extent[1] !== newExtent[1]) {
          newExtent[1] = newExtent[0] + DAY;
        }
      }

      if (newExtent[0] !== extent[0] || newExtent[1] !== extent[1]) {
        d3.select(this).transition()
          .duration(brush.empty() ? 0 : 750)
          .call(brush.extent(newExtent))
          .call(brush.event);
      }

      onSelect(selectedDates(timeline, brush.extent()));
      d3.select('.nvtooltip').style('display', 'block');
    }

    brush = d3.svg.brush()
      .x(chart.xScale())
      .extent([timeline.extentstart, timeline.extentend])
      .on('brushstart', brushstart)
      .on('brushend', brushend);

    selection.select('.nv-areaWrap')
      .append('g')
      .attr('class', 'brush')
      .call(brush)
      .selectAll('rect')
      .attr('height', bbox.height)
      .attr('fill-opacity', '.125')
      .attr('stroke', '#fff');

    onSelect(selectedDates(timeline, brush.extent()));

    return chart;
  });
}
