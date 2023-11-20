<template>
  <div id="timeline_plot"></div>
</template>
<script>
export default {
  name: "TimelinePlot",
  props: {
    plotData: {
      type: Object,
      required: true,
      default: () => { return null }
    },
  },
  mounted() {
    this.plot_timeline_graph();
  },
  methods: {
    plot_timeline_graph() {
      /*
       * This template generates a line plot for a group of time-series datasets,
       * where each set is allowed to have a different number of data points.
       *
       * The expected input is of the form:
       *   plotData = {
       *   | data: [
       *   |   {
       *   |   | color: <line color>,  <--- d3 accepts most color formats (hex, rgb, etc.)
       *   |   | name: "<name to appear in legend>",
       *   |   | values: [
       *   |   |   {
       *   |   |   | x: <Date object>,
       *   |   |   | y: <numeric>,
       *   |   |   | url: "<url>"      <--- optional, render clickable points if provided
       *   |   |   },
       *   |   |   ...
       *   |   | ],
       *   |   },
       *   |   ...
       *   | ],
       *   | labels: {
       *   |   title: "<plot title>",
       *   |   x_axis: "<x-axis label>",
       *   |   y_axis: "<y-axis label>"
       *   | }
       *   }
       */

      const input = this.plotData;
      const data = input.data.map((d) => { return d['values'] });
      const colors = input.data.map((d) => { return d.color });
      const line_names = input.data.map((d) => { return d.name });
      const labels = input.labels;


      // ################## INPUT DATA (temporary) ##################
      // TODO: refactor all `.style()` calls to CSS sheet
      const legend_font_size = 18; // GIVEN
      const title_font_size = 22; // GIVEN

      // TODO: fit relative to page?
      // define plot size
      const margin = {top: 2*title_font_size, right: 30, bottom: 30, left: 60};
      const width = 600 - margin.left - margin.right;
      const height = 400 - margin.top - margin.bottom;


      // ################## INITIALIZE PLOT ##################
      // create main svg object in which we draw the plot
      let svg = d3.select("#timeline_plot")
        .append("svg")
          .attr("width", width + margin.left + margin.right)
          .attr("height", height + margin.top + margin.bottom)
          .attr("id", "main_timelineplot")
        .append("g")
          .attr("transform", `translate(${margin.left},${margin.top})`);

      // add title
      svg.append("text")
        .attr("x", width / 2)
        .attr("y", -margin.top/2)
        .attr("text-anchor", "middle")
        .text(labels.title)
        .style("font-size", title_font_size+"px");;


      // ################## PLOT AXES ##################
      // find extent of the x-axis to make the domain fit the data

      // get list of min,max x values of each dataset
      const x_extents = data.map((d) => {
        return d3.extent(d.map((d) => { return d.x }));
      });
      // pick max and min x values across all datasets
      const x_min = d3.min(x_extents, (d) => { return d[0] });
      const x_max = d3.max(x_extents, (d) => { return d[1] });
      // create x scale that maps dates to width in pixels
      let x = d3.time.scale()
        .domain([x_min, x_max])
        .range([0,width]);

      // plot x-axis
      let xAxis = svg.append("g")
        .attr("class","x-axis")
        .attr("transform", `translate(0,${height})`)
        .call(d3.svg.axis().scale(x).orient("bottom"));
      // add x label
      xAxis.append("text")
        .attr("x", width)
        .attr("y", -legend_font_size/2)
        .style("text-anchor", "end")
        .text(labels.x_axis)
        .style("font-size", legend_font_size+"px");

      // likewise, find extent of data on y-axis across all sets of points
      const y_extents = data.map((d) => {
        return d3.extent(d.map( (d) => { return d.y } ));
      });
      const y_min = d3.min(y_extents, (d) => { return d[0] });
      const y_max = d3.max(y_extents, (d) => { return d[1] });
      // create y scale that maps data values to height in pixels
      let y = d3.scale.linear()
        .domain([y_min, y_max])
        .range([height,0]);
      // plot y-axis
      let yAxis = svg.append("g")
        .attr("class", "y-axis")
        .call(d3.svg.axis().scale(y).orient("left"));
      // add y label
      yAxis.append("text")
        .attr("transform", "rotate(-90)")
        .attr("y", legend_font_size/2)
        .attr("dy", legend_font_size/2)
        .style("text-anchor", "end")
        .text(labels.y_axis)
        .style("font-size", legend_font_size+"px");

      // define useful getters for location of point in the plot (in pixels)
      const getXRange = ((d) => { return x(d.x) });
      const getYRange = ((d) => { return y(d.y) });


      // ################## ADD BRUSHING ##################
      // add a clipPath object to restrict object to render only inside the plot
      let clip = svg.append("defs")
        .append("svg:clipPath")
          .attr("id", "clip")
        .append("svg:rect")
          .attr("width", width )
          .attr("height", height )
          .attr("x", 0)
          .attr("y", 0);

      // initialize the brush object
      let brush = d3.svg.brush()
        .x(x) // 1D brush only acts on the x-axis
        .extent(x.domain()) // initialise the brush area
        .on("brush", () => {
          d3.select(".extent")
            .attr("height",height)
            .style("fill","grey")
            .style("opacity", "0.25");
        })
        .on("brushend", () => {
          zoomOnSelection(); // trigger an update when brush selection changes
          d3.select(".extent")
            .attr("height",0)
            .style("opacity", "0"); // remove gray area when done
        });
      svg.append("g")
        .attr("class", "brush")
        .call(brush);
      // initialize some brush stuff as d3 refuses to do it for us.
      // TODO: this shouldn't be necessary :(
      d3.select(".resize w").select("rect").attr("height",height);
      d3.select(".resize e").select("rect").attr("height",height);
      d3.select(".background").attr("height",height);

      // general function to update the chart based on specified boundaries
      function updateChart(new_x1, new_x2, delta_t) {
        return function() {
          // update x-axis
          x.domain([new_x1, new_x2]);
          xAxis.transition().duration(delta_t)
            .call(d3.svg.axis().scale(x).orient("bottom"));
          for (let i = 0; i < data.length; i += 1) {
            // update line position
            plot_lines[i].transition().duration(delta_t)
              .attr("d", d3.svg.line()
                .x(getXRange)
                .y(getYRange)
              );
            // update circle positions
            plot_points[i].transition().duration(delta_t)
              .attr("cx", getXRange)
              .attr("cy", getYRange);
          }
        };
      }

      // zoom-in the plot using the coordinates of the brushed area
      function zoomOnSelection() {
        // get the boundaries of the selection
        let extent = d3.select(".extent");
        let coord1 = Number(extent.attr("x"));
        let coord2 = coord1 + Number(extent.attr("width"));

        if (coord2 - coord1 < 2) {
          return; // selection too small to be meaningfull (e.g. accidental mouse click)
        } else {
          updateChart(x.invert(coord1), x.invert(coord2), 700)();
        }
      }

      // on double-click, reinitialize the chart to original zoom level
      d3.select("#timeline_plot").on("dblclick", updateChart(x_min, x_max, 200))


      // ################## ADD TOOLTIP ##################
      // add div which serves as the tooltip
      let tooltip = d3.select("#timeline_plot").append("div")
        .attr("class", "my_tooltip")
        .style("position", "absolute")
        .style("text-align", "left")
        .style("opacity", "0")
        .style("background-color", "white")
        .style("border", "solid")
        .style("border-width", "1px")
        .style("border-radius", "3px")
        .style("padding", "5px");

      // make tooltip visible when user hovers on a point
      function showTooltip(d,i) {
        d3.select(this).style("opacity", "1"); // make circle appear
        tooltip.transition().duration(100).style("opacity", "1");
      }

      // load information about data point and display it in the tooltip div
      function renderTooltip(k) {
        return function(d,i) {
          // get x,y coordinates from the circle the user is hovering on
          let [x_val, y_val] = Object.values(d).map(String);
          // render the html div element with this point's data
          let content =
            `<b>${line_names[k]}</b>: ${y_val}
            <br/>
            <b>${labels.x_axis}</b>: ${x_val}`;
          tooltip.html(content) // add slight offset for visiblity
            .style("left", (d3.event.pageX + 15)+"px")
            .style("top", (d3.event.pageY - 10)+"px");
        }
      }

      // make tooltip invisible when user mouse leaves point
      function hideTooltip(d,i) {
        d3.select(this).style("opacity", "0"); // make cirle disappear
        tooltip.transition().duration(100).style("opacity", "0");
      }


      // ################## DRAW THE LINES ##################
      let plot_lines = [];
      let plot_points = [];
      for (let i = 0; i < data.length; i += 1) {
        let current_color = colors[i];

        // plot the line
        let line_i = svg.append("path")
          .datum(data[i])
          .attr("fill", "none")
          .attr("stroke", current_color)
          .attr("stroke-width", 1.5)
          .attr("clip-path", "url(#clip)")
          .attr("d", d3.svg.line()
            .x(getXRange)
            .y(getYRange)
          );
        plot_lines.push(line_i);

        // draw indivudal data points that appear on hover
        let point_i = svg.append("g")
          .selectAll("dot")
          .data(data[i])
          .enter()
          .append("svg:a")
          .attr("clip-path", "url(#clip)");
        if ('url' in data[i][0]) {
          // add a url to make the circle clickable, if given one
          point_i.attr("xlink:href", (d) => { return d.url });
        }
        point_i = point_i.append("circle")
          .attr("class", "my_circle")
          .attr("cx", getXRange)
          .attr("cy", getYRange)
          .attr("r", 7)
          .attr("stroke", current_color)
          .attr("fill", "white")
          .attr("opacity", "0")
          .on("mouseover", showTooltip)
          .on("mousemove", renderTooltip(i))
          .on("mouseleave", hideTooltip);
        plot_points.push(point_i);
      }


      // ################## ADD LEGEND ##################
      const numKeys = line_names.length;

      // calculate width of legend ahead of time so that it fits the text exactly
      // by creating a dummy element with the full texts and removing it immediately
      // TODO: this is not ideal, but text in SVG is difficult...
      let textWidths = []
      svg.append('g')
        .selectAll('.getTextWidth')
        .data(line_names)
        .enter()
        .append("text")
          .attr("font-size", legend_font_size)
          .text((d) => { return d })
          .each(function(d,i) {
            let thisWidth = this.getComputedTextLength()
            textWidths.push(thisWidth)
            this.remove() // remove the text just after displaying it
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
      let legend = svg.append("g")
        .attr('class', 'legend')
        .attr("width", legendWidth)
        .attr("height", legendHeight)
        .attr("transform", `translate(${(width-legendWidth)},0)`);
      // add SVG rect object to serve as our legend border
      let legend_box = legend.append("rect")
        .attr("width", legendWidth)
        .attr("height", legendHeight)
        .attr("fill", "white")
        .attr("stroke", "lightgrey")
        .attr("opacity", 0.8);

      // dim all lines but the selected one when hovering on a legend item
      function dimLines(_, i) {
        for (let j = 0; j < data.length; j++) {
          if (j !== i) { plot_lines[j].transition().duration(150).attr("opacity", "0.25"); }
        }
      }
      // restore all lines to original opacity on mouse leave
      function undimLines(_, i) {
        for (let j = 0; j < data.length; j++) {
          plot_lines[j].transition().duration(150).attr("opacity", "1");
        }
      };

      // add rows to the legend for each dataset
      let legend_items = legend.selectAll('.legend-item')
        .data(line_names)
        .enter()
        .append('g')
        .attr('class', 'legend-item')
        .attr('transform', (d, i) => {
          // calculate the poisiton of each row with respect to
          // the origin of the rectangular border we added above
          let rowHeight = legendRectSize + legendSpacing;
          let horzDist = legendSpacing;
          let vertDist = i * rowHeight + legendSpacing;
          return `translate(${horzDist},${vertDist})`;
        })
        .on("mouseover", dimLines)
        .on("mouseleave", undimLines);
      // draw the color swatch for each row
      legend_items.append('rect')
        .attr('width', legendRectSize)
        .attr('height', legendRectSize)
        .style('fill', (d,i) => { return colors[i] });
      // render the text associated with each dataset
      legend_items.append('text')
        .attr('x', legendRectSize + legendSpacing)
        .attr('y', legendRectSize - legendSpacing/2)
        .text((d) => { return d })
        .style("font-size", legend_font_size+"px");

    }
  }
}
</script>
