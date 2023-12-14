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

    let diameter ;
    let radius ;
    let textRadius ;
    let innerRadius = radius - textRadius;
    const txtLinkGap = 5;
    let _mouseOvered, _mouseOuted;

    function resetDimension() {
      radius = diameter / 2;
      innerRadius = radius - textRadius;
    }

    // construct the package hierarchy by group
    const packageHierarchy = function (classes) {
      const map = {};
      function setparent(name, data) {
        let node = map[name];
        if (!node) {
          node = map[name] = data || {name: name, children: []};
          if (name.length) {
            if (data && data.group) {
              node.parent = setparent(data.group, null);
              node.parent.children.push(node);
            }
            else {
              node.parent = map[''];
              node.parent.children.push(node);
            }
            node.key = name;
          }
        }
        return node;
      }
      setparent('', null);
      classes.forEach((d) => {
        setparent(d.name, d);
      });

      return map[''];
    };

    // Return a list of depends for the given array of nodes.
    const packageDepends = function (nodes) {
      const map = {};
      const depends = [];

      // Compute a map from name to node.
      nodes.forEach((d) => {
        map[d.name] = d;
      });

      // For each dependency, construct a link from the source to target node.
      nodes.forEach((d) => {
        if (d.depends) {
          d.depends.forEach((i) => {
            depends.push({source: map[d.name], target: map[i]});
          });
        }
      });

      return depends;
    };

    function chart(selection) {
      selection.each((data) => {
        // logic to set the size of the svg graph based on input
        let item=0, maxLength=0, length=0, maxItem;
        for (item in data) {
          length = data[item].name.length;
          if (maxLength < length) {
            maxLength = length;
            maxItem = data[item].name;
          }
        }
        const minTextWidth = 7.4;
        const radialTextHeight = 9.8;
        const minTextRadius = Math.ceil(maxLength * minTextWidth);
        let minInnerRadius = Math.ceil((radialTextHeight * data.length)/2/Math.PI);
        if (minInnerRadius < 140) {
          minInnerRadius = 140;
        }
        const minDiameter = 2 * (minTextRadius + minInnerRadius + txtLinkGap + 2);
        diameter = minDiameter;
        textRadius = minTextRadius;
        resetDimension();
        const root = data;
        // create the layout
        const cluster =  d3.layout.cluster()
          .size([360, innerRadius])
          .sort(null)
          .value((d) => {
            return d.size;
          });

        const bundle = d3.layout.bundle();

        const line = d3.svg.line.radial()
          .interpolate('bundle')
          .tension(.9)
          .radius((d) => {
            return d.y;
          })
          .angle((d) => {
            return d.x / 180 * Math.PI;
          });

        const svg = selection.insert('svg')
          .attr('width', diameter)
          .attr('height', diameter)
          .append('g')
          .attr('transform', `translate(${radius},${radius})`);

        // get all the link and node
        let link = svg.append('g').selectAll('.link');
        let node = svg.append('g').selectAll('.node');

        const nodes = cluster.nodes(packageHierarchy(root));
        const links = packageDepends(nodes);

        link = link
          .data(bundle(links))
          .enter().append('path')
          .each((d) => {
            d.source = d[0], d.target = d[d.length - 1];
          })
          .attr('class', 'link')
          .attr('d', line);

        node = node
          .data(nodes.filter((n) => {
            return !n.children;
          }))
          .enter().append('text')
          .attr('class', 'node')
          .attr('dy', '.31em')
          .attr('transform', (d) => {
            return `rotate(${d.x - 90})translate(${d.y + txtLinkGap},0)${d.x < 180 ? '' : 'rotate(180)'}`;
          })
          .style('text-anchor', (d) => {
            return d.x < 180 ? 'start' : 'end';
          })
          .text((d) => {
            return d.key;
          })
          .on('mouseover', mouseovered)
          .on('mouseout', mouseouted);

        function mouseovered(d) {

          node
            .each((n) => {
              n.target = n.source = false;
            });

          link
            .classed('link--target', (l) => {
              if (l.target === d) {
                return l.source.source = true;
              }
            })
            .classed('link--source', (l) => {
              if (l.source === d) {
                return l.target.target = true;
              }
            })
            .filter((l) => {
              return l.target === d || l.source === d;
            })
            .each(function() {
              this.parentNode.appendChild(this);
            });

          node
            .classed('node--target', (n) => {
              return n.target;
            })
            .classed('node--source', (n) => {
              return n.source;
            });

          _mouseOvered(d);
        }

        function mouseouted(d) {
          link
            .classed('link--target', false)
            .classed('link--source', false);

          node
            .classed('node--target', false)
            .classed('node--source', false)
            .text((d) => {
              return d.key;
            });

          _mouseOuted(d);

        }

      });
    }

    chart.mouseOvered = function (d) {
      if (!arguments.length) {
        return d;
      }
      _mouseOvered = d;
      return chart;
    };

    chart.mouseOuted = function (d) {
      if (!arguments.length) {
        return d;
      }
      _mouseOuted = d;
      return chart;
    };

    return chart;
  },
};
