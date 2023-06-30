<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div v-if="loading">
      <img :src="$baseURL + '/img/loading.gif'">
    </div>
    <div v-else-if="cdash.projectcreated == 1">
      The project <b>{{ cdash.project_name }}</b> has been created successfully.
      <br>
      <br>
      Click here to access the
      <a :href="$baseURL + '/index.php?project=' + cdash.project.name_encoded">CDash project page</a>
      <br>
      Click here to
      <a :href="$baseURL + '/project/' + cdash.project.Id + '/edit'">edit the project</a>
      <br>
      Click here to
      <a :href="$baseURL + '/project/' + cdash.project.Id + '/ctest_configuration'">download the CTest configuration file</a>
      <br>
    </div>
    <div v-else>
      <table v-if="cdash.edit == 1">
        <tr>
          <td>
            <div align="right">
              <strong>Project:</strong>
            </div>
          </td>
          <td>
            <select
              v-model="selectedProject"
              name="projectSelection"
              @change="switchProject()"
            >
              <option
                v-for="proj in cdash.availableprojects"
                :value="proj.id"
                :selected="proj.id == cdash.projectid"
              >
                {{ proj.name }}
              </option>
            </select>
          </td>
        </tr>
      </table>

      <div
        v-if="cdash.project || cdash.edit == 0"
        name="projectForm"
      >
        <div class="tabs">
          <!-- navigation panel -->
          <b-nav
            tabs
          >
            <b-nav-item
              href="#Info"
              :active="activeTab == 'Info'"
              @click="setTabByName('Info');"
            >
              Information
            </b-nav-item>

            <b-nav-item
              href="#Logo"
              :active="activeTab == 'Logo'"
              :disabled="cdash.tabs.Logo.disabled"
              @click="setTabByName('Logo');"
            >
              Logo
            </b-nav-item>

            <b-nav-item
              href="#Repos"
              :active="activeTab == 'Repos'"
              :disabled="cdash.tabs.Repos.disabled"
              @click="setTabByName('Repos');"
            >
              Repository
            </b-nav-item>

            <b-nav-item
              href="#Testing"
              :active="activeTab == 'Testing'"
              :disabled="cdash.tabs.Testing.disabled"
              @click="setTabByName('Testing');"
            >
              Testing
            </b-nav-item>

            <b-nav-item
              href="#Email"
              :active="activeTab == 'Email'"
              :disabled="cdash.tabs.Email.disabled"
              @click="setTabByName('Email');"
            >
              Email
            </b-nav-item>

            <b-nav-item
              v-if="cdash.edit == 1"
              href="#Spam"
              :active="activeTab == 'Spam'"
              @click="setTabByName('Spam');"
            >
              Spam
            </b-nav-item>

            <b-nav-item
              href="#Misc"
              :active="activeTab == 'Misc'"
              :disabled="cdash.tabs.Misc.disabled"
              @click="setTabByName('Misc');"
            >
              Miscellaneous
            </b-nav-item>
          </b-nav>

          <!-- tab contents -->
          <div class="tab-content">
            <!-- Information tab -->
            <div :class="['tab-pane', { 'active': activeTab === 'Info' }]">
              <div class="tab_help" />
              <table width="550">
                <tr v-if="cdash.edit == 0">
                  <td />
                  <td>
                    <div align="right">
                      <strong>Name:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="name"
                      v-model="cdash.project.Name"
                      name="name"
                      type="text"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('name_help')"
                    >
                    <span
                      id="name_help"
                      class="help_content"
                    >
                      <strong>Name of the project</strong>
                      <br>
                      CDash allows spaces for the name of the project but it is
                      not recommended. If the project’s name contains space
                      make sure you replace the space by the corresponding HTML
                      entity, i.e. %20.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Description:</strong>
                    </div>
                  </td>
                  <td>
                    <textarea
                      id="description"
                      v-model="cdash.project.Description"
                      name="description"
                      rows="5"
                      cols="40"
                      @change="cdash.changesmade = true"
                      @focus="clearHelp()"
                    />
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Home URL :</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="homeURL"
                      v-model="cdash.project.HomeUrl"
                      name="homeURL"
                      size="50"
                      type="text"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('homeurl_help')"
                    >
                    <span
                      id="homeurl_help"
                      class="help_content"
                    >
                      <strong>Home URL</strong>
                      <br>
                      Home url of the project (with or without http://) . This
                      URL is referred in the top menu of the dashboard for this
                      project.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Bug Tracker URL:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="bugURL"
                      v-model="cdash.project.BugTrackerUrl"
                      name="bugURL"
                      size="50"
                      type="text"
                      @change="cdash.changesmade = true"
                      @focus="clearHelp()"
                    >
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Bug Tracker File URL:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="bugFileURL"
                      v-model="cdash.project.BugTrackerFilerUrl"
                      name="bugFileURL"
                      type="text"
                      size="50"
                      @change="cdash.changesmade = true"
                      @focus="clearHelp()"
                    >
                  </td>
                </tr>

                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Bug Tracker Issue Creation:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="issuecreation"
                      type="checkbox"
                      name="issuecreation"
                      @focus="showHelp('issuecreation_help')"
                    >
                    <img
                      :src="$baseURL + '/img/help.gif'"
                      border="0"
                      @mouseover="showHelp('issuecreation_help')"
                    >
                    <span
                      id="issuecreation_help"
                      class="help_content"
                    >
                      <b>Enable Issue Creation</b>
                      <br>
                      CDash can display a link on the build summary page if you use a
                      supported bug tracking system.  This makes it easy to create
                      a new issue when you notice that a build is broken.
                    </span>
                  </td>
                </tr>
                <transition name="fade">
                  <tr
                    v-show="issuecreation"
                  >
                    <td />
                    <td>
                      <div align="right">
                        <strong>Bug Tracker Type:</strong>
                      </div>
                    </td>
                    <td>
                      <select
                        id="bugtrackertype"
                        v-model="cdash.project.BugTrackerType"
                        name="bugtrackertype"
                        @change="cdash.changesmade = true; changeTrackerType()"
                        @focus="showHelp('issuecreation_help')"
                      >
                        <option
                          value=""
                          selected
                        >
                          Other
                        </option>
                        <option
                          v-for="type in bugtrackertypes"
                          :value="type"
                          :selected="type == cdash.project.BugTrackerType"
                        >
                          {{ type }}
                        </option>
                      </select>
                      <a
                        href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                        target="blank"
                      >
                        <img
                          :src="$baseURL + '/img/help.gif'"
                          border="0"
                          @mouseover="showHelp('issuecreation_help')"
                        >
                      </a>
                    </td>
                  </tr>
                </transition>
                <transition name="fade">
                  <tr
                    v-show="issuecreation"
                  >
                    <td />
                    <td>
                      <div align="right">
                        <strong>New Issue URL:</strong>
                      </div>
                    </td>
                    <td>
                      <input
                        id="newissueURL"
                        v-model="cdash.project.BugTrackerNewIssueUrl"
                        name="newissueURL"
                        type="text"
                        size="50"
                        @change="cdash.changesmade = true"
                        @focus="showHelp('newissueurl_help')"
                      >
                      <span
                        id="newissueurl_help"
                        class="help_content"
                      >
                        <b>New Issue URL</b>
                        Link to create a new issue in your bug tracker.
                        <br>
                        {{ newissuehelp }}
                      </span>
                    </td>
                  </tr>
                </transition>

                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Documentation URL:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="docURL"
                      v-model="cdash.project.DocumentationUrl"
                      name="docURL"
                      type="text"
                      size="50"
                      @change="cdash.changesmade = true"
                      @focus="clearHelp()"
                    >
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Public Dashboard:</strong>
                    </div>
                  </td>
                  <td>
                    <select
                      v-model="cdash.project.Public"
                      name="public"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('public_help')"
                    >
                      <option value="0">
                        Private
                      </option>
                      <option value="1">
                        Public
                      </option>
                      <option value="2">
                        Protected
                      </option>
                    </select>
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('public_help')"
                      >
                    </a>
                    <span
                      id="public_help"
                      class="help_content"
                    >
                      <b>Public dashboard</b>
                      <br>
                      <ul>
                        <li>
                          Public projects can be accessed by anybody.
                        </li>
                        <li>
                          Protected projects can only be accessed by logged in users.
                        </li>
                        <li>
                          Users need to be explicitly granted access to view private projects.
                        </li>
                      </ul>
                    </span>
                  </td>
                </tr>

                <tr>
                  <!-- Checkbox to enable/disable the "Authenticated Submissions"
                       feature for this project -->
                  <td colspan="2">
                    <div align="right">
                      <strong>Authenticate Submissions</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.AuthenticateSubmissions"
                      type="checkbox"
                      name="authenticateSubmissions"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('authenticateSubmissions_help')"
                    >
                    <img
                      :src="$baseURL + '/img/help.gif'"
                      border="0"
                      @mouseover="showHelp('authenticateSubmissions_help')"
                    >
                    <span
                      id="authenticateSubmissions_help"
                      class="help_content"
                    >
                      <b>Authenticate Submissions</b>
                      <br>
                      Only accept submissions bearing a valid authentication token.
                    </span>
                  </td>
                </tr>

                <tr>
                  <td />
                  <td />
                  <td align="right">
                    <img
                      v-if="cdash.edit == 0"
                      :src="$baseURL + '/img/next.png'"
                      style="cursor:pointer;"
                      alt="next"
                      title="Next Step"
                      @click="nextTab()"
                    >
                  </td>
                </tr>
              </table>
            </div>

            <!-- Logo tab -->
            <div :class="['tab-pane', { 'active': activeTab === 'Logo' }]">
              <div class="tab_help" />
              <table width="550">
                <tr>
                  <td />
                  <td>
                    <label for="logo">Select Logo</label>
                    <input
                      name="logo"
                      type="file"
                      accept="image/*"
                      @change="uploadLogo"
                    >
                    <i v-show="logoTooLarge">
                      File too large ({{ logoHeight }}px).  Max height allowed is 100px
                    </i>
                    <img
                      :src="previewLogo"
                      class="thumb"
                    >
                    <button
                      v-show="previewLogo"
                      @click="previewLogo = null"
                    >
                      Remove
                    </button>

                    <span
                      id="logo_help"
                      class="help_content"
                    >
                      <strong>Logo</strong>
                      <br>
                      Logo for this project. For best results choose an image with
                      a transparent background.  The logo's height should be no
                      more than 100 pixels.
                    </span>
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                      @mouseover="showHelp('logo_help')"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                      >
                    </a>
                  </td>
                </tr>
                <tr v-if="cdash.edit == 1">
                  <td />
                  <td>
                    <div valign="top">
                      <strong>Current logo:</strong>
                    </div>
                    <span v-if="cdash.project.ImageId == 0">
                      [none]
                    </span>
                    <img
                      v-if="cdash.project.ImageId != 0"
                      id="projectlogo"
                      border="0"
                      :alt="cdash.project_name"
                      :src="$baseURL + '/image/' + cdash.project.ImageId"
                    >
                  </td>
                </tr>
                <tr>
                  <td />
                  <td />
                  <td align="right">
                    <br>
                    <br>
                    <br>
                    <br>
                    <br>
                    <br>
                    <div v-if="cdash.edit == 0">
                      <img
                        :src="$baseURL + '/img/previous.png'"
                        title="Previous Step"
                        alt="previous"
                        style="cursor:pointer;"
                        @click="previousTab()"
                      >
                      <img
                        :src="$baseURL + '/img/next.png'"
                        title="Next Step"
                        alt="next"
                        style="cursor:pointer;"
                        @click="nextTab()"
                      >
                    </div>
                  </td>
                </tr>
              </table>
            </div>

            <!-- Repository tab -->
            <div :class="['tab-pane', { 'active': activeTab === 'Repos' }]">
              <div class="tab_help" />
              <table width="550">
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Repository Viewer URL:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="cvsURL"
                      v-model="cdash.project.CvsUrl"
                      name="cvsURL"
                      type="text"
                      size="50"
                      @change="changeViewerType()"
                      @focus="showHelp('svnViewer_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('svnViewer_help')"
                      >
                    </a>
                    <span
                      id="svnViewer_help"
                      class="help_content"
                    >
                      <b>Repository Viewer URL</b>
                      URL of the Repository viewer
                      <ul>
                        <li> ViewCVS:
                          public.kitware.com/cgi-bin/viewcvs.cgi/?cvsroot=CMake
                        </li>
                        <li>
                          WebSVN:
                          <a
                            href="https://www.kitware.com/websvn/listing.php?repname=MyRepository"
                            class="external free"
                            title="https://www.kitware.com/websvn/listing.php?repname=MyRepository"
                            rel="nofollow"
                          >https://www.kitware.com/websvn/listing.php?repname=MyRepository</a>
                          <br>
                          <b>(listing.php is important)</b>
                        </li>
                      </ul>
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Repository Viewer Type:</strong>
                    </div>
                  </td>
                  <td>
                    <select
                      id="cvsviewertype"
                      v-model="cdash.project.CvsViewerType"
                      name="cvsviewertype"
                      @change="changeViewerType()"
                      @focus="showHelp('svnViewerType_help')"
                    >
                      <option
                        v-for="viewer in cdash.vcsviewers"
                        :value="viewer.value"
                      >
                        {{ viewer.description }}
                      </option>
                    </select>
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('svnViewerType_help')"
                      >
                    </a>
                    <span
                      id="svnViewerType_help"
                      class="help_content"
                    >
                      <b>Repository View Type</b>
                      <br>
                      Select an appropriate repository viewer depending on
                      your current configuration.
                    </span>
                    <span
                      id="svnRepository_help"
                      class="help_content"
                    >
                      <b>Repository</b>
                      <br>
                      In order to get the daily updates, CDash should be able to
                      access the current repository. It is recommended to use
                      the anonymous access, for instance
                      :pserver:anoncvs@myproject.org:/cvsroot/MyProject. If the
                      project needs ssh access, make sure that the user running
                      the webserver running CDash has the proper ssh keys.
                    </span>
                    <span
                      id="svnUsername_help"
                      class="help_content"
                    >
                      <b>Username</b>
                      <br>
                      Optional. Provide a username if you do not wish to use anonymous access to your repository. For GitHub, this should be your <b>installation ID</b>.
                    </span>
                    <span
                      id="svnPassword_help"
                      class="help_content"
                    >
                      <b>Password</b>
                      <br>
                      The password corresponding to the above user.  WARNING: this password will be stored in plaintext in the database. For GitHub, this field is unused.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Test URL:</strong>
                    </div>
                  </td>
                  <td>
                    <font size="1">
                      <span>{{ repositoryurlexample }}</span>
                    </font>
                  </td>
                </tr>
                <template v-for="repo in cdash.project.repositories">
                  <tr>
                    <td />
                    <td>
                      <div align="right">
                        <strong>Repository:</strong>
                      </div>
                    </td>
                    <td>
                      <input
                        v-model="repo.url"
                        type="text"
                        size="50"
                        :name="'vcsRepository[' + repo.id + ']'"
                        @change="cdash.changesmade = true"
                        @focus="showHelp('svnRepository_help')"
                      >
                      <a
                        href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                        target="blank"
                      >
                        <img
                          :src="$baseURL + '/img/help.gif'"
                          border="0"
                          @mouseover="showHelp('svnRepository_help')"
                        >
                      </a>
                    </td>
                  </tr>
                  <tr>
                    <td />
                    <td>
                      <div align="right">
                        <strong>Branch:</strong>
                      </div>
                    </td>
                    <td>
                      <input
                        v-model="repo.branch"
                        type="text"
                        size="50"
                        :name="'vcsBranch[' + repo.id + ']'"
                        @change="cdash.changesmade = true"
                      >
                    </td>
                  </tr>
                  <tr>
                    <td />
                    <td>
                      <div align="right">
                        <strong>Username:</strong>
                      </div>
                    </td>
                    <td>
                      <input
                        v-model="repo.username"
                        type="text"
                        size="50"
                        name="'vcsUsername[' + repo.id + ']'"
                        @change="cdash.changesmade = true"
                        @focus="showHelp('svnUsername_help')"
                      >
                      <a
                        href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                        target="blank"
                      >
                        <img
                          :src="$baseURL + '/img/help.gif'"
                          border="0"
                          @mouseover="showHelp('svnUsername_help')"
                        ></a>
                    </td>
                  </tr>
                  <tr>
                    <td />
                    <td>
                      <div align="right">
                        <strong>Password:</strong>
                      </div>
                    </td>
                    <td>
                      <input
                        v-model="repo.password"
                        type="password"
                        size="50"
                        name="'vcsPassword[' + repo.id + ']'"
                        @change="cdash.changesmade = true"
                        @focus="showHelp('svnPassword_help')"
                      >
                      <a
                        href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                        target="blank"
                      >
                        <img
                          :src="$baseURL + '/img/help.gif'"
                          border="0"
                          @mouseover="showHelp('svnPassword_help')"
                        ></a>
                    </td>
                  </tr>
                </template>
                <tr v-if="cdash.edit == 1">
                  <td />
                  <td />
                  <td>
                    <input
                      name="addRepository"
                      type="submit"
                      value="Add another repository"
                      @click="addRepository(repo)"
                    >
                  </td>
                </tr>
                <tr>
                  <td />
                  <td />
                  <td align="right">
                    <div v-if="cdash.edit == 0">
                      <img
                        :src="$baseURL + '/img/previous.png'"
                        title="Previous Step"
                        alt="previous"
                        style="cursor:pointer;"
                        @click="previousTab()"
                      >
                      <img
                        :src="$baseURL + '/img/next.png'"
                        title="Next Step"
                        alt="next"
                        style="cursor:pointer;"
                        @click="nextTab()"
                      >
                    </div>
                  </td>
                </tr>
              </table>
            </div>

            <!-- Testing tab -->
            <div :class="['tab-pane', { 'active': activeTab === 'Testing' }]">
              <div class="tab_help" />
              <table width="550">
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Testing Data URL:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="testingDataUrl"
                      v-model="cdash.project.TestingDataUrl"
                      name="testingDataUrl"
                      type="text"
                      size="30"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('TestingDataUrl_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('TestingDataUrl_help')"
                      >
                    </a>
                    <span
                      id="TestingDataUrl_help"
                      class="help_content"
                    >
                      <b>Testing Data URL</b>
                      <br>
                      CDash can display a link on the main dashboard page
                      to the URL of your testing data
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Nightly Start Time:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="nightlyTime"
                      v-model="cdash.project.NightlyTime"
                      name="nightlyTime"
                      type="text"
                      size="20"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('NightlyStart_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('NightlyStart_help')"
                      >
                    </a>
                    <span
                      id="NightlyStart_help"
                      class="help_content"
                    >
                      <b>Nightly Start Time</b>
                      <p>
                        CDash displays results using a 24 hour window.
                        The nightly start time defines the beginning of this window.
                      </p>
                      <p>
                        Format as <i>HH:MM:SS TZ</i>, i.e.
                        21:00:00 America/New_York. Build times are shown in the
                        chosen time zone. CDash adjusts for DST if necessary.
                        <a
                          href="https://www.php.net/manual/en/timezones.php"
                          target="blank"
                        >List of supported timezones</a>
                      </p>
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Coverage Threshold:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="coverageThreshold"
                      v-model="cdash.project.CoverageThreshold"
                      name="coverageThreshold"
                      type="text"
                      size="2"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('CoverageThres_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('CoverageThres_help')"
                      >
                    </a>
                    <span
                      id="CoverageThres_help"
                      class="help_content"
                    >
                      <b>Coverage threshold</b>
                      <br>
                      CDash marks the coverage has passed (green) if the global
                      coverage for a build or specific files is above this
                      threshold. It is recommended to set the coverage threshold
                      to a high value and increase it when the coverage is
                      getting higher.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Enable test timing:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.ShowTestTime"
                      type="checkbox"
                      name="showTestTime"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('EnableTestTiming_help')"
                    >
                    <a
                      href="https://public.kitware.com/Wiki/CDash:Design#Test_Timing"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('EnableTestTiming_help')"
                      >
                    </a>
                    <span
                      id="EnableTestTiming_help"
                      class="help_content"
                    >
                      <b>Enable test timing</b>
                      <br>
                      Enable/Disable test timing for this project.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Test time standard deviation multiplier:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="testTimeStd"
                      v-model="cdash.project.TestTimeStd"
                      name="testTimeStd"
                      type="text"
                      size="4"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('TimeDeviation_help')"
                    >
                    <a
                      href="https://public.kitware.com/Wiki/CDash:Design#Test_Timing"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('TimeDeviation_help')"
                      >
                    </a>
                    <span
                      id="TimeDeviation_help"
                      class="help_content"
                    >
                      <b>Test time standard deviation multiplier</b>
                      <br>
                      Set a multiplier for the standard deviation for a test
                      time. If the time for a test is higher than
                      mean+multiplier*standarddeviation, the test time status is
                      marked as failed. Default is 4 if not specified. Note that
                      changing this value doesn’t affect previous builds but
                      only builds submitted after the modification.
                    </span>
                  </td>
                </tr>

                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Test time standard deviation threshold:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="testTimeStdThreshold"
                      v-model="cdash.project.TestTimeStdThreshold"
                      name="testTimeStdThreshold"
                      type="text"
                      size="4"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('TimeDeviationThreshold_help')"
                    >
                    <a
                      href="https://public.kitware.com/Wiki/CDash:Design#Test_Timing"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('TimeDeviationThreshold_help')"
                      >
                    </a>
                    <span
                      id="TimeDeviationThreshold_help"
                      class="help_content"
                    >
                      <b>Test time standard deviation threshold</b>
                      <br>
                      Set a minimum standard deviation for a test time. If the
                      current standard deviation for a test is lower than this
                      threshold then the threshold is used instead. This is
                      particularly important, for tests that have a very low
                      standard deviation but still some variability. Default
                      threshold is set to 2 if not specified. Note that changing
                      this value doesn’t affect previous builds but only builds
                      submitted after the modification.
                    </span>
                  </td>
                </tr>

                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Test time # max failures before flag:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="testTimeMaxStatus"
                      v-model="cdash.project.TestTimeMaxStatus"
                      name="testTimeMaxStatus"
                      type="text"
                      size="4"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('TimeMaxStatus_help')"
                    >
                    <a
                      href="https://public.kitware.com/Wiki/CDash:Design#Test_Timing"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('TimeMaxStatus_help')"
                      >
                    </a>
                    <span
                      id="TimeMaxStatus_help"
                      class="help_content"
                    >
                      <b>Test time max status</b>
                      <br>
                      Set the number of times a test must violate the time status check
                      before it is flagged. For example, if this is set to 2, then a test
                      will need to run more slowly than expected twice in a row before it
                      is marked as having failed the time status check.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td />
                  <td align="right">
                    <div v-if="cdash.edit == 0">
                      <img
                        :src="$baseURL + '/img/previous.png'"
                        title="Previous Step"
                        alt="previous"
                        style="cursor:pointer;"
                        @click="previousTab()"
                      >
                      <img
                        :src="$baseURL + '/img/next.png'"
                        title="Next Step"
                        alt="next"
                        style="cursor:pointer;"
                        @click="nextTab()"
                      >
                    </div>
                  </td>
                </tr>
              </table>
            </div>

            <!-- Email tab -->
            <div :class="['tab-pane', { 'active': activeTab === 'Email' }]">
              <div class="tab_help" />
              <table width="550">
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Email submission failures:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.EmailBrokenSubmission"
                      type="checkbox"
                      name="emailBrokenSubmission"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('emailBroken_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('emailBroken_help')"
                      >
                    </a>
                    <span
                      id="emailBroken_help"
                      class="help_content"
                    >
                      <b>Email broken submission</b>
                      <br>
                      Enable/Disable sending email for broken submissions for
                      this project. This is a general feature.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Email redundant failures:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.EmailRedundantFailures"
                      type="checkbox"
                      name="emailRedundantFailures"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('emailRedundant_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('emailRedundant_help')"
                      >
                    </a>
                    <span
                      id="emailRedundant_help"
                      class="help_content"
                    >
                      <b>Email redundant failures</b>
                      <br>
                      Enable/Disable sending email even if a build has been
                      failing previously. If not checked, CDash sends an email
                      only on the first failure.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Email administrator:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.EmailAdministrator"
                      type="checkbox"
                      name="emailAdministrator"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('emailAdministrator_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('emailAdministrator_help')"
                      >
                    </a>
                    <span
                      id="emailAdministrator_help"
                      class="help_content"
                    >
                      <b>Email administator</b>
                      <br>
                      Enable/Disable sending email when the XML parsing fails or
                      any issues related to the project administration.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Email low coverage:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.EmailLowCoverage"
                      type="checkbox"
                      name="emailLowCoverage"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('emailCoverage_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('emailCoverage_help')"
                      >
                    </a>
                    <span
                      id="emailCoverage_help"
                      class="help_content"
                    >
                      <b>Email low coverage</b>
                      <br>
                      Enable/Disable sending email when the coverage for files
                      is lower than the "Coverage Threshold" value specified on
                      the "Testing" tab.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Email test timing changed:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.EmailTestTimingChanged"
                      type="checkbox"
                      name="emailTestTimingChanged"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('emailTiming_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('emailTiming_help')"
                      >
                    </a>
                    <span
                      id="emailTiming_help"
                      class="help_content"
                    >
                      <b>Email test timing change</b>
                      <br>
                      Enable/Disable sending email when a test timing has
                      changed. This feature is currently not implemented.
                    </span>
                  </td>
                </tr>

                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Maximum number of items in email:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="emailMaxItems"
                      v-model="cdash.project.EmailMaxItems"
                      name="emailMaxItems"
                      type="text"
                      size="4"
                      @change="cdash.changesmade = true"
                      @focus="clearHelp()"
                    >
                  </td>
                </tr>

                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Maximum number of characters per item in email:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="emailMaxChars"
                      v-model="cdash.project.EmailMaxChars"
                      name="emailMaxChars"
                      type="text"
                      size="4"
                      @change="cdash.changesmade = true"
                      @focus="clearHelp()"
                    >
                  </td>
                </tr>
                <tr>
                  <td />
                  <td />
                  <td align="right">
                    <div v-if="cdash.edit == 0">
                      <img
                        :src="$baseURL + '/img/previous.png'"
                        title="Previous Step"
                        alt="previous"
                        style="cursor:pointer;"
                        @click="previousTab()"
                      >
                      <img
                        :src="$baseURL + '/img/next.png'"
                        title="Next Step"
                        alt="next"
                        style="cursor:pointer;"
                        @click="nextTab()"
                      >
                    </div>
                  </td>
                </tr>
              </table>
            </div>

            <!-- Spam tab -->
            <div
              v-if="cdash.edit == 1"
              :class="['tab-pane', { 'active': activeTab === 'Spam' }]"
            >
              <div class="tab_help" />
              <table width="550">
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>
                        Block List
                        <a
                          href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                          target="blank"
                        >
                          <img
                            :src="$baseURL + '/img/help.gif'"
                            border="0"
                            @mouseover="showHelp('blockList_help')"
                          >
                        </a>
                      </strong>
                    </div>
                  </td>
                  <td>
                    <table
                      v-if="cdash.project.blockedbuilds.length > 0"
                      width="100%"
                      border="0"
                    >
                      <tr>
                        <th>Name</th>
                        <th>Site</th>
                        <th>IP address</th>
                        <th />
                      </tr>
                      <tr v-for="blockedbuild in cdash.project.blockedbuilds">
                        <td>
                          {{ blockedbuild.buildname }}
                        </td>
                        <td>
                          {{ blockedbuild.sitename }}
                        </td>
                        <td>
                          {{ blockedbuild.ipaddress }}
                        </td>
                        <td>
                          <span
                            class="glyphicon glyphicon-trash"
                            @click="removeBlockedBuild(blockedbuild)"
                          />
                        </td>
                      </tr>
                    </table>
                  </td>
                  <span
                    id="blockList_help"
                    class="help_content"
                  >
                    <b>Block List</b>
                    <br>
                    Submission to CDash can be blocked given a sitename,
                    buildname and IP address in order to prevent submissions
                    from an unwanted host.
                  </span>
                </tr>

                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Build Name:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="spam.buildname"
                      type="text"
                      name="spambuildname"
                    >
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Site Name:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="spam.sitename"
                      type="text"
                      name="spamsitename"
                    >
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>IP Address:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="spam.ipaddress"
                      type="text"
                      name="spamip"
                    >
                  </td>
                </tr>
                <tr>
                  <td />
                  <td />
                  <td>
                    <input
                      type="submit"
                      name="SpamFilter"
                      value="Add filter"
                      @click="addBlockedBuild"
                    >
                    <transition name="fade">
                      <img
                        v-show="buildblocked"
                        id="build_blocked"
                        :src="$baseURL + '/img/check.gif'"
                      >
                    </transition>
                  </td>
                </tr>

                <tr>
                  <td />
                  <td />
                  <td align="right">
                    <div v-if="cdash.edit == 0">
                      <img
                        :src="$baseURL + '/img/previous.png'"
                        title="Previous Step"
                        alt="previous"
                        style="cursor:pointer;"
                        @click="previousTab()"
                      >
                      <img
                        :src="$baseURL + '/img/next.png'"
                        title="Next Step"
                        alt="next"
                        style="cursor:pointer;"
                        @click="nextTab()"
                      >
                    </div>
                  </td>
                </tr>
              </table>
            </div>

            <!-- Miscellaneous tab -->
            <div :class="['tab-pane', { 'active': activeTab === 'Misc' }]">
              <div class="tab_help" />
              <table width="550">
                <!-- downloading the CTestConfig.cmake -->
                <tr v-if="cdash.edit == 1">
                  <td />
                  <td>
                    <div align="right">
                      <strong>Download CTestConfig:</strong>
                    </div>
                  </td>
                  <td>
                    <a :href="$baseURL + '/project/' + cdash.project.Id + '/ctest_configuration'">
                      CTestConfig.cmake
                    </a>
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('ctestConfig_help')"
                      >
                    </a>
                  </td>
                  <span
                    id="ctestConfig_help"
                    class="help_content"
                  >
                    <b>Download CTest config</b>
                    <br>
                    Automatically generated CTest configuration file.
                    downloading this file and putting it at the root of your
                    project, allows to quickly get started with CTest/CDash
                    and submitting to the dashboard.
                  </span>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Google Analytics Tracker:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="googleTracker"
                      v-model="cdash.project.GoogleTracker"
                      name="googleTracker"
                      type="text"
                      size="30"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('google_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('google_help')"
                      >
                    </a>
                    <span
                      id="google_help"
                      class="help_content"
                    >
                      <b>Google Analytics Tracker</b>
                      <br>
                      CDash supports visitor tracking through Google analytics.
                      See “Adding Google Analytics” for more information.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Show site IP addresses:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.ShowIPAddresses"
                      type="checkbox"
                      name="showIPAddresses"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('showSiteIPAddresses_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('showSiteIPAddresses_help')"
                      >
                    </a>
                    <span
                      id="showSiteIPAddresses_help"
                      class="help_content"
                    >
                      <b>Show Site IP Addresses</b>
                      <br>
                      Enable/Disable the display of IP addresses of the sites
                      submitting to this project.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Display Labels:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.DisplayLabels"
                      type="checkbox"
                      name="displayLabels"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('displayLabels_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('displayLabels_help')"
                      >
                    </a>
                    <span
                      id="displayLabels_help"
                      class="help_content"
                    >
                      <b>Display Labels</b>
                      <br>
                      Enable/Disable the display of the label column for the
                      project. The labels are submitted by the client as part of
                      the submission.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Share Label Filters</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.ShareLabelFilters"
                      type="checkbox"
                      name="shareLabelFilters"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('shareLabelFilters_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('shareLabelFilters_help')"
                      >
                    </a>
                    <span
                      id="shareLabelFilters_help"
                      class="help_content"
                    >
                      <b>Share Label Filters</b>
                      <br>
                      Pass label filters set on index.php to other
                      related pages like viewTest.php and queryTest.php.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>View SubProjects Link</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.ViewSubProjectsLink"
                      type="checkbox"
                      name="viewSubProjectsLink"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('viewSubProjectsLink_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('viewSubProjectsLink_help')"
                      >
                    </a>
                    <span
                      id="viewSubProjectsLink_help"
                      class="help_content"
                    >
                      <b>View SubProjects Link</b>
                      <br>
                      If this Project uses SubProjects, show a per-SubProject
                      breakdown by default. If unchecked, CDash will show per-build
                      results instead.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Show coverage code:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="cdash.project.ShowCoverageCode"
                      type="checkbox"
                      name="showCoverageCode"
                      true-value="1"
                      false-value="0"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('showCoverageCode_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('showCoverageCode_help')"
                      >
                    </a>
                    <span
                      id="showCoverageCode_help"
                      class="help_content"
                    >
                      <b>Display Source Code in Coverage</b>
                      <br>
                      Enable/Disable the display of code coverage for the project. Only administrators
                      of the projects can see the source code in the coverage section when this option is disabled.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>AutoRemove Timeframe (days):</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="autoremoveTimeframe"
                      v-model="cdash.project.AutoremoveTimeframe"
                      type="text"
                      name="autoremoveTimeframe"
                      size="10"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('autoremoveTimeframe_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('autoremoveTimeframe_help')"
                      >
                    </a>
                    <span
                      id="autoremoveTimeframe_help"
                      class="help_content"
                    >
                      <b>AutoRemove Timeframe</b>
                      <br>
                      On the first submission of the day, remove builds that are
                      older than X number of days.
                      If this value is less than 2 days, no builds are removed.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>AutoRemove Max Builds:</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="autoremoveMaxBuilds"
                      v-model="cdash.project.AutoremoveMaxBuilds"
                      type="text"
                      name="autoremoveMaxBuilds"
                      size="10"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('autoremoveMaxBuilds_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('autoremoveMaxBuilds_help')"
                      >
                    </a>
                    <span
                      id="autoremoveMaxBuilds_help"
                      class="help_content"
                    >
                      <b>AutoRemove max builds</b>
                      <br>
                      On the first submission of the day, remove builds that are
                      older than X number of days.
                      The maximum number of builds that should be removed.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>File upload quota (GB):</strong>
                    </div>
                  </td>
                  <td>
                    <input
                      id="uploadQuota"
                      v-model="cdash.project.UploadQuota"
                      type="text"
                      name="uploadQuota"
                      size="10"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('uploadQuota_help')"
                    >
                    <a
                      href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                      target="blank"
                    >
                      <img
                        :src="$baseURL + '/img/help.gif'"
                        border="0"
                        @mouseover="showHelp('uploadQuota_help')"
                      >
                    </a>
                    <span
                      id="uploadQuota_help"
                      class="help_content"
                    >
                      <b>File upload quota</b>
                      <br>
                      Enter how many gigabytes of uploaded files to store with this project.
                      If this quota is exceeded, older files will be deleted to make room when
                      new ones are uploaded. The number must be less than or equal to the maximum
                      per-project quota of {{ cdash.project.maxuploadquota }} GB.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Web API Key:</strong>
                    </div>
                  </td>
                  <td @mouseover="showHelp('webapikey_help')">
                    {{ cdash.project.webapikey }}
                    <span
                      id="webapikey_help"
                      class="help_content"
                    >
                      <b>Web API key</b>
                      <br>
                      Use this key when calling the login method of the web API.
                      It will return a token that you can temporarily use for authenticated access
                      to other web API methods.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Warnings Filters:</strong>
                    </div>
                  </td>
                  <td>
                    <textarea
                      id="warningsFilter"
                      v-model="cdash.project.WarningsFilter"
                      name="warningsFilter"
                      rows="5"
                      cols="40"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('warningsFilter_help')"
                    />
                    <span
                      id="warningsFilter_help"
                      class="help_content"
                    >
                      <b>Warnings filter</b>
                      <br>
                      Perform substring matching to filter out build warnings.
                      Enter one filter per line.
                      Any warnings containing one of these filters will not be saved to the database.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td>
                    <div align="right">
                      <strong>Errors Filters:</strong>
                    </div>
                  </td>
                  <td>
                    <textarea
                      id="errorsFilter"
                      v-model="cdash.project.ErrorsFilter"
                      name="errorsFilter"
                      rows="5"
                      cols="40"
                      @change="cdash.changesmade = true"
                      @focus="showHelp('errorsFilter_help')"
                    />
                    <span
                      id="errorsFilter_help"
                      class="help_content"
                    >
                      <b>Errors filter</b>
                      <br>
                      Perform substring matching to filter out build errors.
                      Enter one filter per line.
                      Any errors containing one of these filters will not be saved to the database.
                    </span>
                  </td>
                </tr>
                <tr>
                  <td />
                  <td />
                  <td align="right">
                    <div v-if="cdash.edit == 0">
                      <img
                        :src="$baseURL + '/img/previous.png'"
                        style="cursor:pointer;"
                        alt="previous"
                        title="Previous Step"
                        @click="previousTab()"
                      >
                      <input
                        type="submit"
                        name="Submit"
                        value="Create Project >> "
                        :disabled="cdash.submitdisabled"
                        @click="createProject()"
                      >
                    </div>
                    <div v-if="cdash.edit == 1">
                      <br>
                      <br>
                      <input
                        type="submit"
                        name="Delete"
                        value="Delete Project"
                        @click="deleteProject()"
                      >
                    </div>
                  </td>
                </tr>
              </table>
            </div>
          </div>

          <div
            v-if="cdash.edit == 1"
            style="width:900px;margin-left:auto;margin-right:auto;text-align:right;"
          >
            <br>
            <span
              v-show="cdash.changesmade"
              id="changesmade"
              style="color:red;"
            >*Changes need to be updated </span>
            <transition name="fade">
              <img
                v-show="projectupdated"
                id="project_updated"
                :src="$baseURL + '/img/check.gif'"
              >
            </transition>
            <input
              type="submit"
              name="Update"
              value="Update Project"
              @click="updateProject()"
            >
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
export default {
  name: "EditProject",
  props: {
    projectid: {
      type: Number,
      default: 0,
    },
  },
  data () {
    return {
      activeTab: 'Info',

      // Example URLs to create a new issue on a supported bug tracking system.
      bugtrackerexamples: {
        Buganizer: '<bug-url>/new?component=###&template=###',
        GitHub: 'https://github.com/:owner/:repo/issues/new?',
        JIRA: '<bug-url>/secure/CreateIssueDetails!init.jspa?pid=###&issuetype=#'
      },
      bugtrackertypes: [
        'Buganizer',
        'GitHub',
        'JIRA',
      ],

      projectupdated: false,

      issuecreation: false,
      newissuehelp: '',

      logoTooLarge: false,
      logoHeight: 0,
      previewLogo: null,
      uploadedLogo: null,

      buildblocked: false,
      spam: {
        buildname: '',
        ipaddress: '',
        sitename: '',
      },

      repositoryurlexample: '',

      selectedProject: 0,

      // API results.
      cdash: {},
      loading: true,
      errored: false,
    }
  },

  mounted () {
    var endpoint_path = '/api/v1/createProject.php';
    if (this.projectid > 0) {
      endpoint_path += '?projectid=' + this.projectid;
      this.selectedProject = this.projectid;
    }
    ApiLoader.loadPageData(this, endpoint_path);
  },

  methods: {
    postSetup: function (response) {
      this.cdash.changesmade = false;

      var disableTabs = false;

      if (this.cdash.edit == 0) {
        disableTabs = true;
        this.cdash.submitdisabled = true;
      }

      this.cdash.tabs = {
        'Info': {
          'disabled': false,
          'idx': 0,
        },
        'Logo': {
          'disabled': disableTabs,
          'idx': 1,
        },
        'Repos': {
          'disabled': disableTabs,
          'idx': 2,
        },
        'Testing': {
          'disabled': disableTabs,
          'idx': 3,
        },
        'Email': {
          'disabled': disableTabs,
          'idx': 4,
        },
      };
      if (this.cdash.edit == 1) {
        this.cdash.tabs.Spam = {
          'disabled': false,
          'idx': 5,
        };
        this.cdash.tabs.Clients = {
          'disabled': false,
          'idx': 6,
        };
        this.cdash.tabs.Misc = {
          'disabled': false,
          'idx': 7,
        };
      } else {
        this.cdash.tabs.Misc = {
          'disabled': true,
          'idx': 5,
        };
      }

      // Jump to tab specified by hash (if any).
      this.setTabByName(window.location.hash.replace("#", ""));

      if (this.cdash.project.BugTrackerType && this.cdash.project.BugTrackerNewIssueUrl) {
        this.issuecreation = true;
      } else {
        this.issuecreation = false;
      }
    },

    // Show/hide help text.
    showHelp: function(id_div) {
      $(".tab_help").html($("#"+id_div).html()).show();
    },
    clearHelp: function() {
      $('.tab_help').html('');
    },

    // Tab navigation functions.
    nextTab: function() {
      if(this.activeTab == 'Info' && (this.cdash.project.Name === undefined || this.cdash.project.Name == '')) {
        alert('please specify a name for the project.');
        return false;
      }

      var idx = this.cdash.tabs[this.activeTab].idx;
      this.gotoTab(idx + 1);
      if(idx == 4) {
        this.cdash.submitdisabled = false;
      }
    },

    previousTab: function() {
      var idx = this.cdash.tabs[this.activeTab].idx;
      this.gotoTab(idx - 1);
    },

    gotoTab: function(idx) {
      for (var tabName in this.cdash.tabs) {
        if (this.cdash.tabs[tabName].idx <= idx) {
          this.cdash.tabs[tabName].disabled = false;
        }
        if (this.cdash.tabs[tabName].idx === idx) {
          this.setTabByName(tabName);
        }
      }
    },

    setTabByName: function(tabName) {
      this.clearHelp();
      if (tabName in this.cdash.tabs) {
        this.activeTab = tabName;
      } else {
        this.activeTab = 'Info';
      }
      if (this.activeTab != tabName) {
        window.location.hash = "#" + this.activeTab;
      }
    },

    // Create new project.
    createProject: function() {
      var parameters = {
        Submit: true,
        project: this.cdash.project
      };

      this.$axios
        .post('/api/v1/project.php', parameters)
        .then(response => {
          var cdash = response.data;
          if (cdash.projectcreated && cdash.project) {
            this.cdash.projectcreated = cdash.projectcreated;
            this.cdash.project = cdash.project;
            this.setLogo();
          }
        })
        .catch(function (error) {
          this.errored = true;
          this.cdash.error = error;
        });
    },

    // Update existing project.
    updateProject: function() {
      var parameters = {
        Update: true,
        project: this.cdash.project
      };
      this.$axios
        .post('/api/v1/project.php', parameters)
        .then(response => {
          var cdash = response.data;
          if (cdash.projectupdated && cdash.project) {
            this.cdash.changesmade = false;
            this.projectupdated = true;
            this.cdash.project = cdash.project;
            this.setLogo();

            setTimeout(() => {
              this.projectupdated = false;
            }, 2000);
          }
        })
        .catch(function (error) {
          this.errored = true;
          this.cdash.error = error;
        });
    },

    setLogo: function() {
      if (this.uploadedLogo) {
        var data = new FormData();
        data.append('project', JSON.stringify(this.cdash.project));
        data.append('logo', this.uploadedLogo);
        var config = {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        };
        this.$axios
          .post('/api/v1/project.php', data, config)
          .then(response => {
            if (response.data.imageid > 0) {
              this.previewLogo = null;
              this.uploadedLogo = null;
              // Use a decache to force the logo to refresh even if the imageid didn't change.
              var imageid = response.data.imageid + "&decache=" + new Date().getTime();
              this.cdash.project.ImageId = imageid;
              this.cdash.logoid = imageid;
            }
          });
      }
    },

    // Delete project.
    deleteProject: function() {
      if (window.confirm("Are you sure you want to delete this project?")) {
        var parameters = { project: this.cdash.project };
        this.$axios
          .delete('/api/v1/project.php', { data: parameters})
          .then(response => {
            // Redirect to user.php
            window.location = this.$baseURL + '/user.php';
          });
      }
    },

    changeViewerType: function() {
      this.cdash.changesmade = true;
      if (!this.cdash.project.CvsUrl) {
        return;
      }
      var endpoint = this.$baseURL + '/api/v1/project.php?vcsexample=1&url=' + encodeURIComponent(this.cdash.project.CvsUrl) + '&type=' + this.cdash.project.CvsViewerType;
      this.$axios
        .get(endpoint)
        .then(response => {
          this.repositoryurlexample = response.data.example;
        });
    },

    addRepository: function() {
      // Add another repository form.
      this.cdash.project.repositories.push({
        url: '',
        branch: '',
        username: '',
        password: ''
      });
    },

    addBlockedBuild: function() {
      var parameters = {
        project: this.cdash.project,
        AddBlockedBuild: this.spam
      };
      this.$axios
        .post('/api/v1/project.php', parameters)
        .then(response => {
          var cdash = response.data;
          if (cdash.blockedid > 0) {
            this.spam.id = cdash.blockedid;
            this.cdash.project.blockedbuilds.push(this.spam);
            this.buildblocked = true;
            setTimeout(function () {
              this.buildblocked = false;
            }.bind(this), 2000);
          }
        })
        .catch(function (error) {
          this.errored = true;
          this.cdash.error = error;
        });
    },

    removeBlockedBuild: function(blockedbuild) {
      var parameters = {
        project: this.cdash.project,
        RemoveBlockedBuild: blockedbuild
      };
      this.$axios
        .post('/api/v1/project.php', parameters)
        .then(response => {
          var cdash = response.data;
          // Find and remove this build.
          var index = -1;
          for(var i = 0, len = this.cdash.project.blockedbuilds.length; i < len; i++) {
            if (this.cdash.project.blockedbuilds[i].id === blockedbuild.id) {
              index = i;
              break;
            }
          }
          if (index > -1) {
            this.cdash.project.blockedbuilds.splice(index, 1);
          }
        })
        .catch(function (error) {
          this.errored = true;
          this.cdash.error = error;
        });
    },

    changeTrackerType: function() {
      if (!this.cdash.project.BugTrackerType) {
        return;
      }
      if (!this.cdash.project.BugTrackerType in this.bugtrackerexamples) {
        this.newissuehelp = '';
      } else {
        this.newissuehelp = this.bugtrackerexamples[this.cdash.project.BugTrackerType];
      }
    },

    switchProject: function() {
      if (this.selectedProject != this.projectid) {
        window.location = this.$baseURL + '/project/' + this.selectedProject + '/edit#Info';
      }
    },

    uploadLogo: function(e) {
      this.logoTooLarge = false;
      this.logoHeight = 0;
      this.cdash.changesmade = true;

      this.uploadedLogo = e.target.files[0];
      const reader = new FileReader();
      reader.readAsDataURL(this.uploadedLogo);
      reader.onload = evt => {
        let img = new Image();
        img.src = evt.target.result;
        img.onload = () => {
          if (img.height > 100) {
            this.logoTooLarge = true;
            this.logoHeight = img.height;
          } else {
            this.previewLogo = evt.target.result;
          }
        }
      };
    }

  },
}
</script>
