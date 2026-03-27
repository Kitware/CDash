<template>
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
</template>

<script>
import { AnsiUp } from 'ansi_up';

export default {
  name: 'CodeBox',
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
  },

  computed: {
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
};
</script>
