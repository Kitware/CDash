<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div v-if="loading">
      <img :src="$baseURL + '/img/loading.gif'">
    </div>
    <div v-else>
      <table
        class="table table-sm"
        align="center"
      >
        <thead>
          <tr bgcolor="#CCCCCC">
            <th>
              Test Measurement Name
            </th>
            <th>
              Delete
            </th>
          </tr>
        </thead>

        <draggable
          :list="cdash.measurements"
          tag="tbody"
          style="cursor: move;"
          item-key="id"
          @end="updatePositions()"
        >
          <template #item="{ element }">
            <tr :id="'measurement_' + element.id">
              <td>
                <input
                  v-model="element.name"
                  type="text"
                  size="25"
                  name="element.name"
                >
              </td>
              <td>
                <span
                  class="glyphicon glyphicon-trash"
                  style="cursor: pointer;"
                  aria-hidden="true"
                  data-toggle="modal"
                  data-target="#deleteMeasurementDialog"
                  @click="measurementToDelete = element.id"
                />
              </td>
            </tr>
          </template>
        </draggable>

        <tbody>
          <tr bgcolor="#CADBD9">
            <td>
              <input
                id="newMeasurement"
                v-model="newMeasurementName"
                name="newMeasurement"
                type="text"
                size="25"
              >
            </td>
            <td />
          </tr>
        </tbody>
      </table>
      <div class="center-text">
        <input
          id="submit_button"
          name="submit"
          value="Save"
          type="submit"
          @click="save()"
        >
        <img
          id="save_complete"
          :src="$baseURL + '/img/check.gif'"
          style="display: none; height:16px; width:16px; margin-top:9px;"
        >
      </div>
      <br>

      <ul>
        <li>
          Use the form above to add test measurements of type <span class="text-monospace">numeric/double</span> or <span class="text-monospace">text/string</span>. Any measurement added here will be displayed as an extra column on the following pages:
          <ul>
            <li><span class="text-monospace">queryTests.php</span></li>
            <li><span class="text-monospace">testSummary.php</span></li>
            <li><span class="text-monospace">viewTest.php</span></li>
          </ul>
        </li>
        <li>Other types of test measurements (eg. <span class="text-monospace">image/png</span>) are not supported for display on these pages, and they may not be rendered correctly if added here.</li>
        <li>You can drag and drop measurements to change the order in which they are displayed.</li>
        <li>Note that all test measurements are shown on the "Test Details" page (<span class="text-monospace">/test/{id}</span>), regardless of whether they have been added here.</li>
      </ul>

      <!-- confirm delete measurement modal dialog -->
      <div
        id="deleteMeasurementDialog"
        class="modal fade"
        tabindex="-1"
        role="dialog"
        aria-labelledby="deleteMeasurementDialogLabel"
        aria-hidden="true"
      >
        <div
          class="modal-dialog"
          role="document"
        >
          <div class="modal-content">
            <div class="modal-header">
              <h5
                id="deleteMeasurementDialogLabel"
                class="modal-title"
              >
                Remove Measurement
              </h5>
              <button
                type="button"
                class="close"
                data-dismiss="modal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              Are you sure you want to remove this Measurement?
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-secondary"
                data-dismiss="modal"
              >
                Cancel
              </button>
              <button
                id="confirmDeleteMeasurementButton"
                type="button"
                class="btn btn-danger"
                @click="removeMeasurement()"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import draggable from 'vuedraggable'
export default {
  name: "ManageMeasurements",

  components: {
    draggable,
  },

  data () {
    return {
      // API results.
      projectid: null,
      cdash: {},
      loading: true,
      errored: false,
      measurementToDelete: -1,
      newMeasurementName: '',
      newMeasurementSummaryPage: 1,
      newMeasurementTestPage: 1,
    }
  },

  mounted () {
    var path_parts = window.location.pathname.split("/");
    this.projectid = path_parts[path_parts.length - 2];
    var endpoint_path = '/api/v1/manageMeasurements.php?projectid=' + this.projectid;
    ApiLoader.loadPageData(this, endpoint_path);
  },

  methods: {
    preSetup: function(response) {
      // Sort measurements by position.
      if (response.data.measurements) {
        response.data.measurements.sort(function (a, b) {
          return Number(a.position) - Number(b.position);
        });
      }
    },

    // Reinitialize a blank measurement for the user to fill out.
    newMeasurement: function() {
      this.newMeasurementName = '';
      this.newMeasurementSummaryPage = 1;
      this.newMeasurementTestPage = 1;
    },

    // Save measurements to database.
    save: function() {
      // Save the new measurement if the user filled it out.
      var new_measurement = {};
      if (this.newMeasurementName != '') {
        new_measurement.name = this.newMeasurementName;
        new_measurement.id = -1;
        new_measurement.position = this.cdash.measurements.length + 1;
        this.cdash.measurements.push(new_measurement);
      }
      // Submit the request.
      var parameters = {
        projectid: this.cdash.projectid,
        measurements: this.cdash.measurements
      };
      this.$axios.post('api/v1/manageMeasurements.php', parameters)
        .then(response => {
          $("#save_complete").show();
          $("#save_complete").delay(3000).fadeOut(400);
          if (response.data.id > 0) {
            // Assign an id to the measurement we just created,
            // and initialize a new blank measurement for the user to fill out.
            //var idx = this.cdash.measurements.length - 1;
            //this.cdash.measurements[idx].id = response.data.id;
            new_measurement.id = response.data.id;
            this.newMeasurement();
          }
        })
        .catch(error => {
          // Display the error.
          this.cdash.error = error;
          console.log(error)
        });
    },

    // Remove measurement upon confirmation.
    removeMeasurement: function() {
      var parameters = {
        projectid: this.cdash.projectid,
        id: this.measurementToDelete
      };

      this.$axios
        .delete('/api/v1/manageMeasurements.php', { data: parameters})
        .then(response => {
          // Find the measurement to remove.
          for (var i = 0, len = this.cdash.measurements.length; i < len; i++) {
            if (this.cdash.measurements[i].id === this.measurementToDelete) {
              // Remove it from our scope.
              this.cdash.measurements.splice(i, 1);
              break;
            }
          }

          // Recalculate position of remaining measurements.
          this.updatePositions();

          // Hide modal dialog.
          $('#deleteMeasurementDialog').modal('hide');
        })
        .catch(error => {
          this.cdash.error = error;
          console.log(error)
        });
    },

    // Update positions when dragging stops.
    updatePositions: function() {
      for (var i = 0; i < this.cdash.measurements.length; i++) {
        this.cdash.measurements[i].position = i + 1;
      }
    },

  },
}
</script>
