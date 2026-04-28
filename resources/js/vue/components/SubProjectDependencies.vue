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
      <loading-indicator :is-loading="graphLoading">
        <v-chart
          id="chart_placeholder"
          :option="chartOption"
          autoresize
          class="chart-container"
          @mouseover="onMouseOver"
          @mouseout="onMouseOut"
        />
      </loading-indicator>
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import VChart from 'vue-echarts';
import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { GraphChart } from 'echarts/charts';
import { LegendComponent } from 'echarts/components';

echarts.use([
  CanvasRenderer,
  GraphChart,
  LegendComponent,
]);

export default {
  name: 'SubProjectDependencies',

  components: {
    VChart,
    LoadingIndicator,
  },

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
      chartOption: {},
      hoveredNodeName: null,
    };
  },

  mounted () {
    ApiLoader.loadPageData(this, `/api/v1/getSubProjectDependencies.php?project=${encodeURIComponent(this.projectName)}&date=${this.date}`);
  },

  methods: {
    postSetup(response) {
      this.depData = response.data.dependencies;
      this.apply_sorting({ target: { value: 0 } });
      this.graphLoading = false;
    },

    onMouseOver(params) {
      if (params.dataType === 'node') {
        const d = params.data;
        if (this.hoveredNodeName === d.name) {
          return;
        }
        this.hoveredNodeName = d.name;

        this.highlightLinks(d.name);
      }
    },

    onMouseOut() {
      if (!this.hoveredNodeName) {
        return;
      }
      this.hoveredNodeName = null;
      this.chartOption = this.getChartOption(this.depData);
    },

    highlightLinks(nodeName) {
      const newOption = this.getChartOption(this.depData);
      const series = newOption.series[0];
      const links = series.links;
      const nodes = series.data;

      const sourceNodeNames = new Set();
      const targetNodeNames = new Set();

      links.forEach((link) => {
        if (link.source === nodeName) {
          // Outgoing - Red
          link.lineStyle = {
            color: '#d62728',
            opacity: 1,
            width: 4,
          };
          targetNodeNames.add(link.target);
        }
        else if (link.target === nodeName) {
          // Incoming - Green
          link.lineStyle = {
            color: '#2ca02c',
            opacity: 1,
            width: 4,
          };
          sourceNodeNames.add(link.source);
        }
        else {
          link.lineStyle = {
            color: '#ccc',
            opacity: 0.05,
          };
        }
      });

      nodes.forEach((node) => {
        if (node.name === nodeName) {
          node.itemStyle = {
            opacity: 1,
          };
          node.label = {
            fontWeight: 'bold',
            color: '#000',
          };
        }
        else if (sourceNodeNames.has(node.name)) {
          // Dependent (Incoming source)
          node.itemStyle = {
            color: '#2ca02c',
            opacity: 1,
          };
          node.label = {
            fontWeight: 'bold',
            color: '#000',
          };
        }
        else if (targetNodeNames.has(node.name)) {
          // Dependency (Outgoing target)
          node.itemStyle = {
            color: '#d62728',
            opacity: 1,
          };
          node.label = {
            fontWeight: 'bold',
            color: '#000',
          };
        }
        else {
          node.itemStyle = {
            opacity: 0.1,
          };
          node.label = {
            color: '#ccc',
          };
        }
      });
      this.chartOption = newOption;
    },

    // event listener to resort graph according to selection field
    apply_sorting(e) {
      const selected = e.target.value;
      if (parseInt(selected) === 1) {
        this.depData.sort(this.sort_by_id);
      }
      else if (parseInt(selected) === 0) {
        this.depData.sort(this.sort_by_name);
      }
      this.chartOption = this.getChartOption(this.depData);
    },

    getChartOption(data) {
      const nodes = [];
      const links = [];
      const categories = [];
      const categoryMap = {};

      // Grouping logic
      data.forEach(item => {
        if (item.group && !categoryMap[item.group]) {
          categoryMap[item.group] = categories.length;
          categories.push({ name: item.group });
        }
      });

      data.forEach(item => {
        nodes.push({
          name: item.name,
          category: item.group ? categoryMap[item.group] : undefined,
          value: item.name,
        });
      });

      data.forEach(item => {
        if (item.depends) {
          item.depends.forEach(dep => {
            links.push({
              source: item.name,
              target: dep,
            });
          });
        }
      });

      return {
        series: [
          {
            type: 'graph',
            layout: 'circular',
            circular: {
              rotateLabel: true,
            },
            data: nodes,
            links: links,
            categories: categories,
            roam: false,
            zoom: 1,
            label: {
              show: true,
              position: 'right',
              formatter: '{b}',
              fontSize: 13,
              color: '#888',
            },
            itemStyle: {
              opacity: 0.8,
            },
            lineStyle: {
              curveness: 0.3,
              opacity: 0.3,
              color: '#ccc',
            },
            edgeSymbol: ['none', 'arrow'],
            edgeSymbolSize: [0, 8],
            emphasis: {
              focus: 'none',
              scale: false,
              label: {
                fontWeight: 'bold',
                color: '#000',
              },
              lineStyle: {
                width: 3,
                opacity: 1,
              },
            },
          },
        ],
      };
    },

    sort_by_name(a, b) {
      if (a.name < b.name) {
        return -1;
      }
      if (a.name > b.name) {
        return 1;
      }
      return 0;
    },

    sort_by_id(a, b) {
      if (a.id < b.id) {
        return -1;
      }
      if (a.id > b.id) {
        return 1;
      }
      return 0;
    },
  },
};
</script>

<style scoped>
.chart-container {
  height: 800px;
  width: 100%;
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
