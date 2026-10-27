<template>
  <div class="tw-flex tw-flex-col tw-gap-2">
    <div class="tw-font-bold">
      <span
        class="tw-mr-1"
        :class="buildError.type === 'ERROR' ? 'tw-text-error' : 'tw-text-warning'"
      >
        <FontAwesomeIcon :icon="buildError.type === 'ERROR' ? FA.faCircleExclamation : FA.faTriangleExclamation" />
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
        Standard Error <FontAwesomeIcon :icon="showStdError ? FA.faChevronDown : FA.faChevronRight" />
      </div>
      <CodeBox
        v-if="showStdError"
        :text="buildError.stdError"
        :links="getLinksFromText(buildError.stdError)"
      />
    </div>

    <div v-if="buildError.stdOutput">
      <div
        class="tw-font-bold tw-cursor-pointer"
        data-test="stdout"
        @click="showStdOutput = !showStdOutput"
      >
        Standard Output <FontAwesomeIcon :icon="showStdOutput ? FA.faChevronDown : FA.faChevronRight" />
      </div>
      <CodeBox
        v-if="showStdOutput"
        :text="buildError.stdOutput"
        :links="getLinksFromText(buildError.stdOutput)"
      />
    </div>

    <div v-if="buildError.command">
      <div
        class="tw-font-bold tw-cursor-pointer"
        @click="showCommand = !showCommand"
      >
        Command <FontAwesomeIcon :icon="showCommand ? FA.faChevronDown : FA.faChevronRight" />
      </div>
      <CodeBox
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
import CodeBox from '../shared/CodeBox.vue';
import {
  faChevronDown,
  faChevronRight,
  faCircleExclamation,
  faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { getRepository } from '../shared/RepositoryIntegrations';
import 'core-js/actual/regexp/escape';

export default {
  name: 'BuildErrorItem',
  components: { FontAwesomeIcon, CodeBox },

  props: {
    buildError: {
      type: Object,
      required: true,
    },

    sourceDirectory: {
      type: [String, null],
      required: true,
    },

    repositoryType: {
      type: [String, null],
      required: true,
    },

    repositoryUrl: {
      type: [String, null],
      required: true,
    },

    repositoryCmakeProjectRoot: {
      type: [String, null],
      required: true,
    },

    revision: {
      type: String,
      required: false,
      default: undefined,
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

    getLinksFromText(text) {
      if (!text || !this.repositoryType || !this.repositoryUrl) {
        return new Map();
      }

      const sourceDirectoryPrefixes = this.sourceDirectory ? [`${RegExp.escape(this.sourceDirectory)}/`] : [];

      // Paths may contain spaces, but end at a line break, an escape sequence, or a delimiter followed by another
      // path.  Otherwise, a match could start at an earlier mention of the prefix.
      const pathStartPattern = `(?:${[...sourceDirectoryPrefixes, RegExp.escape('/.../')].join('|')})`;
      const pathCharacterPattern = `(?![\\s"'\`(),;=]${pathStartPattern}|\\[NON-XML-CHAR-0x1B\\])[^:\\r\\n\\x1B]`;

      const regexPrefixes = [
        ...sourceDirectoryPrefixes,
        `${RegExp.escape('/.../')}(?:(?!/)${pathCharacterPattern})+/`, // CTest includes the repository name in the path.
      ];
      const prefixPattern = `(?:${regexPrefixes.join('|')})`;

      // Match each path whether or not a line and column follow it, so later mentions of the prefix within the path
      // aren't searched again.  Otherwise, long lines which mention the prefix many times are slow to search.
      const pathRegex = new RegExp(`${prefixPattern}(?:${pathCharacterPattern})*(:[0-9]+:[0-9]+)?`, 'g');
      const matches = [...text.matchAll(pathRegex)]
        .filter(([, lineAndColumn]) => lineAndColumn)
        .map(([match]) => match);
      const repository = getRepository(this.repositoryType, this.repositoryUrl, this.repositoryCmakeProjectRoot);

      if (!repository || matches.length === 0) {
        return new Map();
      }

      return new Map(
        matches.map((match) => {
          let filePath = match.replace(new RegExp(`^${prefixPattern}`), '');
          filePath = filePath.split(':')[0]; // Trim off the line:col anchor.
          // Encode each directory separately so characters such as "#" and "?" stay part of the path.
          filePath = filePath.split('/').map((part) => encodeURIComponent(part)).join('/');

          return [
            match,
            repository.getFileUrl(this.revision ?? 'main', filePath),
          ];
        }),
      );
    },
  },
};
</script>
