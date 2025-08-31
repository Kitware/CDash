<template>
  <div ref="editor" />
</template>

<script>
import { EditorView, lineNumbers, gutter, GutterMarker, Decoration } from '@codemirror/view';
import { EditorState, Facet, StateField, RangeSetBuilder } from '@codemirror/state';

const coverageData = Facet.define({
  combine: values => values[0] || {},
});

const coverageThemes = EditorView.baseTheme({
  '&.cm-editor .cm-line.cm-line-hit': { backgroundColor: '#e6ffe6', borderLeft: '3px solid #00c853' },
  '&.cm-editor .cm-line.cm-line-miss': { backgroundColor: '#ffe6e6', borderLeft: '3px solid #ff1744' },
  '&.cm-editor .cm-line.cm-line-partial': { backgroundColor: '#fffad1', borderLeft: '3px solid #fbc02d' },
  '.cm-coverage-gutter-hit': { color: '#00c853', fontWeight: 'bold' },
  '.cm-coverage-gutter-miss': { color: '#ff1744', fontWeight: 'bold' },
  '.cm-coverage-gutter-partial': { color: '#fbc02d', fontWeight: 'bold' },
  '.cm-coverage-gutter': { width: '3em', textAlign: 'right', paddingRight: '5px' },
});

const coverageHighlightField = StateField.define({
  create(state) {
    return buildDecorations(state.facet(coverageData), state.doc);
  },
  update(decorations, transaction) {
    if (transaction.docChanged || transaction.effects.some(e => e.is(coverageData.reconfigure))) {
      return buildDecorations(transaction.state.facet(coverageData), transaction.state.doc);
    }
    return decorations.map(transaction.changes);
  },
  provide: f => EditorView.decorations.from(f),
});

function buildDecorations(coverageMap, doc) {
  const builder = new RangeSetBuilder();
  if (!coverageMap) {
    return builder.finish();
  }

  for (const lineNumberStr in coverageMap) {
    const lineData = coverageMap[lineNumberStr];
    const cmLineNumber = parseInt(lineNumberStr, 10) + 1;

    if (lineData && cmLineNumber > 0 && cmLineNumber <= doc.lines) {
      const linePos = doc.line(cmLineNumber);
      let className = '';
      const hasBranches = typeof lineData.totalBranches === 'number' && lineData.totalBranches > 0;

      if (hasBranches) {
        if (lineData.branchesHit === lineData.totalBranches) {
          className = 'cm-line-hit';
        }
        else if (lineData.branchesHit > 0) {
          className = 'cm-line-partial';
        }
        else {
          className = 'cm-line-miss';
        }
      }
      else if (typeof lineData.timesHit === 'number') {
        className = lineData.timesHit > 0 ? 'cm-line-hit' : 'cm-line-miss';
      }

      if (className) {
        builder.add(linePos.from, linePos.from, Decoration.line({ class: className }));
      }
    }
  }
  return builder.finish();
}

export default {
  props: {
    file: { type: String, required: true },
    coverageLines: { type: Array, required: true },
  },
  data: () => ({ view: null }),
  computed: {
    coverageMap() {
      const map = {};
      for (const line of this.coverageLines) {
        if (typeof line.lineNumber === 'number') {
          map[line.lineNumber] = line;
        }
      }
      return map;
    },
  },
  watch: {
    coverageMap: {
      handler(newMap) {
        if (this.view && newMap) {
          this.view.dispatch({ effects: coverageData.reconfigure(newMap) });
        }
      },
      immediate: true,
    },
  },
  mounted() {
    class CoverageGutterMarker extends GutterMarker {
      constructor(coverage) {
        super();
        this.coverage = coverage;
      }
      toDOM() {
        const element = document.createElement('span');

        const hasBranches = typeof this.coverage.totalBranches === 'number' && this.coverage.totalBranches > 0;
        let statusClass = '';

        if (hasBranches) {
          element.textContent = `${this.coverage.branchesHit}/${this.coverage.totalBranches}`;
          if (this.coverage.branchesHit === this.coverage.totalBranches) {
            statusClass = 'cm-coverage-gutter-hit';
          }
          else if (this.coverage.branchesHit > 0) {
            statusClass = 'cm-coverage-gutter-partial';
          }
          else {
            statusClass = 'cm-coverage-gutter-miss';
          }
        }
        else if (typeof this.coverage.timesHit === 'number') {
          element.textContent = this.coverage.timesHit;
          statusClass = this.coverage.timesHit > 0 ? 'cm-coverage-gutter-hit' : 'cm-coverage-gutter-miss';
        }

        if (statusClass) {
          element.classList.add(statusClass);
        }
        return element;
      }
    }

    this.view = new EditorView({
      state: EditorState.create({
        doc: this.file,
        extensions: [
          EditorView.editable.of(false),
          lineNumbers(),
          gutter({
            class: 'cm-coverage-gutter',
            lineMarker: (view, line) => {
              const cmLineNumber = view.state.doc.lineAt(line.from).number;
              const coverage = this.coverageMap[cmLineNumber - 1];
              if (coverage) {
                return new CoverageGutterMarker(coverage);
              }
              return null;
            },
          }),
          coverageThemes,
          coverageData.of(this.coverageMap),
          coverageHighlightField,
        ],
      }),
      parent: this.$refs.editor,
    });
  },
  beforeUnmount() {
    if (this.view) {
      this.view.destroy();
    }
  },
};
</script>
