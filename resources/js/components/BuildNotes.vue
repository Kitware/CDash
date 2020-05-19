<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div v-if="loading">
      <img :src="$baseURL + '/img/loading.gif'">
    </div>
    <div
      v-else
      id="main_content"
    >
      <br>
      <table border="0">
        <tr>
          <td align="left">
            <b>Site: </b>
            <a :href="$baseURL + 'viewSite.php?siteid=' + cdash.build.siteid">
              {{ cdash.build.site }}
            </a>
          </td>
        </tr>
        <tr>
          <td align="left">
            <b>Build Name: </b>
            <a :href="$baseURL + 'build/' + cdash.build.buildid">
              {{ cdash.build.buildname }}
            </a>
          </td>
        </tr>

        <tr>
          <td align="left">
            <b>Stamp: </b>
            {{ cdash.build.stamp }}
          </td>
        </tr>
      </table>

      <br>

      <li v-for="(item, index) in items">
        {{ parentMessage }} - {{ index }} - {{ item.message }}
      </li>


      <a
        v-for="(note, index) in cdash.notes"
        :href="`#${index}`"
      >
        <img
          :src="$baseURL + '/img/document.png'"
          alt="Notes"
          border="0"
          align="top"
        >
        {{ note.time }} -- {{ note.name }}
        <br>
      </a>

      <br>
      <br>
      <br>

      <div v-for="(note, index) in cdash.notes">
        <div
          :id="`note${index}`"
          class="title-divider"
        >
          <b>{{ note.time }} -- {{ note.name }}</b>
        </div>
        <br>
        <pre>{{ note.text }}</pre>
        <br>
      </div>
      <br>
    </div>
  </section>
</template>

<script>
export default {
  name: "BuildNotes",

  data () {
    return {
      // API results.
      buildid: null,
      cdash: {},
      loading: true,
      errored: false,
    }
  },

  mounted () {
    var path_parts = window.location.pathname.split("/");
    this.buildid = path_parts[path_parts.length - 2];
    var endpoint_path = '/api/v1/viewNotes.php?buildid=' + this.buildid;
    this.$axios
      .get(endpoint_path)
      .then(response => {
        this.cdash = response.data;
        this.cdash.endpoint = this.$baseURL + endpoint_path;
        this.$root.$emit('api-loaded', this.cdash);
      })
      .catch(error => {
        console.log(error)
        this.errored = true
      })
      .finally(() => this.loading = false)
  },
}
</script>
