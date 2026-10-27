<template>
  <pre
    class="tw-font-mono tw-bg-gray-100 tw-p-2 tw-whitespace-pre-wrap tw-break-all tw-overflow-hidden"
    :class="{ 'tw-border': bordered, 'tw-rounded': bordered }"
  ><template
    v-for="segment in textSegments"
  ><a
    v-if="segment.href"
    class="tw-link tw-link-hover tw-link-info [&_span]:!tw-text-inherit [&_span]:!tw-bg-transparent"
    :href="segment.href"
    v-html="segment.html"
  /><span
    v-else
    v-html="segment.html"
  /></template></pre>
</template>

<script>
import { AnsiUp } from 'ansi_up';
import 'core-js/actual/regexp/escape';

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
    textSegments() {
      const text = String(this.text).replace(/\[NON-XML-CHAR-0x1B\]/g, '\x1B');

      // A single converter is shared by all segments so ANSI styles carry over into and out of links.  Links keep
      // their own colors though, so they stay recognizable.
      // AnsiUp escapes the text it converts, so its output is safe to render with v-html.
      const ansiUp = new AnsiUp();

      if (!this.links || this.links.size === 0) {
        return [
          {
            html: ansiUp.ansi_to_html(text),
          },
        ];
      }

      // Match longer strings first so a string which is a prefix of another doesn't split it.
      const pattern = [...this.links.keys()]
        .sort((a, b) => b.length - a.length)
        .map((stringToMatch) => RegExp.escape(stringToMatch))
        .join('|');

      // Split the raw text rather than the generated HTML so links never cut through ANSI styles or HTML
      // entities.  The capturing group places the matched strings at the odd indices.
      return text.split(new RegExp(`(${pattern})`)).map((chunk, index) => ({
        html: ansiUp.ansi_to_html(chunk),
        href: index % 2 === 1 ? this.links.get(chunk) : undefined,
      }));
    },
  },
};
</script>
