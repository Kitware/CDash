--
-- Table: coveragefilepriority
--
CREATE TABLE "coveragefilepriority" (
  "id" serial NOT NULL,
  "priority" smallint NOT NULL,
  "fullpath" character varying(255) DEFAULT '' NOT NULL,
  "projectid" bigint  NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "coveragefilepriority_priority" on "coveragefilepriority" ("priority");
CREATE INDEX "coveragefilepriority_fullpath" on "coveragefilepriority" ("fullpath");
CREATE INDEX "coveragefilepriority_projectid" on "coveragefilepriority" ("projectid");

--
-- Table: submission
--
CREATE TABLE "submission" (
  "id" serial NOT NULL,
  "fullpath" character varying(255) DEFAULT '' NOT NULL,
  "projectid" bigint  NOT NULL,
  "status" smallint NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "submission_projectid" on "submission" ("projectid");
CREATE INDEX "submission_status" on "submission" ("status");

CREATE TABLE "buildtesttime" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "time" numeric(7,2) DEFAULT '0.00' NOT NULL,
  PRIMARY KEY ("buildid")
);

CREATE TABLE "blockbuild" (
  "id" serial NOT NULL,
  "projectid" bigint  NOT NULL,
  "buildname" character varying(255) DEFAULT '' NOT NULL,
  "sitename"  character varying(255) DEFAULT '' NOT NULL,
  "ipaddress" character varying(255) DEFAULT '' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "blockbuild_projectid" on "blockbuild" ("projectid");
CREATE INDEX "blockbuild_buildname" on "blockbuild" ("buildname");
CREATE INDEX "blockbuild_sitename" on "blockbuild" ("sitename");
CREATE INDEX "blockbuild_ipaddress" on "blockbuild" ("ipaddress");


--
-- Table: client_cmake
--
CREATE TABLE "client_cmake" (
  "id" serial NOT NULL,
  "version" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);


--
-- Table: client_compiler
--
CREATE TABLE "client_compiler" (
  "id" serial NOT NULL,
  "name" character varying(255) NOT NULL,
  "version" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);


--
-- Table: client_job
--
CREATE TABLE "client_job" (
  "id" bigserial NOT NULL,
  "scheduleid" bigint NOT NULL,
  "osid" smallint NOT NULL,
  "siteid" bigint DEFAULT NULL,
  "startdate" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "enddate" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "status" bigint DEFAULT NULL,
  "output" text,
  "cmakeid" bigint NOT NULL,
  "compilerid" bigint NOT NULL
);
CREATE INDEX "client_job_scheduleid" on "client_job" ("scheduleid");
CREATE INDEX "client_job_startdate" on "client_job" ("startdate");
CREATE INDEX "client_job_enddate" on "client_job" ("enddate");
CREATE INDEX "client_job_status" on "client_job" ("status");


--
-- Table: client_jobschedule
--
CREATE TABLE "client_jobschedule" (
  "id" bigserial NOT NULL,
  "userid" bigint DEFAULT NULL,
  "projectid" bigint DEFAULT NULL,
  "cmakecache" text NOT NULL,
  "startdate" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "enddate" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "type" smallint NOT NULL,
  "starttime" time DEFAULT '00:00:00' NOT NULL,
  "repeattime" numeric(3,2) DEFAULT '0.00' NOT NULL,
  "enable" smallint NOT NULL,
  "lastrun" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "repository" character varying(512) default '',
  "module" character varying(255) default '',
  "buildnamesuffix" character varying(255) default '',
  "tag" character varying(255) default ''
);
CREATE INDEX "client_jobschedule_userid" on "client_jobschedule" ("userid");
CREATE INDEX "client_jobschedule_projectid" on "client_jobschedule" ("projectid");
CREATE INDEX "client_jobschedule_enable" on "client_jobschedule" ("enable");
CREATE INDEX "client_jobschedule_starttime" on "client_jobschedule" ("starttime");
CREATE INDEX "client_jobschedule_repeattime" on "client_jobschedule" ("repeattime");


--
-- Table: client_jobschedule2cmake
--
CREATE TABLE "client_jobschedule2cmake" (
  "scheduleid" bigint NOT NULL,
  "cmakeid" bigint NOT NULL,
  Constraint "scheduleid2" UNIQUE ("scheduleid", "cmakeid")
);

--
-- Table: client_jobschedule2compiler
--
CREATE TABLE "client_jobschedule2compiler" (
  "scheduleid" bigint NOT NULL,
  "compilerid" bigint NOT NULL,
  Constraint "scheduleid3" UNIQUE ("scheduleid", "compilerid")
);

--
-- Table: client_jobschedule2library
--
CREATE TABLE "client_jobschedule2library" (
  "scheduleid" bigint NOT NULL,
  "libraryid" bigint NOT NULL,
  Constraint "scheduleid4" UNIQUE ("scheduleid", "libraryid")
);


--
-- Table: client_jobschedule2os
--
CREATE TABLE "client_jobschedule2os" (
  "scheduleid" bigint NOT NULL,
  "osid" bigint NOT NULL,
  Constraint "scheduleid5" UNIQUE ("scheduleid", "osid")
);


--
-- Table: client_jobschedule2site
--
CREATE TABLE "client_jobschedule2site" (
  "scheduleid" bigint NOT NULL,
  "siteid" bigint NOT NULL,
  Constraint "scheduleid6" UNIQUE ("scheduleid", "siteid")
);


--
-- Table: client_jobschedule2toolkit
--
CREATE TABLE "client_jobschedule2toolkit" (
  "scheduleid" bigint NOT NULL,
  "toolkitconfigurationid" bigint NOT NULL,
  Constraint "scheduleid7" UNIQUE ("scheduleid", "toolkitconfigurationid")
);


--
-- Table: client_library
--
CREATE TABLE "client_library" (
  "id" serial NOT NULL,
  "name" character varying(255) NOT NULL,
  "version" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);


--
-- Table: client_os
--
CREATE TABLE "client_os" (
  "id" serial NOT NULL,
  "name" character varying(255) NOT NULL,
  "version" character varying(255) NOT NULL,
  "bits" smallint DEFAULT '32' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "client_os_name" on "client_os" ("name");
CREATE INDEX "client_os_version" on "client_os" ("version");
CREATE INDEX "client_os_bits" on "client_os" ("bits");


--
-- Table: client_site
--
CREATE TABLE "client_site" (
  "id" serial NOT NULL,
  "name" character varying(255) DEFAULT NULL,
  "osid" bigint DEFAULT NULL,
  "systemname" character varying(255) DEFAULT NULL,
  "host" character varying(255) DEFAULT NULL,
  "basedirectory" character varying(512) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "client_site_name" on "client_site" ("name");
CREATE INDEX "client_site_system" on "client_site" ("osid");


--
-- Table: client_site2cmake
--
CREATE TABLE "client_site2cmake" (
  "siteid" bigint DEFAULT NULL,
  "cmakeid" bigint DEFAULT NULL,
  "path" character varying(512) DEFAULT NULL
);
CREATE INDEX "client_site2cmake_siteid" on "client_site2cmake" ("siteid");
CREATE INDEX "client_site2cmake_version" on "client_site2cmake" ("cmakeid");


--
-- Table: client_site2compiler
--
CREATE TABLE "client_site2compiler" (
  "siteid" bigint DEFAULT NULL,
  "compilerid" bigint DEFAULT NULL,
  "command" character varying(512) DEFAULT NULL,
  "generator" character varying(255) NOT NULL
);
CREATE INDEX "client_site2compiler_siteid" on "client_site2compiler" ("siteid");


--
-- Table: client_site2library
--
CREATE TABLE "client_site2library" (
  "siteid" bigint DEFAULT NULL,
  "libraryid" bigint DEFAULT NULL,
  "path" character varying(512) DEFAULT NULL,
  "include" character varying(512) NOT NULL
);
CREATE INDEX "client_site2library_siteid" on "client_site2library" ("siteid");

--
-- Table: client_toolkit
--
CREATE TABLE "client_toolkit" (
  "id" serial NOT NULL,
  "name" character varying(255) NOT NULL,
  "projectid" bigint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "client_toolkit_projectid" on "client_toolkit" ("projectid");


--
-- Table: client_toolkitconfiguration
--
CREATE TABLE "client_toolkitconfiguration" (
  "id" serial NOT NULL,
  "toolkitversionid" bigint NOT NULL,
  "name" character varying(255) NOT NULL,
  "cmakecache" text,
  "environment" text,
  "binarypath" character varying(512) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "client_toolkitconfiguration_name" on "client_toolkitconfiguration" ("name");
CREATE INDEX "client_toolkitconfiguration_binarypath" on "client_toolkitconfiguration" ("binarypath");


--
-- Table: client_toolkitconfiguration2os
--
CREATE TABLE "client_toolkitconfiguration2os" (
  "toolkitconfigurationid" bigint NOT NULL,
  "osid" bigint NOT NULL
);
CREATE INDEX "client_toolkitconfiguration2os_toolkitconfigurationid" on "client_toolkitconfiguration2os" ("toolkitconfigurationid");
CREATE INDEX "client_toolkitconfiguration2os_osid" on "client_toolkitconfiguration2os" ("osid");


--
-- Table: client_toolkitversion
--
CREATE TABLE "client_toolkitversion" (
  "id" serial NOT NULL,
  "toolkitid" bigint NOT NULL,
  "name" character varying(10) NOT NULL,
  "repositoryurl" character varying(255) NOT NULL,
  "repositorytype" smallint NOT NULL,
  "repositorymodule" character varying(100) NOT NULL,
  "tag" character varying(30) DEFAULT NULL,
  "sourcepath" character varying(512) NOT NULL,
  "ctestprojectname" character varying(50) DEFAULT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "client_toolkitversion_toolkitid" on "client_toolkitversion" ("toolkitid");
CREATE INDEX "client_toolkitversion_version" on "client_toolkitversion" ("name");


--
-- Table structure for table projectrobot
--
CREATE TABLE "projectrobot" (
  "projectid" bigint NOT NULL,
  "robotname" character varying(255) NOT NULL,
  "authorregex" character varying(512) NOT NULL
);
CREATE INDEX "projectrobot_projectid" on "projectrobot" ("projectid");
CREATE INDEX "projectrobot_robotname" on "projectrobot" ("robotname");

--
-- Table structure for table `filesum`
--

CREATE TABLE "filesum" (
  "id" serial NOT NULL,
  "md5sum" character varying(32) NOT NULL,
  "contents" bytea,
  PRIMARY KEY ("id")
);
CREATE INDEX "filesum_md5sum" on "filesum" ("md5sum");
