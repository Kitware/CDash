<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <build-summary-card :build-id="buildId" />

    <loading-indicator :is-loading="!configures">
      <div v-if="configures.length === 0">
        No configure found for this build.
      </div>
      <configure-card
        v-else-if="hasSingleConfigure"
        :return-value="configures[0].configure.returnValue"
        :log="configures[0].configure.log"
        :command="configures[0].configure.command"
      />
      <div
        v-else
        class="tw-join tw-join-vertical tw-w-full"
      >
        <details
          v-for="configure in configures"
          class="tw-collapse tw-collapse-plus tw-join-item tw-border"
          :data-test="'collapse-' + configure.subProject.id"
        >
          <summary class="tw-collapse-title tw-text-xl tw-font-medium">
            <span>{{ configure.subProject.name }}</span>
            <span
              v-if="configure.configureErrorsCount > 0"
              class="tw-badge tw-ml-2 tw-bg-red-400"
              :data-test="'errors-' + configure.subProject.id"
            ><font-awesome-icon :icon="FA.faCircleExclamation" /> {{ configure.configureErrorsCount }}</span>
            <span
              v-if="configure.configureWarningsCount > 0"
              class="tw-badge tw-ml-1 tw-bg-orange-400"
              :data-test="'warnings-' + configure.subProject.id"
            ><font-awesome-icon :icon="FA.faTriangleExclamation" /> {{ configure.configureWarningsCount }}</span>
          </summary>
          <div class="tw-collapse-content">
            <configure-card
              :return-value="configure.configure.returnValue"
              :log="configure.configure.log"
              :command="configure.configure.command"
            />
          </div>
        </details>
      </div>
    </loading-indicator>
  </div>
</template>

<script>
import gql from 'graphql-tag';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import ConfigureCard from './shared/ConfigureCard.vue';
import {
  faCircleExclamation,
  faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

export default {
  components: {FontAwesomeIcon, ConfigureCard, LoadingIndicator, BuildSummaryCard},
  props: {
    buildId: {
      type: Number,
      required: true,
    },
  },

  apollo: {
    configures: {
      query: gql`
        query($buildid: ID) {
          build(id: $buildid) {
            id
            configure {
              id
              command
              log
              returnValue
            }
            children(first: 100000) {
              edges {
                node {
                  id
                  configureWarningsCount
                  configureErrorsCount
                  configure {
                    id
                    command
                    log
                    returnValue
                  }
                  subProject {
                    id
                    name
                  }
                }
              }
            }
          }
        }
      `,
      update: (data) => {
        let configures = data.build.configure !== null ? [{configure: data.build.configure}] : [];
        data.build.children.edges.forEach((child) => {
          configures = configures.concat({
            ...child.node,
          });
        });
        return configures;
      },
      variables() {
        return {
          buildid: this.buildId,
        };
      },
    },
  },

  computed: {
    FA() {
      return {
        faCircleExclamation,
        faTriangleExclamation,
      };
    },

    hasSingleConfigure() {
      let childConfiguresAreSame = true;
      let firstConfigureId = null;
      this.configures.forEach((configure) => {
        if (firstConfigureId === null) {
          firstConfigureId = configure.configure.id;
          return;
        }

        if (firstConfigureId !== configure.configure.id) {
          childConfiguresAreSame = false;
        }
      });

      return childConfiguresAreSame;
    },
  },
};
</script>
