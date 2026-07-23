<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="update"
  >
    <section class="tw-flex tw-flex-col tw-w-full tw-gap-4">
      <BuildSummaryCard :build-id="buildId" />

      <LoadingIndicator :is-loading="!update">
        <div>
          <div v-if="update.revision">
            <b>Revision: </b>
            <!-- eslint-disable-next-line vue/no-undef-components -->
            <tt v-if="repository">
              <a
                class="tw-link tw-link-hover tw-link-info"
                :href="revisionUrl"
              >{{ update.revision }}</a>
            </tt>
            <!-- eslint-disable-next-line vue/no-undef-components -->
            <tt v-else>
              {{ update.revision }}
            </tt>
          </div>
          <div v-if="update.priorRevision">
            <b>Prior Revision: </b>
            <!-- eslint-disable-next-line vue/no-undef-components -->
            <tt v-if="repository">
              <a
                class="tw-link tw-link-hover tw-link-info"
                :href="repository?.getCommitUrl(update.priorRevision) ?? ''"
              >{{ update.priorRevision }}</a>
            </tt>
            <!-- eslint-disable-next-line vue/no-undef-components -->
            <tt v-else>
              {{ update.priorRevision }}
            </tt>
          </div>
        </div>

        <h3
          v-if="update.status"
          class="error"
        >
          {{ update.status }}
        </h3>
      </LoadingIndicator>

      <LoadingIndicator :is-loading="!updateFiles">
        <div class="tw-flex tw-flex-col tw-gap-4">
          <CommitCard
            v-for="commitFiles in commits"
            :key="commitFiles[0].revision"
            :commit-files="commitFiles"
            :repository="repository"
          />
        </div>
      </LoadingIndicator>
    </section>
  </BuildSidebar>
</template>

<script>
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import {getRepository} from './shared/RepositoryIntegrations';
import gql from 'graphql-tag';
import { DateTime } from 'luxon';
import CommitCard from './BuildUpdate/CommitCard.vue';

export default {
  name: 'BuildUpdate',
  components: {CommitCard, LoadingIndicator, BuildSummaryCard, BuildSidebar},

  props: {
    buildId: {
      type: Number,
      required: true,
    },

    repositoryType: {
      type: String,
      required: true,
    },

    repositoryUrl: {
      type: String,
      required: true,
    },
  },

  apollo: {
    update: {
      query: gql`
        query($buildId: ID) {
          build(id: $buildId) {
            id
            updateStep {
              id
              command
              type
              status
              revision
              priorRevision
              path
            }
          }
        }
      `,
      update: (data) => data?.build?.updateStep,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
    },

    updateFiles: {
      query: gql`
        query($buildId: ID) {
          build(id: $buildId) {
            id
            updateStep {
              id
              updateFiles(first: 100000) {
                edges {
                  node {
                    id
                    fileName
                    authorName
                    authorEmail
                    committerName
                    committerEmail
                    checkinDate
                    log
                    revision
                    priorRevision
                    status
                  }
                }
              }
            }
          }
        }
      `,
      update: (data) => data?.build?.updateStep?.updateFiles?.edges,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
    },
  },

  computed: {
    repository() {
      return getRepository(this.repositoryType, this.repositoryUrl);
    },

    revisionUrl() {
      if (this.update.priorRevision) {
        return this.repository?.getComparisonUrl(this.update.revision, this.update.priorRevision) ?? '';
      } else {
        return this.repository?.getCommitUrl(this.update.revision) ?? '';
      }
    },

    commits() {
      if (!this.updateFiles) {
        return [];
      }

      // Group by revision
      const groups = {};
      for (const edge of this.updateFiles) {
        const file = edge.node;
        const revision = file.revision || 'Unknown Revision';
        if (!groups[revision]) {
          groups[revision] = [];
        }
        groups[revision].push(file);
      }

      const commits = Object.values(groups);

      // Sort files within each commit
      commits.forEach((commitFiles) => {
        commitFiles.sort((a, b) => a.fileName.localeCompare(b.fileName));
      });

      // Sort commits by max checkin date (descending)
      commits.sort((a, b) => {
        const maxDateA = a.reduce((max, file) => {
          if (!file.checkinDate) {
            return max;
          }
          const dt = DateTime.fromISO(file.checkinDate);
          return dt > max ? dt : max;
        }, DateTime.fromMillis(0));

        const maxDateB = b.reduce((max, file) => {
          if (!file.checkinDate) {
            return max;
          }
          const dt = DateTime.fromISO(file.checkinDate);
          return dt > max ? dt : max;
        }, DateTime.fromMillis(0));

        return maxDateB.toMillis() - maxDateA.toMillis();
      });

      return commits;
    },
  },
};
</script>
