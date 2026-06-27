<template>
  <div class="tw-relative">
    <button
      v-if="showCopyButton && String(text).length > 0"
      type="button"
      class="tw-btn tw-btn-ghost tw-btn-sm tw-absolute tw-top-1 tw-right-1"
      :title="copied ? 'Copied!' : 'Copy'"
      data-test="copy-button"
      @click="copyText"
    >
      <FontAwesomeIcon :icon="copied ? FA.faCheck : FA.faCopy" />
    </button>

    <!-- Must be formatted like this to avoid extra whitespace -->
    <pre
      class="tw-font-mono tw-bg-gray-100 tw-p-2 tw-whitespace-pre-wrap tw-break-all tw-overflow-hidden"
      :class="{ 'tw-border': bordered, 'tw-rounded': bordered }"
    ><template
      v-for="segment in textSegments"
    ><a
      v-if="segment.href"
      class="tw-link tw-link-hover tw-link-info"
      :href="segment.href"
    >{{ segment.text }}</a><span
      v-else
      v-html="segment.text"
    /></template></pre>
  </div>
</template>

<script>
import { AnsiUp } from 'ansi_up';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faCopy, faCheck } from '@fortawesome/free-solid-svg-icons';

export default {
  name: 'CodeBox',

  components: {
    FontAwesomeIcon,
  },

  props: {
    text: {
      type: String,
      required: true,
    },

    bordered: {
      type: Boolean,
      default: true,
    },

    /**
     * An map of the form { string => href }.  Strings matching a key in this map will be linkified.
     */
    links: {
      type: Map,
      required: false,
      default: undefined,
    },

    showCopyButton: {
      type: Boolean,
      default: true,
    },
  },

  data() {
    return {
      copied: false,
    };
  },

  computed: {
    FA() {
      return {
        faCopy,
        faCheck,
      };
    },

    ansiText() {
      const escapedText = String(this.text).replace(/\[NON-XML-CHAR-0x1B\]/g, '\x1B') ?? '';
      return (new AnsiUp).ansi_to_html(escapedText);
    },

    textSegments() {
      if (!this.links) {
        return [
          {
            text: this.ansiText,
          },
        ];
      }

      let segments = [
        {
          text: this.ansiText,
        },
      ];
      for (const [stringToMatch, linkTo] of this.links) {
        const newSegments = [];
        segments.forEach(({ text, href }) => {
          if (href) {
            // Don't attempt to split existing links.
            newSegments.push({
              text,
              href,
            });
            return;
          }

          let isFirstElement = true;
          text.split(stringToMatch).forEach((newSegment) => {
            if (!isFirstElement) {
              // Add a link between two text chunks.  Don't add a link before the first chunk.
              newSegments.push({
                text: stringToMatch,
                href: linkTo,
              });
            }

            newSegments.push({
              text: newSegment,
            });

            isFirstElement = false;
          });
        });

        segments = newSegments;
      }

      return segments;
    },
  },

  methods: {
    async copyText() {
      try {
        await navigator.clipboard.writeText(String(this.text));
        this.copied = true;
        setTimeout(() => {
          this.copied = false;
        }, 2000);
      }
      catch (err) {
        console.error('Failed to copy: ', err);
      }
    },
  },
};
</script>
