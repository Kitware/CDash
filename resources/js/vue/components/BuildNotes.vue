<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div v-if="loading">
      <img :src="$baseURL + '/img/loading.gif'">
    </div>
    <div v-else>
      <br>
      <table border="0">
        <tbody>
          <tr>
            <td align="left">
              <b>Site: </b>
              <a
                class="cdash-link"
                :href="$baseURL + '/sites/' + cdash.build.siteid"
              >
                {{ cdash.build.site }}
              </a>
            </td>
          </tr>
          <tr>
            <td align="left">
              <b>Build: </b>
              <a
                class="cdash-link"
                :href="$baseURL + '/build/' + cdash.build.buildid"
              >
                {{ cdash.build.buildname }}
              </a>
            </td>
          </tr>

          <tr v-if="cdash.build.subproject">
            <td align="left">
              <b>SubProject: </b>
              {{ cdash.build.subproject }}
            </td>
          </tr>

          <tr>
            <td align="left">
              <b>Start Time: </b>
              {{ cdash.build.starttime }}
            </td>
          </tr>
        </tbody>
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
          <pre
            v-show="note.show"
            :id="`notetext${index}`"
          >{{ note.text }}</pre>
        </transition>
        <br>
      </div>
      <br>
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
export default {
  name: 'BuildNotes',

  data () {
    return {
      // API results.
      buildid: null,
      cdash: {},
      loading: true,
      errored: false,
    };
  },

  mounted () {
    const path_parts = window.location.pathname.split('/');
    this.buildid = path_parts[path_parts.length - 2];
    const endpoint_path = `/api/v1/viewNotes.php?buildid=${this.buildid}`;
    ApiLoader.loadPageData(this, endpoint_path);
  },

  methods: {
    preSetup: function(response) {
      // Collapse notes by default if there's more than one.
      let showNotes = true;
      this.cdash.multiple_notes = false;
      if (response.data.notes.length > 1) {
        this.cdash.multiple_notes = true;
        showNotes = false;
      }
      for (let i = 0; i < response.data.notes.length; i++) {
        response.data.notes[i].show = showNotes;
      }
    },
  },
};
</script>
