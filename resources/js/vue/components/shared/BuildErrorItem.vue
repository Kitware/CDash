<template>
  <div class="tw-flex tw-flex-col tw-gap-2">
    <div class="tw-font-bold">
      <span
        class="tw-mr-1"
        :class="buildError.type === 'ERROR' ? 'tw-text-error' : 'tw-text-warning'"
      >
        <font-awesome-icon :icon="buildError.type === 'ERROR' ? FA.faCircleExclamation : FA.faTriangleExclamation" />
      </span>
      {{ buildError.type === 'ERROR' ? 'Error' : 'Warning' }}
      <template v-if="buildError.outputFile">
        while building {{ buildError.language ?? '' }}
        {{ buildError.outputType ?? 'file' }}
        <span class="tw-font-mono">{{ buildError.outputFile }}</span>
      </template>
      <template v-else-if="buildError.sourceFile">
        in {{ buildError.language ?? '' }} file
        <span class="tw-font-mono">{{ buildError.sourceFile }}</span>
      </template>
      <template v-if="buildError.targetName">
        in target <span class="tw-font-mono">{{ buildError.targetName }}</span>
      </template>
    </div>

    <div v-if="buildError.stdError">
      <div
        class="tw-font-bold tw-cursor-pointer"
        @click="showStdError = !showStdError"
      >
        Standard Error <font-awesome-icon :icon="showStdError ? FA.faChevronDown : FA.faChevronRight" />
      </div>
      <code-box
        v-if="showStdError"
        :text="buildError.stdError"
      />
    </div>

    <div v-if="buildError.stdOutput">
      <div
        class="tw-font-bold tw-cursor-pointer"
        data-test="stdout"
        @click="showStdOutput = !showStdOutput"
      >
        Standard Output <font-awesome-icon :icon="showStdOutput ? FA.faChevronDown : FA.faChevronRight" />
      </div>
      <code-box
        v-if="showStdOutput"
        :text="buildError.stdOutput"
      />
    </div>

    <div v-if="buildError.command">
      <div
        class="tw-font-bold tw-cursor-pointer"
        @click="showCommand = !showCommand"
      >
        Command <font-awesome-icon :icon="showCommand ? FA.faChevronDown : FA.faChevronRight" />
      </div>
      <code-box
        v-if="showCommand"
        :text="buildError.command"
      />
    </div>

    <div
      v-if="buildError.workingDirectory"
      class="tw-flex tw-flex-row tw-gap-1"
    >
      <span class="tw-font-bold">Working Directory:</span>
      <span class="tw-font-mono">{{ buildError.workingDirectory }}</span>
    </div>

    <div
      v-if="buildError.exitCondition"
      class="tw-flex tw-flex-row tw-gap-1"
    >
      <span class="tw-font-bold">Return Value:</span>
      <span class="tw-font-mono">{{ buildError.exitCondition }}</span>
    </div>

    <div
      v-if="buildError.labels.edges.length > 0"
      class="tw-flex tw-flex-row tw-gap-1"
    >
      <span class="tw-font-bold">Labels:</span>
      <span
        v-for="{ node: label } in buildError.labels.edges"
        class="tw-badge tw-badge-outline tw-text-xs tw-text-neutral-500"
      >{{ label.text }}</span>
    </div>
  </div>
</template>

<script>
import CodeBox from './CodeBox.vue';
import {
  faChevronDown,
  faChevronRight,
  faCircleExclamation,
  faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

export default {
  components: {FontAwesomeIcon, CodeBox},

  props: {
    buildError: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      showStdOutput: this.shouldShowStringByDefault(this.buildError.stdOutput),
      showStdError: true,
      showCommand: false,
    };
  },

  computed: {
    FA() {
      return {
        faChevronDown,
        faChevronRight,
        faCircleExclamation,
        faTriangleExclamation,
      };
    },
  },

  methods: {
    /**
     * Hide strings more than 20 lines long by default.
     */
    shouldShowStringByDefault(str) {
      return [...str.matchAll(/\r\n|\r|\n/g).take(21)].length <= 20;
    },
  },
};
</script>
