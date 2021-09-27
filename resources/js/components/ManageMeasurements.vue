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
      <table
        width="800px"
        align="center"
      >
        <tr bgcolor="#CCCCCC">
          <th>Measurement Name</th>
          <th>Show on Test Page</th>
          <th>Show Test Summary Page</th>
          <th>Delete</th>
        </tr>

        <tr v-for="measurement in cdash.measurements">
          <td align="center">
            <input
              v-model="measurement.name"
              type="text"
              size="25"
              name="measurement_name"
              @change="measurement.dirty = true"
            >
          </td>
          <td align="center">
            <input
              v-model="measurement.testpage"
              type="checkbox"
              name="testpage"
              true-value="1"
              false-value="0"
              @change="measurement.dirty = true"
            >
          </td>
          <td align="center">
            <input
              v-model="measurement.summarypage"
              type="checkbox"
              name="summarypage"
              true-value="1"
              false-value="0"
              @change="measurement.dirty = true"
            >
          </td>
          <td align="center">
            <span
              class="glyphicon glyphicon-trash"
              style="cursor: pointer;"
              aria-hidden="true"
              data-toggle="modal"
              data-target="#deleteMeasurementDialog"
              @click="measurementToDelete = measurement.id"
            />
          </td>
        </tr>

        <tr bgcolor="#CADBD9">
          <td align="center">
            <input
              id="newMeasurement"
              v-model="cdash.newmeasurement.name"
              name="newMeasurement"
              type="text"
              size="25"
            >
          </td>
          <td align="center">
            <input
              v-model="cdash.newmeasurement.testpage"
              type="checkbox"
              name="showTestPage"
              value="1"
              true-value="1"
              false-value="0"
            >
          </td>
          <td align="center">
            <input
              v-model="cdash.newmeasurement.summarypage"
              type="checkbox"
              name="showSummaryPage"
              value="1"
              true-value="1"
              false-value="0"
            >
          </td>
          <td />
        </tr>
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
          style="display: none; height:16px; width=16px; margin-top:9px;"
        >
      </div>
      <br>

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
export default {
  name: "ManageMeasurements",

  data () {
    return {
      // API results.
      projectid: null,
      cdash: {},
      loading: true,
      errored: false,
      measurementToDelete: -1,
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
      for (var i = 0; i < response.data.measurements.length; i++) {
        response.data.measurements[i].dirty = false;
      }
    },

    postSetup: function (response) {
      // Create a blank measurement for the user to fill out.
      this.newMeasurement();
    },

    newMeasurement: function() {
      this.cdash.newmeasurement = {
        id: -1,
        dirty: false,
        name: '',
        summarypage: 1,
        testpage: 1
      };
    },

    // Save measurements to database.
    save: function() {
      var measurements_to_save = [];
      // Gather up all the modified measurements.
      for (var i = 0, len = this.cdash.measurements.length; i < len; i++) {
        if (this.cdash.measurements[i].dirty) {
          measurements_to_save.push(this.cdash.measurements[i]);
        }
      }

      // Also save the new measurement if the user filled it out.
      if (this.cdash.newmeasurement.name != '') {
        measurements_to_save.push(this.cdash.newmeasurement);
      }

      // Submit the request.
      var parameters = {
        projectid: this.cdash.projectid,
        measurements: measurements_to_save
      };
      this.$axios.post('api/v1/manageMeasurements.php', parameters)
        .then(response => {
          $("#save_complete").show();
          $("#save_complete").delay(3000).fadeOut(400);
          if (response.data.id > 0) {
          // Assign an id to the "new" measurement and create a new blank one
          // for the user to fill out.
            this.cdash.newmeasurement.id = response.data.id;
            this.cdash.measurements.push(this.cdash.newmeasurement);
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
          $('#deleteMeasurementDialog').modal('hide');
        })
        .catch(error => {
          this.cdash.error = error;
          console.log(error)
        });
    },
  },
}
</script>
