<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div>
      <span class="hint">
        This circle plot captures the interrelationships among subgroups. Mouse over any of the subgroup in this graph to see incoming links (dependents) in green and the outgoing links (dependencies) in red.
      </span>
      <span class="dropdown">
        <label
          for="selectedsort"
          class="dropdown-label"
        >
          Sorted by:
        </label>
        <select
          id="selectedsort"
          data-cy="select-sorting-order"
          @change="apply_sorting($event)"
        >
          <option
            value="0"
            selected="selected"
          >
            subproject name
          </option>
          <option value="1">subproject id</option>
        </select>
      </span>
    </div>
    <div class="text-center">
      <img
        v-if="graphLoading"
        :src="$baseURL + '/img/loading.gif'"
      >
      <div
        id="chart_placeholder"
        data-cy="svg-wrapper"
      />
    </div>
    <!-- Tooltip -->
    <div
      id="toolTip"
      class="tooltip"
      data-cy="tooltip"
    >
      <div
        id="header1"
        class="header"
        data-cy="tooltip-name-header"
      >
        Name: {{ nodeHeader }}
      </div>
      <div
        v-if="dependsList"
        id="dependency"
        class="dependency"
      >
        Depends: {{ dependsList }}
      </div>
      <div
        v-if="dependentsList"
        id="dependents"
        class="dependents"
      >
        Dependents: {{ dependentsList }}
      </div>
      <div
        id="tooltip-tail"
        class="tooltipTail"
      />
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import DependencyEdgeBundling from './shared/DependencyEdgeBundling.js';
export default {
  name: 'SubProjectDependencies',

  props: {
    projectName: {
      type: String,
      required: true,
    },
    date: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
      depData: {},
      graphLoading: true,
      chart: {},
      nodeHeader: false,
      dependsList: false,
      dependentsList: false,
    };
  },

  mounted () {
    ApiLoader.loadPageData(this, `/api/v1/getSubProjectDependencies.php?project=${encodeURIComponent(this.projectName)}&date=${this.date}`);
  },

  methods: {
    postSetup(response) {
      this.depData = response.data.dependencies;
      this.chart = DependencyEdgeBundling.initChart();
      this.plot_subdependencies();
    },

    plot_subdependencies() {

      const vm = this;
      vm.chart.mouseOvered(mouseOvered).mouseOuted(mouseOuted);

      function mouseOvered(d) {
        let header1Text = d.key;
        if (d.group !== undefined) {
          header1Text += `, Group: ${d.group}`;
        }
        vm.nodeHeader = header1Text;
        if (d.depends.length > 0) {
          vm.dependsList = d.depends.join(', ');
        }
        let dependents = '';
        d3.selectAll('.node--source').each((p) => {
          if (p.key) {
            dependents += `${p.key}, `;
          }
        });

        if (dependents) {
          vm.dependentsList = dependents.substring(0,dependents.length-2);
        }
        d3.select('#toolTip').style('left', `${d3.event.pageX + 40}px`)
          .style('top', `${d3.event.pageY + 5}px`)
          .style('opacity', '.9');
      }

      function mouseOuted(d) {
        $('#header1').text('');
        vm.nameHeader = false;
        vm.dependentsList = false;
        vm.dependsList = false;
        d3.select('#toolTip').style('opacity', '0');
      }

      vm.apply_sorting({ target: { value: 0 } }); // load the graph for the first time
    },

    // replot the graph after change (or on initial load)
    resetDepView() {
      const vm = this;
      vm.graphLoading = true;
      d3.select('#chart_placeholder svg').remove();
      d3.select('#chart_placeholder')
        .datum(vm.depData)
        .call(vm.chart);
      vm.graphLoading = false;
    },

    // event listener to resort graph according to selection field
    apply_sorting(e) {
      const vm = this;
      const selected = e.target.value;
      if (parseInt(selected) === 1) {
        vm.depData.sort(sort_by_id);
      }
      else if (parseInt(selected) === 0) {
        vm.depData.sort(sort_by_name);
      }
      vm.resetDepView();

      function sort_by_name (a, b) {
        if (a.name < b.name) {
          return -1;
        }
        if (a.name > b.name) {
          return 1;
        }
        return 0;
      }

      function sort_by_id (a, b) {
        if (a.id < b.id) {
          return -1;
        }
        if (a.id > b.id) {
          return 1;
        }
        return 0;
      }

    },
  },
};
</script>

<style>
/* these can't be under a scoped style tag because they
   target elements generated by d3 that Vue doesn't track */
.node {
  font-weight: 400;
  font-size: 13px;
  fill: #888;
  opacity: 0.8;
}

.node:hover {
  fill: #000;
  cursor: hand;
  cursor: pointer;
}

.link {
  stroke: steelblue;
  stroke-opacity: .4;
  fill: none;
  pointer-events: none;
  opacity: 0.4;
}

.node:hover,
.node--source,
.node--target {
  fill: #000;
  font-weight: 700;
  opacity: 1;
}

.node--source {
  fill: #2ca02c;
}

.node--target {
  fill: #d62728;
}

.link--source,
.link--target {
  stroke-opacity: 1;
  stroke-width: 2px;
  opacity: 1;
}

.link--source {
  stroke: #d62728;
}

.link--target {
  stroke: #2ca02c;
}
</style>

<style scoped>
div.tooltip {
  text-align: left;
  pointer-events: none; /* 'none' tells the mouse to ignore the rectangle */
  font-family: arial,helvetica,sans-serif;
  position: absolute;
  font-size: 1.1em;
  padding: 10px;
  border-radius: 3px;
  background: rgba(255,255,255,0.9);
  color: #000;
  box-shadow: 0 1px 5px rgba(0,0,0,0.4);
  border: 1px solid rgba(200,200,200,0.85);
  z-index: 10000;
  opacity: 0;
}

div.tooltipTail {
  position: absolute;
  left: -7px;
  top: 12px;
  width: 7px;
  height: 13px;
  background: url("/img/tail_white.png") 50% 0%;
}

div.toolTipBody {
  position: absolute;
  height: 100px;
  width: 230px;
}

#toolTip .header {
  text-align: left;
  font-size: 14px;
  margin-bottom: 2px;
  color: #000;
  font-weight: 700;
}

div.dependency {
  color: #d62728;
}

div.dependents {
  color: #2ca02c;
}

p.dependency-list {
  opacity: 0;
}

div.header1{
  text-align: left;
  font-size: 12px;
  margin-bottom: 2px;
  color: black;
}

span.hint {
  top: 20px;
  left: 20px;
  font-size: 0.9em;
  width: 350px;
  color: #999;
  display: inline-block;
}

span.dropdown {
  float: right;
}

label.dropdown-label {
  margin-right: 10px;
}
</style>
