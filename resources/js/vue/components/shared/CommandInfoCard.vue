<template>
  <div>
    <loading-indicator :is-loading="!buildCommand">
      <div class="tw-font-bold tw-text-lg">
        Details
      </div>
      <table class="tw-table tw-table-sm">
        <tbody>
          <tr>
            <td class="tw-font-bold tw-w-px">
              Type
            </td>
            <td>{{ buildCommand.type }}</td>
          </tr>
          <tr v-if="buildCommand.target">
            <td class="tw-font-bold">
              Target
            </td>
            <td>{{ buildCommand.target.name }}</td>
          </tr>
          <tr>
            <td class="tw-font-bold">
              Start Time
            </td>
            <td>{{ commandStartTime }}</td>
          </tr>
          <tr>
            <td class="tw-font-bold">
              Duration
            </td>
            <td>{{ commandDuration }}</td>
          </tr>
          <tr v-if="buildCommand.language">
            <td class="tw-font-bold">
              Language
            </td>
            <td>{{ buildCommand.language }}</td>
          </tr>
          <tr v-if="buildCommand.config">
            <td class="tw-font-bold">
              Config
            </td>
            <td>{{ buildCommand.config }}</td>
          </tr>
          <tr v-if="buildCommand.source">
            <td class="tw-font-bold">
              Source
            </td>
            <td class="tw-font-mono">
              {{ buildCommand.source }}
            </td>
          </tr>
          <tr>
            <td class="tw-font-bold">
              Command
            </td>
            <td class="tw-font-mono">
              {{ buildCommand.command }}
            </td>
          </tr>
          <tr>
            <td class="tw-font-bold">
              Working Directory
            </td>
            <td class="tw-font-mono">
              {{ buildCommand.workingDirectory }}
            </td>
          </tr>
          <tr>
            <td class="tw-font-bold">
              Result
            </td>
            <td class="tw-font-mono">
              {{ buildCommand.result }}
            </td>
          </tr>
        </tbody>
      </table>
      <div class="tw-divider" />
      <div class="tw-font-bold tw-text-lg">
        Measurements
      </div>
      <table class="tw-table tw-table-sm">
        <tbody>
          <tr v-for="{ node: measurement } in buildCommand.measurements.edges">
            <td class="tw-font-bold tw-w-px">
              {{ measurement.name }}
            </td>
            <td>{{ addUnitsToSpecialMeasurements(measurement.name, measurement.value) }}</td>
          </tr>
        </tbody>
      </table>
      <template v-if="buildCommand.outputs.edges.length > 0">
        <div class="tw-divider" />
        <div class="tw-font-bold tw-text-lg">
          Output
        </div>
        <table class="tw-table tw-table-sm">
          <tbody>
            <tr v-for="{ node: output } in buildCommand.outputs.edges">
              <td class="tw-font-bold tw-w-px">
                {{ output.name }}
              </td>
              <td>{{ humanReadableMemory(output.size) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </loading-indicator>
  </div>
</template>

<script>
import gql from 'graphql-tag';
import LoadingIndicator from './LoadingIndicator.vue';
import { DateTime } from 'luxon';
import Utils from './Utils';

export default {
  components: {LoadingIndicator},
  props: {
    commandId: {
      type: Number,
      required: true,
    },
  },

  apollo: {
    buildCommand: {
      query: gql`
        query($id: ID) {
          buildCommand(id: $id) {
            id
            type
            startTime
            duration
            command
            workingDirectory
            result
            source
            language
            config
            target {
              id
              name
            }
            measurements(first: 10000) {
              edges {
                node {
                  id
                  name
                  value
                }
              }
            }
            outputs(first: 10000) {
              edges {
                node {
                  id
                  name
                  size
                }
              }
            }
          }
        }
      `,

      variables() {
        return {
          id: this.commandId,
        };
      },
    },
  },

  computed: {
    commandStartTime() {
      return DateTime.fromISO(this.buildCommand.startTime).toSQL();
    },

    commandDuration() {
      return Utils.formatDuration(this.buildCommand.duration);
    },
  },

  methods: {
    humanReadableMemory(inputInBytes) {
      if (!inputInBytes) {
        return '';
      }

      if (inputInBytes < 1024) {
        return `${inputInBytes} Bytes`;
      }
      else if (inputInBytes < 1024 ** 2) {
        return `${(inputInBytes / 1024).toFixed(2)} KiB`;
      }
      else if (inputInBytes < 1024 ** 3) {
        return `${(inputInBytes / (1024 ** 2)).toFixed(2)} MiB`;
      }
      else if (inputInBytes < 1024 ** 4) {
        return `${(inputInBytes / (1024 ** 3)).toFixed(2)} GiB`;
      }
      else {
        return `${(inputInBytes / (1024 ** 4)).toFixed(2)} TiB`;
      }
    },

    addUnitsToSpecialMeasurements(measurementName, measurementValue) {
      if (['BeforeHostMemoryUsed', 'AfterHostMemoryUsed'].includes(measurementName)) {
        return this.humanReadableMemory(measurementValue);
      }

      return measurementValue;
    },
  },
};
</script>
