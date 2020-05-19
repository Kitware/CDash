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

      <a
        v-for="(note, index) in cdash.notes"
        :href="`#note${index}`"
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
          class="title-divider column-header"
          @click="note.show = !note.show"
        >
          <b>{{ note.time }} -- {{ note.name }}</b>
          <i :class="[note.show ? 'glyphicon-chevron-down' : 'glyphicon-chevron-right', 'glyphicon']" />
        </div>
        <br>
        <transition name="fade">
          <pre v-show="note.show">{{ note.text }}</pre>
        </transition>
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
        // Collapse notes by default if there's more than one.
        var showNotes = true;
        this.cdash.multiple_notes = false;
        if (response.data.notes.length > 1) {
          this.cdash.multiple_notes = true;
          showNotes = false;
        }
        for (var i = 0; i < response.data.notes.length; i++) {
          response.data.notes[i].show = showNotes;
        }

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
