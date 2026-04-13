<template>
  <div
    class="tw-p-2 tw-border tw-rounded-md tw-flex tw-flex-col tw-gap-2"
    data-test="commit-card"
  >
    <div>
      <span class="tw-font-bold">Revision </span>
      <a
        v-if="repository"
        :href="repository.getCommitUrl(revision)"
        class="tw-font-mono tw-link tw-link-hover tw-link-info"
      >{{ revision }}</a>
      <span
        v-else
        class="tw-font-mono"
      >{{ revision }}</span>
      authored by
      <span class="tw-italic">{{ authorName }}</span>,
      committed by
      <span class="tw-italic">{{ committerName }}</span>
    </div>

    <div class="tw-flex tw-flex-row tw-gap-2">
      <CodeBox
        :text="commitMessage"
        class="tw-w-1/2"
      />
      <ul class="tw-menu tw-menu-xs tw-bg-base-200 tw-rounded-lg tw-w-1/2">
        <commit-file-tree-node
          v-for="node in fileTree"
          :key="node.name"
          :node="node"
          :repository="repository"
        />
      </ul>
    </div>
  </div>
</template>

<script>

import {Repository} from '../shared/RepositoryIntegrations';
import CodeBox from '../shared/CodeBox.vue';
import CommitFileTreeNode from './CommitFileTreeNode.vue';

export default {
  components: {CodeBox, CommitFileTreeNode},
  props: {
    commitFiles: {
      type: Array,
      required: true,
    },

    repository: {
      type: [Repository, null],
      required: true,
    },
  },

  computed: {
    revision() {
      return this.commitFiles[0].revision;
    },

    commitMessage() {
      return this.commitFiles[0].log;
    },

    authorName() {
      return this.commitFiles[0].authorName ?? 'unknown';
    },

    committerName() {
      return this.commitFiles[0].committerName ?? 'unknown';
    },

    fileTree() {
      const tree = [];

      this.commitFiles.forEach(file => {
        const parts = file.fileName.split('/');
        let currentLevel = tree;

        parts.forEach((part, index) => {
          const existingPath = currentLevel.find(item => item.name === part);

          if (existingPath) {
            currentLevel = existingPath.children;
          }
          else {
            const newNode = {
              name: part,
            };

            if (index < parts.length - 1) {
              newNode.children = [];
            }
            else {
              newNode.file = file;
            }

            currentLevel.push(newNode);
            currentLevel = newNode.children;
          }
        });
      });

      return tree;
    },
  },
};
</script>
