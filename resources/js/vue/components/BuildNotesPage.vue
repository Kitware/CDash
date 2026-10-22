<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="notes"
  >
    <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
      <BuildSummaryCard :build-id="buildId" />

      <div data-test="notes-section">
        <div
          v-if="loadFailed"
          role="alert"
          class="tw-alert tw-alert-error"
          data-test="notes-error-message"
        >
          Failed to load notes for this build.
        </div>

        <LoadingIndicator
          v-else
          :is-loading="!notes"
        >
          <div
            v-if="notes.edges.length === 0"
            data-test="no-notes-message"
          >
            No notes were uploaded for this build.
          </div>

          <div
            v-else
            class="tw-flex tw-flex-col md:tw-flex-row"
            data-test="notes-content"
          >
            <aside
              class="tw-w-full md:tw-w-1/3 lg:tw-w-1/4"
              data-test="notes-menu"
            >
              <div class="tw-sticky tw-top-[100px] tw-max-h-[calc(100vh-100px)] tw-overflow-y-auto tw-border tw-rounded-box">
                <ul class="tw-menu tw-bg-base-200">
                  <li
                    v-for="note in notes.edges"
                    :key="note.node.id"
                    class="tw-w-full"
                    data-test="notes-menu-item"
                  >
                    <a
                      :href="`#${note.node.id}`"
                      :class="{ 'tw-active': activeNoteId === note.node.id }"
                      class="menu-item-wrap"
                    >{{ note.node.name }}</a>
                  </li>
                </ul>
              </div>
            </aside>

            <main
              class="tw-w-full md:tw-w-2/3 lg:tw-w-3/4 md:tw-pl-4 tw-pt-4 md:tw-pt-0"
              data-test="notes-content"
            >
              <div class="tw-flex tw-flex-col tw-gap-4">
                <div
                  v-for="note in notes.edges"
                  :id="note.node.id"
                  :key="note.node.id"
                  :ref="r => noteRefs.push(r)"
                  class="tw-border tw-border-base-300 tw-bg-base-100 tw-rounded-box tw-scroll-mt-28 tw-overflow-hidden"
                  data-test="notes-content-item"
                >
                  <h3 class="tw-p-4 tw-text-xl tw-font-bold tw-break-words">
                    {{ note.node.name }}
                  </h3>
                  <hr>
                  <CodeBox
                    :text="note.node.text"
                    :bordered="false"
                  />
                </div>
              </div>
            </main>
          </div>
        </LoadingIndicator>
      </div>
    </div>
  </BuildSidebar>
</template>

<script>
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import gql from 'graphql-tag';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import CodeBox from './shared/CodeBox.vue';
import BuildSidebar from './shared/BuildSidebar.vue';

export default {
  name: 'BuildNotesPage',
  components: { CodeBox, LoadingIndicator, BuildSummaryCard, BuildSidebar },

  props: {
    buildId: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      activeNoteId: null,
      noteRefs: [],
      loadFailed: false,
    };
  },

  apollo: {
    notes: {
      query: gql`
        query($buildId: ID!, $after: String) {
          build(id: $buildId) {
            id
            notes(after: $after, first: 10) {
              edges {
                node {
                  id
                  name
                  text
                }
              }
              pageInfo {
                hasNextPage
                hasPreviousPage
                startCursor
                endCursor
              }
            }
          }
        }
      `,
      update: (data) => data?.build?.notes,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
      result({ data }) {
        if (data && !data.build) {
          // The build was deleted after the page loaded.
          this.loadFailed = true;
          return;
        }
        if (data && data.build.notes.pageInfo.hasNextPage) {
          this.$apollo.queries.notes.fetchMore({
            variables: {
              buildId: this.buildId,
              after: data.build.notes.pageInfo.endCursor,
            },
          }).catch((error) => {
            console.error(error);
            this.loadFailed = true;
          });
        }
      },
      error(error) {
        console.error(error);
        this.loadFailed = true;
      },
    },
  },

  created() {
    window.addEventListener('scroll', this.handleScroll);
  },

  methods: {
    handleScroll() {
      for (const noteRef of this.noteRefs) {
        const rect = noteRef.getBoundingClientRect();
        if (rect.bottom >= 100) {
          this.activeNoteId = noteRef.getAttribute('id');
          break;
        }
      }
    },
  },
};
</script>

<style scoped>
/*
This is a hacky workaround to avoid CSS specificity issues with DaisyUI menu items.
Re-evaluate once DaisyUI v5 is available in CDash.
*/
.menu-item-wrap {
  height: auto;
  white-space: normal;
  word-break: break-word;
}
</style>
