<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <data-table
      :column-groups="[
        {
          displayName: 'Authentication Tokens',
          width: 100,
        }
      ]"
      :columns="[
        {
          name: 'owner',
          displayName: 'Owner',
        },
        {
          name: 'description',
          displayName: 'Description',
          expand: true,
        },
        {
          name: 'scope',
          displayName: 'Scope',
        },
        {
          name: 'expires',
          displayName: 'Expires',
        },
        {
          name: 'actions',
          displayName: 'Actions',
        },
      ]"
      :rows="formattedAuthTokenRows"
      :full-width="true"
      empty-table-text="No authentication tokens have been created yet."
    >
      <template #scope="{ props: { scope: scope, projectname: projectname } }">
        <template v-if="scope === 'submit_only' && projectname !== null && projectname.length > 0">
          Submit Only (<a
            class="cdash-link"
            :href="$baseURL + '/index.php?project=' + projectname"
          >{{ projectname }}</a>)
        </template>
        <template v-else-if="scope === 'submit_only'">
          Submit Only
        </template>
        <template v-else>
          Full Access
        </template>
      </template>
      <template #actions="{ props: { token: token } }">
        <button
          class="tw-btn tw-btn-sm tw-btn-outline tw-flex-nowrap tw-items-center tw-gap-1"
          @click="revokeToken(token)"
        >
          Revoke Token
          <font-awesome-icon :icon="FA.faTrash" />
        </button>
      </template>
    </data-table>
  </section>
</template>
<script>

import ApiLoader from './shared/ApiLoader';
import DataTable from './shared/DataTable.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faTrash} from '@fortawesome/free-solid-svg-icons';

export default {
  name: 'ManageAuthTokens',
  components: {FontAwesomeIcon, DataTable},

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
    };
  },

  computed: {
    FA() {
      return {
        faTrash,
      };
    },

    formattedAuthTokenRows() {
      return Object.values(this.cdash?.tokens ?? {}).map(token => {
        return {
          owner: `${token.owner_firstname} ${token.owner_lastname}`,
          description: token.description,
          scope: {
            scope: token.scope,
            projectname: token.projectname,
          },
          expires: token.expires,
          actions: {
            token: token,
          },
        };
      }) ?? [];
    },
  },

  mounted () {
    ApiLoader.loadPageData(this, '/api/authtokens/all');
  },

  methods: {
    revokeToken(token) {
      this.$axios
        .delete(`/api/authtokens/delete/${token.hash}`)
        .then(() => {
          delete this.cdash.tokens[token.hash];
        })
        .catch(error => {
          console.log(error);
          this.errored = true;
        });
    },
  },
};
</script>
