<template>
  <li v-if="!node.children">
    <span class="tw-flex tw-items-center tw-justify-between tw-w-full">
      <span
        class="tw-truncate"
        :title="node.name"
      >
        <font-awesome-icon :icon="FA.faFile" />
        {{ node.name }}
      </span>
      <span
        v-if="node.file?.status && node.file?.status !== 'UPDATED'"
        class="tw-badge tw-badge-sm tw-border"
        :class="node.file?.status === 'CONFLICTING' ? 'tw-badge-error tw-border-error' : 'tw-badge-ghost tw-border-gray-300'"
      >
        {{ node.file.status }}
      </span>
    </span>
  </li>
  <li v-else>
    <details open>
      <summary
        class="tw-truncate"
        :title="node.name"
      >
        <font-awesome-icon :icon="FA.faFolderOpen" />
        {{ node.name }}
      </summary>
      <ul>
        <commit-file-tree-node
          v-for="child in node.children"
          :key="child.name"
          :node="child"
          :repository="repository"
        />
      </ul>
    </details>
  </li>
</template>

<script>
import {Repository} from '../shared/RepositoryIntegrations';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faFolderOpen, faFile} from '@fortawesome/free-regular-svg-icons';

export default {
  name: 'CommitFileTreeNode',
  components: {FontAwesomeIcon},
  props: {
    node: {
      type: Object,
      required: true,
    },
    repository: {
      type: [Repository, null],
      required: true,
    },
  },
  computed: {
    FA() {
      return {
        faFolderOpen,
        faFile,
      };
    },
  },
};
</script>
