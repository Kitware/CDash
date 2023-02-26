<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div id="main_content">
      <table class="tabb" style="width: 100%;">
        <!-- We abuse HTML table syntax a little bit here, but we want to maintain consistency with other CDash tables -->
        <thead>
          <tr class="table-heading1">
            <td colspan="5">
              <h3>Manage Authentication Tokens</h3>
            </td>
          </tr>
          <tr class="table-heading">
            <td align="center" class="botl">Owner</td>
            <td align="center" class="botl">Description</td>
            <td align="center" class="botl">Scope</td>
            <td align="center" class="botl">Expires</td>
            <td align="center" class="botl">Revoke</td>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cdash.tokens === undefined || Object.keys(cdash.tokens).length === 0">
            <td align="center" colspan="5">No authentication tokens have been created yet.</td>
          </tr>
          <tr v-for="(token, hash, index) in cdash.tokens" :class="{'treven': index % 2 === 0, 'trodd': index % 2 !== 0}">
            <td align="center">
              {{ token.owner_firstname }}&nbsp;{{ token.owner_lastname }}
            </td>
            <td align="center">
              {{ token.description }}
            </td>
            <td align="center" v-if="token.scope === 'submit_only' && token.projectname !== null && token.projectname.length > 0">
              Submit Only (<a :href="$baseURL + '/index.php?project=' + token.projectname">{{ token.projectname }}</a>)
            </td>
            <td align="center" v-else-if="token.scope === 'submit_only'">
              Submit Only
            </td>
            <td align="center" v-else>
              Full Access
            </td>
            <td align="center">
              {{ token.expires }}
            </td>
            <td align="center">
              <span class="glyphicon glyphicon-trash" @click="revokeToken(token)"></span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
<script>
import ApiLoader from './shared/ApiLoader';
export default {
  name: "ManageAuthTokens",

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
    }
  },

  mounted () {
    ApiLoader.loadPageData(this, '/api/authtokens/all');
  },

  methods: {
    revokeToken(token) {
      this.$axios
        .delete('/api/authtokens/delete/' + token.hash)
        .then(() => {
          this.$delete(this.cdash.tokens, token.hash);
        })
        .catch(error => {
          console.log(error);
          this.errored = true;
        });
    }
  }
}
</script>
