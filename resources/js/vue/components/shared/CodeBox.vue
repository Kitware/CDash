<template>
  <div
    ref="editor"
    class="code-box"
    :class="{ 'tw-border': bordered }"
  />
</template>

<script>
import { EditorView, lineNumbers, drawSelection } from '@codemirror/view';
import { EditorState } from '@codemirror/state';

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
  },
  data() {
    return {
      editor: null,
    };
  },
  watch: {
    text(newText) {
      if (this.editor) {
        this.editor.dispatch({
          changes: { from: 0, to: this.editor.state.doc.length, insert: newText },
        });
      }
    },
  },
  mounted() {
    const state = EditorState.create({
      doc: this.text,
      extensions: [
        lineNumbers(),
        drawSelection(),
        EditorState.readOnly.of(true),
        EditorView.theme({
          '.cm-scroller': {
            overflow: 'auto',
          },
        }),
        EditorView.lineWrapping,
      ],
    });

    this.editor = new EditorView({
      state,
      parent: this.$refs.editor,
    });
  },
  beforeUnmount() {
    if (this.editor) {
      this.editor.destroy();
    }
  },
};
</script>
