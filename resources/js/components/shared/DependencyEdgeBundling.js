export default {
  /**
   * Dependency edge bundling chart for d3.js
   *
   * Usage:
   * const chart = DependencyEdgeBundling.initChart();
   * d3.select('#chart_placeholder')
   *   .datum(data)
   *   .call(chart);
   */
  initChart: function(options) {

    var diameter ;
    var radius ;
    var textRadius ;
    var innerRadius = radius - textRadius;
    var txtLinkGap = 5;
    var _mouseOvered, _mouseOuted;

    function resetDimension(){
      radius = diameter / 2;
      innerRadius = radius - textRadius;
    }

    // construct the package hierarchy by group
    var packageHierarchy = function (classes) {
      var map = {};
      function setparent(name, data) {
        var node = map[name];
        if (!node) {
          node = map[name] = data || {name: name, children: []};
          if (name.length) {
            if (data && data.group){
              node.parent = setparent(data.group, null);
              node.parent.children.push(node);
            }
            else {
              node.parent = map[""];
              node.parent.children.push(node);
            }
            node.key = name;
          }
        }
        return node;
      }
      setparent("",null);
      classes.forEach(function(d) {
        setparent(d.name, d);
      });

      return map[""];
    }

    // Return a list of depends for the given array of nodes.
    var packageDepends = function (nodes) {
      var map = {},
          depends = [];

      // Compute a map from name to node.
      nodes.forEach(function(d) {
        map[d.name] = d;
      });

      // For each dependency, construct a link from the source to target node.
      nodes.forEach(function(d) {
        if (d.depends) d.depends.forEach(function(i) {
          depends.push({source: map[d.name], target: map[i]});
        });
      });

      return depends;
    }

    function chart(selection) {
      selection.each(function(data) {
        // logic to set the size of the svg graph based on input
        var item=0, maxLength=0, length=0, maxItem;
        for (item in data){
          length = data[item].name.length;
          if (maxLength < length)
            {
              maxLength = length;
              maxItem = data[item].name;
            }
        }
        var minTextWidth = 7.4;
        var radialTextHeight = 9.8;
        var minTextRadius = Math.ceil(maxLength * minTextWidth);
        var minInnerRadius = Math.ceil((radialTextHeight * data.length)/2/Math.PI);
        if (minInnerRadius < 140)
          {
            minInnerRadius = 140;
          }
        var minDiameter = 2 * (minTextRadius + minInnerRadius + txtLinkGap + 2);
        diameter = minDiameter;
        textRadius = minTextRadius;
        resetDimension();
        var root = data;
        // create the layout
        var cluster =  d3.layout.cluster()
          .size([360, innerRadius])
          .sort(null)
          .value(function(d) {return d.size; });

        var bundle = d3.layout.bundle();

        var line = d3.svg.line.radial()
            .interpolate("bundle")
            .tension(.9)
            .radius(function(d) { return d.y; })
            .angle(function(d) { return d.x / 180 * Math.PI; });

        var svg = selection.insert("svg")
            .attr("width", diameter)
            .attr("height", diameter)
          .append("g")
            .attr("transform", "translate(" + radius + "," + radius + ")");

        // get all the link and node
        var link = svg.append("g").selectAll(".link"),
            node = svg.append("g").selectAll(".node");

        var nodes = cluster.nodes(packageHierarchy(root)),
            links = packageDepends(nodes);

        link = link
            .data(bundle(links))
          .enter().append("path")
            .each(function(d) { d.source = d[0], d.target = d[d.length - 1]; })
            .attr("class", "link")
            .attr("d", line);

        node = node
            .data(nodes.filter(function(n) { return !n.children; }))
          .enter().append("text")
            .attr("class", "node")
            .attr("dy", ".31em")
            .attr("transform", function(d) { return "rotate(" + (d.x - 90) + ")translate(" + (d.y + txtLinkGap) + ",0)" + (d.x < 180 ? "" : "rotate(180)"); })
            .style("text-anchor", function(d) { return d.x < 180 ? "start" : "end"; })
            .text(function(d) { return d.key; })
            .on("mouseover", mouseovered)
            .on("mouseout", mouseouted);

        function mouseovered(d) {

          node
              .each(function(n) { n.target = n.source = false; });

          link
              .classed("link--target", function(l) { if (l.target === d) return l.source.source = true; })
              .classed("link--source", function(l) { if (l.source === d) return l.target.target = true; })
            .filter(function(l) { return l.target === d || l.source === d; })
              .each(function() { this.parentNode.appendChild(this); });

          node
              .classed("node--target", function(n) { return n.target; })
              .classed("node--source", function(n) { return n.source; });

          _mouseOvered(d);
        }

        function mouseouted(d) {
          link
              .classed("link--target", false)
              .classed("link--source", false);

          node
              .classed("node--target", false)
              .classed("node--source", false)
              .text(function(d) {return d.key;});

          _mouseOuted(d);

        }

      });
    }

    chart.mouseOvered = function (d) {
      if (!arguments.length) return d;
      _mouseOvered = d;
      return chart;
    };

    chart.mouseOuted = function (d) {
      if (!arguments.length) return d;
      _mouseOuted = d;
      return chart;
    };

    return chart;
  },
};
