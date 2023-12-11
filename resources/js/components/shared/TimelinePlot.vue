<template>
  <div ref="divRef">
    <div class="timeline-tooltip">
      <span>
        <b ref="tooltipRow1Axis" />: <text ref="tooltipRow1Value" />
      </span>
      <br>
      <span>
        <b ref="tooltipRow2Axis" />: <text ref="tooltipRow2Value" />
      </span>
    </div>
  </div>
</template>
<script>
import {ref, onMounted } from 'vue';
export default {
  name: 'TimelinePlot',
  /*
   * This template generates a line plot for a group of time-series datasets,
   * where each set is allowed to have a different number of data points.
   *
   * The expected input is of the form:
   *   plotData: [
   *   |   {
   *   |   | color: <line color>,  <--- d3 accepts most color formats (hex, rgb, etc.)
   *   |   | name: '<name to appear in legend>',
   *   |   | values: [
   *   |   |   {
   *   |   |   | x: <Date object>,
   *   |   |   | y: <numeric>,
   *   |   |   | url: '<url>'      <--- optional, renders clickable points if provided
   *   |   |   },
   *   |   |   ...
   *   |   | ],
   *   |   },
   *   |   ...
   *   | ],
   *   },
   *   title: '<plot title>',
   *   xLabel: '<x-axis label>',
   *   yLabel: '<y-axis label>'
   */

  props: {
    plotData: {
      type: Object,
      required: true,
      default() {
        return null;
      },
    },
    title: {
      type: String,
      required: true,
      default() {
        return 'Timeline Plot';
      },
    },
    xLabel: {
      type: String,
      required: true,
      default() {
        return 'Time';
      },
    },
    yLabel: {
      type: String,
      required: true,
      default() {
        return 'Value';
      },
    },
  },

  setup () {
    // initialize refs
    const divRef = ref(); // div element in which to render the plot
    const tooltipRow1Axis = ref();
    const tooltipRow1Value = ref();
    const tooltipRow2Axis = ref();
    const tooltipRow2Value = ref();
    onMounted(() => {
      divRef.value;
      tooltipRow1Axis.value;
      tooltipRow1Value.value;
      tooltipRow2Axis.value;
      tooltipRow2Value.value;
    });
  },

  mounted () {
    this.plot_timeline_graph();
  },

  methods: {
    plot_timeline_graph() {

      const plot_div = this.$refs.divRef;
      const data = this.plotData.map((d) => d.values);
      const colors = this.plotData.map((d) => d.color);
      const line_names = this.plotData.map((d) => d.name);
      const title = this.title;
      const xLabel = this.xLabel;
      const yLabel = this.yLabel;
      const tooltipRow1Axis = this.$refs.tooltipRow1Axis;
      const tooltipRow1Value = this.$refs.tooltipRow1Value;
      const tooltipRow2Axis = this.$refs.tooltipRow2Axis;
      const tooltipRow2Value = this.$refs.tooltipRow2Value;


      // ################## INPUT DATA (temporary) ##################
      // TODO: refactor all `.style()` calls to CSS sheet
      const legend_font_size = 18; // GIVEN
      const title_font_size = 22; // GIVEN

      // TODO: fit relative to page?
      // define plot size
      const margin = {
        top: 2*title_font_size,
        right: 30,
        bottom: 30,
        left: 60,
      };
      const width = 600 - margin.left - margin.right;
      const height = 400 - margin.top - margin.bottom;


      // ################## INITIALIZE PLOT ##################
      // create main svg object in which we draw the plot
      const svg = d3.select(plot_div)
        .append('svg')
        .attr({
          'width': width + margin.left + margin.right,
          'height': height + margin.top + margin.bottom,
        })
        .append('g')
        .attr('transform', `translate(${margin.left},${margin.top})`);

      // add title
      svg.append('text')
        .attr({
          'x': width/2,
          'y': -margin.top/2,
          'text-anchor': 'middle',
        })
        .text(title)
        .style('font-size', `${title_font_size}px`);;


      // ################## PLOT AXES ##################
      // find extent of the x-axis to make the domain fit the data

      // get list of min,max x values of each dataset
      const x_extents = data.map((d) => {
        return d3.extent(d.map((d) => d.x));
      });
      // pick max and min x values across all datasets
      const x_min = d3.min(x_extents, (d) => d[0]);
      const x_max = d3.max(x_extents, (d) => d[1]);
      // create x scale that maps dates to width in pixels
      const x = d3.time.scale()
        .domain([x_min, x_max])
        .range([0,width]);

      // plot x-axis
      const xAxis = svg.append('g')
        .attr({
          'class': 'x-axis',
          'transform': `translate(0,${height})`,
        })
        .call(d3.svg.axis().scale(x).orient('bottom'));
      // add x label
      xAxis.append('text')
        .attr({
          'x': width,
          'y': -legend_font_size/2,
        })
        .style('text-anchor', 'end')
        .text(xLabel)
        .style('font-size', `${legend_font_size}px`);

      // likewise, find extent of data on y-axis across all sets of points
      const y_extents = data.map((d) => {
        return d3.extent(d.map( (d) => d.y ));
      });
      const y_min = d3.min(y_extents, (d) => d[0]);
      const y_max = d3.max(y_extents, (d) => d[1]);
      // create y scale that maps data values to height in pixels
      const y = d3.scale.linear()
        .domain([y_min, y_max])
        .range([height,0]);
      // plot y-axis
      const yAxis = svg.append('g')
        .attr('class', 'y-axis')
        .call(d3.svg.axis().scale(y).orient('left'));
      // add y label
      yAxis.append('text')
        .attr({
          'transform': 'rotate(-90)',
          'y': legend_font_size/2,
          'dy': legend_font_size/2,
        })
        .style('text-anchor', 'end')
        .text(yLabel)
        .style('font-size', `${legend_font_size}px`);

      // define useful getters for location of point in the plot (in pixels)
      const getXRange = ((d) => x(d.x));
      const getYRange = ((d) => y(d.y));


      // ################## ADD BRUSHING ##################
      // add a clipPath object to restrict object to render only inside the plot
      const clip = svg.append('defs')
        .append('svg:clipPath')
        .attr('id', 'clip')
        .append('svg:rect')
        .attr({
          'width': width,
          'height': height,
          'x': 0,
          'y': 0,
        });

      // initialize the brush object
      const brush = d3.svg.brush()
        .x(x) // 1D brush only acts on the x-axis
        .extent(x.domain()) // initialise the brush area
        .on('brush', () => {
          d3.select('.extent')
            .attr('height',height)
            .style('fill','grey')
            .style('opacity', 0.25);
        })
        .on('brushend', () => {
          zoomOnSelection(); // trigger an update when brush selection changes
          d3.select('.extent')
            .attr('height',0)
            .style('opacity', 0); // remove gray area when done
        });
      svg.append('g')
        .attr('class', 'brush')
        .call(brush);
      // initialize some brush stuff as d3 refuses to do it for us.
      // TODO: this shouldn't be necessary :(
      d3.select('.resize w').select('rect').attr('height',height);
      d3.select('.resize e').select('rect').attr('height',height);
      d3.select('.background').attr('height',height);

      // general function to update the chart based on specified boundaries
      function updateChart(new_x1, new_x2, delta_t) {
        return function() {
          // update x-axis
          x.domain([new_x1, new_x2]);
          xAxis.transition().duration(delta_t)
            .call(d3.svg.axis().scale(x).orient('bottom'));
          for (let i = 0; i < data.length; i += 1) {
            // update line position
            plot_lines[i].transition().duration(delta_t)
              .attr('d', d3.svg.line().x(getXRange).y(getYRange));
            // update circle positions
            plot_points[i].transition().duration(delta_t)
              .attr({
                'cx': getXRange,
                'cy': getYRange,
              });
          }
        };
      }

      // zoom-in the plot using the coordinates of the brushed area
      function zoomOnSelection() {
        // get the boundaries of the selection
        const extent = d3.select('.extent');
        const coord1 = Number(extent.attr('x'));
        const coord2 = coord1 + Number(extent.attr('width'));

        if (coord2 - coord1 < 2) {
          return; // selection too small to be meaningfull (e.g. accidental mouse click)
        }
        else {
          updateChart(x.invert(coord1), x.invert(coord2), 700)();
        }
      }

      // on double-click, reinitialize the chart to original zoom level
      d3.select(plot_div).on('dblclick', updateChart(x_min, x_max, 200));


      // ################## ADD TOOLTIP ##################
      // select div which serves as the tooltip and define functions to update it
      const tooltip = d3.select('div .timeline-tooltip');

      // make tooltip visible when user hovers on a point
      function showTooltip(d,i) {
        d3.select(this).style('opacity', 1); // make circle appear
        tooltip.transition().duration(100).style('opacity', 1);
      }

      // load information about data point and display it in the tooltip div
      function renderTooltip(k) {
        return function(d,i) {
          // get x,y coordinates from the circle the user is hovering on
          const [x_val, y_val] = Object.values(d).map(String);
          // render the tooltip div element with this point's data
          tooltip // add slight offset for visiblity
            .style('left', `${d3.event.pageX + 15}px`)
            .style('top', `${d3.event.pageY - 10}px`);
          d3.select(tooltipRow1Axis).text(line_names[k]);
          d3.select(tooltipRow1Value).text(y_val);
          d3.select(tooltipRow2Axis).text(xLabel);
          d3.select(tooltipRow2Value).text(x_val);
        };
      }

      // make tooltip invisible when user mouse leaves point
      function hideTooltip(d,i) {
        d3.select(this).style('opacity', 0); // make cirle disappear
        tooltip.transition().duration(100).style('opacity', 0);
      }


      // ################## DRAW THE LINES ##################
      const plot_lines = [];
      const plot_points = [];
      for (let i = 0; i < data.length; i += 1) {
        const current_color = colors[i];

        // plot the line
        const line_i = svg.append('path')
          .datum(data[i])
          .attr({
            'fill': 'none',
            'stroke': current_color,
            'stroke-width': 1.5,
            'clip-path': 'url(#clip)',
            'd': d3.svg.line()
              .x(getXRange)
              .y(getYRange),
          });
        plot_lines.push(line_i);

        // draw indivudal data points that appear on hover
        let point_i = svg.append('g')
          .selectAll('dot')
          .data(data[i])
          .enter()
          .append('svg:a')
          .attr('clip-path', 'url(#clip)');
        if ('url' in data[i][0]) {
          // add a url to make the circle clickable, if given one
          point_i.attr('xlink:href', (d) => d.url);
        }
        point_i = point_i.append('circle')
          .attr({
            'class': 'my_circle',
            'cx': getXRange,
            'cy': getYRange,
            'r': 7,
            'stroke': current_color,
            'fill': 'white',
            'opacity': 0,
          })
          .on('mouseover', showTooltip)
          .on('mousemove', renderTooltip(i))
          .on('mouseleave', hideTooltip);
        plot_points.push(point_i);
      }


      // ################## ADD LEGEND ##################
      const numKeys = line_names.length;

      // calculate width of legend ahead of time so that it fits the text exactly
      // by creating a dummy element with the full texts and removing it immediately
      // TODO: this is not ideal, but text in SVG is difficult...
      const textWidths = [];
      svg.append('g')
        .selectAll('.getTextWidth')
        .data(line_names)
        .enter()
        .append('text')
        .attr('font-size', legend_font_size)
        .text((d) => d)
        .each(function(d,i) {
          const thisWidth = this.getComputedTextLength();
          textWidths.push(thisWidth);
          this.remove(); // remove the text just after displaying it
        });
      const maxKeyLen = Math.ceil(d3.max(textWidths));

      // calculate dimensions of the legend
      const legendRectSize = Math.max(18, legend_font_size); // size of square color swatch
      const legendSpacing = Math.ceil(legendRectSize/4); // add some minimal padding
      // calculate width, each row in the legend looks like: |space-swatch-space-key-space|
      const legendWidth = 3*legendSpacing + legendRectSize + maxKeyLen;
      // calculate height: there are numKeys rows, each with height legendRectSize
      //   and we want to put spacing before each row and also after the last row
      const legendHeight = numKeys*legendRectSize + (numKeys+1)*legendSpacing;

      // create new SVG group to gather all objects associated with the legend
      const legend = svg.append('g')
        .attr({
          'class': 'legend',
          'width': legendWidth,
          'height': legendHeight,
          'transform': `translate(${(width-legendWidth)},0)`,
        });
      // add SVG rect object to serve as our legend border
      const legend_box = legend.append('rect')
        .attr({
          'width': legendWidth,
          'height': legendHeight,
          'fill': 'white',
          'stroke': 'lightgrey',
          'opacity': 0.8,
        });

      // dim all lines but the selected one when hovering on a legend item
      function dimLines(_, i) {
        for (let j = 0; j < data.length; j++) {
          if (j !== i) {
            plot_lines[j].transition().duration(150).attr('opacity', 0.25);
          }
        }
      }
      // restore all lines to original opacity on mouse leave
      function undimLines(_, i) {
        for (let j = 0; j < data.length; j++) {
          plot_lines[j].transition().duration(150).attr('opacity', 1);
        }
      };

      // add rows to the legend for each dataset
      const legend_items = legend.selectAll('.legend-item')
        .data(line_names)
        .enter()
        .append('g')
        .attr('class', 'legend-item')
        .attr('transform', (d, i) => {
          // calculate the poisiton of each row with respect to
          // the origin of the rectangular border we added above
          const rowHeight = legendRectSize + legendSpacing;
          const horzDist = legendSpacing;
          const vertDist = i * rowHeight + legendSpacing;
          return `translate(${horzDist},${vertDist})`;
        })
        .on('mouseover', dimLines)
        .on('mouseleave', undimLines);
      // draw the color swatch for each row
      legend_items.append('rect')
        .attr({
          'width': legendRectSize,
          'height': legendRectSize,
        })
        .style('fill', (d,i) => colors[i]);
      // render the text associated with each dataset
      legend_items.append('text')
        .attr({
          'x': legendRectSize + legendSpacing,
          'y': legendRectSize - legendSpacing/2,
        })
        .text((d) => d )
        .style('font-size', `${legend_font_size}px`);

    },
  },
};
</script>
<style scoped>
div .timeline-tooltip {
  position: absolute;
  text-align: left;
  opacity: 0;
  background-color: white;
  border: solid;
  border-width: 1px;
  border-radius: 0.4em;
  padding: 0.4em;
}
</style>
