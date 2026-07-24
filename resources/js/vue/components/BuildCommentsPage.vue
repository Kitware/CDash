<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="comments"
  >
    <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
      <BuildSummaryCard :build-id="buildId" />

      <div class="tw-border tw-border-base-300 tw-bg-base-100 tw-rounded-md">
        <div class="tw-p-4">
          <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
            <h2 class="tw-text-lg tw-font-bold">
              Comments ({{ comments ? comments.length : 0 }})
            </h2>
          </div>

          <LoadingIndicator :is-loading="!comments">
            <div
              v-if="comments && comments.length > 0"
              class="tw-flex tw-flex-col tw-gap-2"
              data-test="comments-list"
            >
              <div
                v-for="{node: comment} in comments"
                :key="comment.id"
                class="tw-p-3 tw-border tw-rounded-md tw-bg-base-100"
                :class="Number(comment.user.id) === userId ? 'tw-border-primary' : 'tw-border-base-300'"
                data-test="comment-item"
              >
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-1">
                  <div class="tw-flex tw-items-center tw-gap-2">
                    <span class="tw-font-bold">{{ comment.user.firstname }} {{ comment.user.lastname }}</span>
                    <span
                      v-if="Number(comment.user.id) === userId"
                      class="tw-badge tw-badge-primary tw-badge-sm"
                    >You</span>
                  </div>
                  <time class="tw-text-xs tw-opacity-50">{{ Utils.formatRelativeTimestamp(comment.timestamp) }}</time>
                </div>
                <CodeBox
                  :text="comment.text"
                  :bordered="false"
                  class="tw-bg-transparent tw-p-0"
                />
              </div>
            </div>
            <div
              v-else
              class="tw-text-center tw-py-4 tw-opacity-50"
              data-test="no-comments-message"
            >
              No comments yet.
            </div>
          </LoadingIndicator>

          <div
            v-if="userId > 0"
            class="tw-divider tw-my-2"
          />

          <div
            v-if="userId > 0"
            class="tw-mt-2"
          >
            <h3 class="tw-text-sm tw-font-bold tw-mb-2">
              Add a comment
            </h3>
            <textarea
              v-model="commentText"
              data-test="comment-text"
              class="tw-textarea tw-textarea-bordered tw-w-full tw-textarea-sm"
              rows="3"
              placeholder="Write your comment here..."
            />
            <div class="tw-flex tw-justify-end tw-mt-2">
              <button
                data-test="add-comment"
                class="tw-btn tw-btn-primary tw-btn-sm"
                :disabled="!commentText || submitting"
                @click="addComment()"
              >
                <span
                  v-if="submitting"
                  class="tw-loading tw-loading-spinner tw-loading-xs"
                />
                Post Comment
              </button>
            </div>
          </div>
          <div
            v-else
            class="tw-mt-4 tw-p-4 tw-bg-base-200 tw-rounded-md tw-text-sm tw-text-center tw-text-base-content/70"
          >
            Please <a
              :href="$baseURL + '/login'"
              class="tw-link tw-link-primary tw-font-bold"
            >log in</a> to add a comment.
          </div>
        </div>
      </div>
    </div>
  </BuildSidebar>
</template>

<script>
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import CodeBox from './shared/CodeBox.vue';
import gql from 'graphql-tag';
import Utils from './shared/Utils';

export default {
  name: 'BuildCommentsPage',

  components: {
    BuildSummaryCard,
    BuildSidebar,
    LoadingIndicator,
    CodeBox,
  },

  props: {
    buildId: {
      type: Number,
      required: true,
    },
    userId: {
      type: Number,
      default: 0,
    },
  },

  data() {
    return {
      commentText: '',
      submitting: false,
    };
  },

  computed: {
    Utils() {
      return Utils;
    },
  },

  apollo: {
    comments: {
      query: gql`
        query($buildId: ID) {
          build(id: $buildId) {
            id
            comments {
              edges {
                node {
                  id
                  text
                  timestamp
                  user {
                    id
                    firstname
                    lastname
                  }
                }
              }
            }
          }
        }
      `,
      update: (data) => data?.build?.comments?.edges,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
    },
  },

  methods: {
    addComment() {
      this.submitting = true;
      this.$apollo
        .mutate({
          mutation: gql`
            mutation createComment($input: CreateCommentInput!) {
              createComment(input: $input) {
                comment {
                  id
                }
              }
            }
          `,
          variables: {
            input: {
              buildId: this.buildId,
              text: this.commentText,
            },
          },
        })
        .then(() => {
          this.$apollo.queries.comments.refetch();
          this.commentText = '';
          this.submitting = false;
        })
        .catch((error) => {
          console.error(error);
          this.submitting = false;
          alert('Failed to add comment. Please try again.');
        });
    },
  },
};
</script>
