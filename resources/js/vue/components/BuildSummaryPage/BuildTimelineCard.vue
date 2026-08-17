<template>
  <LoadingIndicator :is-loading="loading">
    <div
      v-if="build"
      class="tw-card tw-bg-white tw-border tw-border-base-300 tw-rounded-md tw-my-4 tw-overflow-visible"
      data-test="build-timeline"
    >
      <div class="tw-card-body tw-p-0">
        <!-- Desktop Timeline -->
        <div class="tw-hidden md:tw-block tw-relative tw-w-full tw-py-12 tw-px-12">
          <div class="tw-relative tw-w-full">
            <!-- Background Lines -->
            <div class="tw-absolute tw-top-1/2 tw-left-0 tw-right-0 tw-h-[2px] tw-transform -tw-translate-y-1/2 tw-flex tw-z-0">
              <div
                v-for="(step, index) in steps.slice(0, -1)"
                :key="index"
                class="tw-flex-1 tw-relative"
                :class="step.label === 'End' ? 'tw-bg-transparent tw-border-t-2 tw-border-dotted tw-border-neutral-content' : 'tw-bg-neutral-content'"
              />
            </div>

            <!-- Milestones -->
            <div class="tw-flex tw-justify-between tw-w-full tw-relative tw-z-10">
              <div
                v-for="(step, index) in steps"
                :key="index"
                class="tw-flex tw-flex-col tw-items-center tw-relative"
                data-test="timeline-step"
              >
                <!-- Timestamp & Duration (Top) -->
                <div class="tw-absolute tw-bottom-full tw-mb-2 tw-w-48 tw-text-center tw-flex tw-flex-col tw-items-center tw-justify-end">
                  <span
                    v-if="step.duration"
                    class="tw-text-xs tw-leading-tight tw-text-neutral-500 tw-block"
                    data-test="step-duration"
                  >
                    {{ step.duration }}
                  </span>
                  <span
                    v-if="step.time"
                    class="tw-text-xs tw-leading-tight tw-text-neutral-500 tw-block"
                    data-test="step-timestamp"
                  >
                    {{ step.time.date }}<br>
                    {{ step.time.time }}
                  </span>
                </div>

                <!-- Icon Container -->
                <component
                  :is="step.link ? 'a' : 'div'"
                  :href="step.link"
                  class="tw-bg-white tw-rounded-full tw-p-1 tw-flex tw-items-center tw-justify-center"
                  :class="step.link ? 'tw-link' : ''"
                  data-test="step-icon-container"
                >
                  <FontAwesomeIcon
                    :icon="step.icon"
                    :class="step.colorClass"
                    class="tw-text-base tw-block"
                    data-test="step-icon"
                  />
                </component>

                <!-- Label (Bottom) -->
                <div class="tw-absolute tw-top-full tw-mt-2 tw-w-32 tw-text-center">
                  <component
                    :is="step.link ? 'a' : 'span'"
                    :href="step.link"
                    class="tw-font-bold tw-text-sm tw-block"
                    :class="step.link ? 'tw-link tw-link-hover' : ''"
                    data-test="step-label"
                  >
                    {{ step.label }}
                  </component>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile Timeline -->
        <div class="md:tw-hidden tw-flex tw-flex-col tw-w-full tw-p-2">
          <div
            v-for="(step, index) in steps"
            :key="index"
            class="tw-flex tw-flex-row tw-items-start tw-relative"
            data-test="timeline-step-mobile"
          >
            <!-- Vertical Line -->
            <div
              v-if="index < steps.length - 1"
              class="tw-absolute tw-left-[12px] tw-top-[12px] tw-bottom-0 tw-w-[2px] tw-z-0"
              :class="step.label === 'End' ? 'tw-bg-transparent tw-border-l-2 tw-border-dotted tw-border-neutral-content' : 'tw-bg-neutral-content'"
            />

            <!-- Icon Container -->
            <div class="tw-relative tw-z-10 tw-bg-white tw-p-1 tw-mr-3 tw-flex-shrink-0">
              <component
                :is="step.link ? 'a' : 'div'"
                :href="step.link"
                class="tw-flex tw-items-center tw-justify-center"
                :class="step.link ? 'tw-link' : ''"
                data-test="step-icon-container-mobile"
              >
                <FontAwesomeIcon
                  :icon="step.icon"
                  :class="step.colorClass"
                  class="tw-text-base tw-block"
                  data-test="step-icon-mobile"
                />
              </component>
            </div>

            <!-- Content -->
            <div
              class="tw-flex tw-flex-col"
              :class="index < steps.length - 1 ? 'tw-pb-6' : ''"
            >
              <component
                :is="step.link ? 'a' : 'span'"
                :href="step.link"
                class="tw-font-bold tw-text-sm tw-block"
                :class="step.link ? 'tw-link tw-link-hover' : ''"
                data-test="step-label-mobile"
              >
                {{ step.label }}
              </component>
              <div
                v-if="step.duration"
                class="tw-text-xs tw-text-neutral-500"
                data-test="step-duration-mobile"
              >
                {{ step.duration }}
              </div>
              <div
                v-if="step.time"
                class="tw-text-xs tw-text-neutral-500"
                data-test="step-timestamp-mobile"
              >
                {{ step.time.date }}<br>
                {{ step.time.time }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </LoadingIndicator>
</template>

<script>
import {
  faCircleCheck,
  faCircleXmark,
  faCircleExclamation,
  faTriangleExclamation,
  faCircle,
  faPlay,
  faFlagCheckered,
  faFileArrowUp,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import LoadingIndicator from '../shared/LoadingIndicator.vue';
import gql from 'graphql-tag';
import { DateTime, Duration } from 'luxon';

export default {
  name: 'BuildTimelineCard',
  components: { LoadingIndicator, FontAwesomeIcon },

  props: {
    buildId: {
      type: [Number, String],
      required: true,
    },
  },

  data() {
    return {
      build: null,
      loading: true,
    };
  },

  apollo: {
    build: {
      query: gql`
        query BuildTimeline($buildId: ID!) {
          build(id: $buildId) {
            id
            startTime
            endTime
            submissionTime
            configureDuration
            buildDuration
            testDuration
            configureErrorsCount
            configureWarningsCount
            buildErrorsCount
            buildWarningsCount
            failedTestsCount
            notRunTestsCount
            passedTestsCount
          }
        }
      `,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
      result() {
        this.loading = false;
      },
      error() {
        this.loading = false;
      },
    },
  },

  computed: {
    FA() {
      return {
        faCircleCheck,
        faCircleXmark,
        faCircleExclamation,
        faTriangleExclamation,
        faCircle,
        faPlay,
        faFlagCheckered,
        faFileArrowUp,
      };
    },

    hasConfigure() {
      return this.build && this.build.configureErrorsCount > -1 && this.build.configureWarningsCount > -1;
    },

    hasBuild() {
      return this.build && this.build.buildErrorsCount > -1 && this.build.buildWarningsCount > -1;
    },

    hasTest() {
      return this.build && this.build.failedTestsCount > -1 && this.build.notRunTestsCount > -1 && this.build.passedTestsCount > -1;
    },

    configureStatus() {
      if (!this.hasConfigure) {
        return 'disabled';
      }
      if (this.build.configureErrorsCount > 0) {
        return 'error';
      }
      if (this.build.configureWarningsCount > 0) {
        return 'warning';
      }
      return 'success';
    },

    buildStatus() {
      if (!this.hasBuild) {
        return 'disabled';
      }
      if (this.build.buildErrorsCount > 0) {
        return 'error';
      }
      if (this.build.buildWarningsCount > 0) {
        return 'warning';
      }
      return 'success';
    },

    testStatus() {
      if (!this.hasTest) {
        return 'disabled';
      }
      if (this.build.failedTestsCount > 0) {
        return 'error';
      }
      if (this.build.notRunTestsCount > 0) {
        return 'warning';
      }
      return 'success';
    },

    configureIcon() {
      if (!this.hasConfigure) {
        return this.FA.faCircle;
      }
      if (this.configureStatus === 'error') {
        return this.FA.faCircleExclamation;
      }
      if (this.configureStatus === 'warning') {
        return this.FA.faTriangleExclamation;
      }
      return this.FA.faCircleCheck;
    },

    buildIcon() {
      if (!this.hasBuild) {
        return this.FA.faCircle;
      }
      if (this.buildStatus === 'error') {
        return this.FA.faCircleExclamation;
      }
      if (this.buildStatus === 'warning') {
        return this.FA.faTriangleExclamation;
      }
      return this.FA.faCircleCheck;
    },

    testIcon() {
      if (!this.hasTest) {
        return this.FA.faCircle;
      }
      if (this.testStatus === 'error') {
        return this.FA.faCircleXmark;
      }
      if (this.testStatus === 'warning') {
        return this.FA.faCircleExclamation;
      }
      return this.FA.faCircleCheck;
    },

    steps() {
      if (!this.build) {
        return [];
      }
      const steps = [];

      // Start
      steps.push({
        label: 'Start',
        time: this.formatTime(this.build.startTime),
        icon: this.FA.faPlay,
        colorClass: 'tw-text-neutral',
      });

      // Configure
      if (this.hasConfigure) {
        steps.push({
          label: 'Configure',
          icon: this.configureIcon,
          colorClass: this.statusColorClass(this.configureStatus),
          link: `${this.$baseURL}/builds/${this.buildId}/configure`,
          duration: this.formatDuration(this.build.configureDuration),
        });
      }

      // Build
      if (this.hasBuild) {
        steps.push({
          label: 'Build',
          icon: this.buildIcon,
          colorClass: this.statusColorClass(this.buildStatus),
          link: `${this.$baseURL}/builds/${this.buildId}/build`,
          duration: this.formatDuration(this.build.buildDuration),
        });
      }

      // Test
      if (this.hasTest) {
        steps.push({
          label: 'Test',
          icon: this.testIcon,
          colorClass: this.statusColorClass(this.testStatus),
          link: `${this.$baseURL}/builds/${this.buildId}/tests`,
          duration: this.formatDuration(this.build.testDuration),
        });
      }

      // End
      steps.push({
        label: 'End',
        time: this.formatTime(this.build.endTime),
        icon: this.FA.faFlagCheckered,
        colorClass: 'tw-text-neutral',
      });

      // Submit
      if (this.build.submissionTime) {
        steps.push({
          label: 'Submit',
          time: this.formatTime(this.build.submissionTime),
          icon: this.FA.faFileArrowUp,
          colorClass: 'tw-text-neutral',
        });
      }

      return steps;
    },
  },

  methods: {
    statusColorClass(status) {
      switch (status) {
        case 'error':
          return 'tw-text-error';
        case 'warning':
          return 'tw-text-warning';
        case 'success':
          return 'tw-text-success';
        default:
          return 'tw-text-neutral';
      }
    },

    formatTime(timestamp) {
      if (!timestamp) {
        return null;
      }
      const dt = DateTime.fromISO(timestamp);
      return {
        date: dt.toFormat('LLLL d, yyyy'),
        time: dt.toFormat('h:mm:ss a ZZZZ'),
      };
    },

    formatDuration(seconds) {
      if (seconds === undefined || seconds === null) {
        return '';
      }
      const absoluteSeconds = Math.max(0, Math.floor(seconds));
      if (absoluteSeconds === 0) {
        return '0 sec';
      }
      return Duration.fromObject({ seconds: absoluteSeconds }).rescale().toHuman({ unitDisplay: 'short' });
    },
  },
};
</script>
