--
-- Table: build
--
CREATE TABLE "build" (
  "id" serial NOT NULL,
  "siteid" bigint DEFAULT '0' NOT NULL,
  "projectid" bigint DEFAULT '0' NOT NULL,
  "stamp" character varying(255) DEFAULT '' NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "type" character varying(255) DEFAULT '' NOT NULL,
  "generator" character varying(255) DEFAULT '' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "submittime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "command" text NOT NULL,
  "log" text NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "build_siteid_idx" on "build" ("siteid", "name");;
CREATE INDEX "build_projectid_idx" on "build" ("projectid");


--
-- Table: buildgroup
--
CREATE TABLE "buildgroup" (
  "id" serial NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "projectid" bigint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "description" text NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "buildgroup_projectid_idx" on "buildgroup" ("projectid");


--
-- Table: buildgroupposition
--
CREATE TABLE "buildgroupposition" (
  "buildgroupid" bigint DEFAULT '0' NOT NULL,
  "position" bigint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "buildgroupposition_buildgroupid_idx" on "buildgroupposition" ("buildgroupid");


--
-- Table: build2group
--
CREATE TABLE "build2group" (
  "groupid" bigint DEFAULT '0' NOT NULL,
  "buildid" bigint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "build2group_groupid_idx" on "build2group" ("groupid");


--
-- Table: build2grouprule
--
CREATE TABLE "build2grouprule" (
  "groupid" bigint DEFAULT '0' NOT NULL,
  "buildtype" character varying(20) DEFAULT '' NOT NULL,
  "buildname" character varying(255) DEFAULT '' NOT NULL,
  "siteid" bigint DEFAULT '0' NOT NULL,
  "expected" smallint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "build2grouprule_groupid_idx" on "build2grouprule" ("groupid");


--
-- Table: builderror
--
CREATE TABLE "builderror" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "type" smallint DEFAULT '0' NOT NULL,
  "logline" bigint DEFAULT '0' NOT NULL,
  "text" text NOT NULL,
  "sourcefile" character varying(255) DEFAULT '' NOT NULL,
  "sourceline" bigint DEFAULT '0' NOT NULL,
  "precontext" text NOT NULL,
  "postcontext" text NOT NULL,
  "repeatcount" bigint DEFAULT '0' NOT NULL
);
CREATE INDEX "builderror_buildid_idx" on "builderror" ("buildid");


--
-- Table: buildupdate
--
CREATE TABLE "buildupdate" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "command" text NOT NULL,
  "type" character varying(4) DEFAULT '' NOT NULL,
  "status" text NOT NULL
);
CREATE INDEX "buildupdate_buildid_idx" on "buildupdate" ("buildid");


--
-- Table: configure
--
CREATE TABLE "configure" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "command" text NOT NULL,
  "log" text NOT NULL,
  "status" smallint DEFAULT '0' NOT NULL
);
CREATE INDEX "configure_buildid_idx" on "configure" ("buildid");


--
-- Table: coverage
--
CREATE TABLE "coverage" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "fileid" bigint DEFAULT '0' NOT NULL,
  "covered" smallint DEFAULT '0' NOT NULL,
  "loctested" bigint DEFAULT '0' NOT NULL,
  "locuntested" bigint DEFAULT '0' NOT NULL,
  "branchstested" bigint DEFAULT '0' NOT NULL,
  "branchsuntested" bigint DEFAULT '0' NOT NULL,
  "functionstested" bigint DEFAULT '0' NOT NULL,
  "functionsuntested" bigint DEFAULT '0' NOT NULL
);
CREATE INDEX "coverage_buildid_idx" on "coverage" ("buildid");;
CREATE INDEX "coverage_fileid_idx" on "coverage" ("fileid");


--
-- Table: coveragefile
--
CREATE TABLE "coveragefile" (
  "id" serial NOT NULL,
  "fullpath" character varying(255) DEFAULT '' NOT NULL,
  "file" bytea,
  "crc32" bigint DEFAULT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "coveragefile_fullpath_idx" on "coveragefile" ("fullpath");;
CREATE INDEX "coveragefile_crc32_idx" on "coveragefile" ("crc32");


--
-- Table: coveragefilelog
--
CREATE TABLE "coveragefilelog" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "fileid" bigint DEFAULT '0' NOT NULL,
  "line" bigint DEFAULT '0' NOT NULL,
  "code" character varying(10) DEFAULT '' NOT NULL
);
CREATE INDEX "coveragefilelog_fileid_idx" on "coveragefilelog" ("fileid");;
CREATE INDEX "coveragefilelog_buildid_idx" on "coveragefilelog" ("buildid");


--
-- Table: coveragesummary
--
CREATE TABLE "coveragesummary" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "loctested" bigint DEFAULT '0' NOT NULL,
  "locuntested" bigint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("buildid")
);



--
-- Table: dynamicanalysis
--
CREATE TABLE "dynamicanalysis" (
  "id" serial NOT NULL,
  "buildid" bigint DEFAULT '0' NOT NULL,
  "status" character varying(10) DEFAULT '' NOT NULL,
  "checker" character varying(60) DEFAULT '' NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "path" character varying(255) DEFAULT '' NOT NULL,
  "fullcommandline" character varying(255) DEFAULT '' NOT NULL,
  "log" text NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "dynamicanalysis_buildid_idx" on "dynamicanalysis" ("buildid");


--
-- Table: dynamicanalysisdefect
--
CREATE TABLE "dynamicanalysisdefect" (
  "dynamicanalysisid" bigint DEFAULT '0' NOT NULL,
  "type" character varying(50) DEFAULT '' NOT NULL,
  "value" character varying(50) DEFAULT '' NOT NULL
);
CREATE INDEX "dynamicanalysisdefect_buildid_idx" on "dynamicanalysisdefect" ("dynamicanalysisid");


--
-- Table: image
--
CREATE TABLE "image" (
  "id" serial NOT NULL,
  "img" bytea NOT NULL,
  "extension" text NOT NULL,
  "checksum" bigint NOT NULL
);
CREATE INDEX "image_id_idx" on "image" ("id");;
CREATE INDEX "image_checksum_idx" on "image" ("checksum");


--
-- Table: test2image
--
CREATE TABLE "test2image" (
  "imgid" bigint DEFAULT '0' NOT NULL,
  "testid" bigint DEFAULT '0' NOT NULL,
  "role" text NOT NULL,
  PRIMARY KEY ("imgid", "testid")
);



--
-- Table: note
--
CREATE TABLE "note" (
  "id" bigserial NOT NULL,
  "text" text NOT NULL,
  "name" character varying(255) NOT NULL,
  "crc32" bigint NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "note_crc32_idx" on "note" ("crc32");


--
-- Table: project
--
CREATE TABLE "project" (
  "id" serial NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "description" text NOT NULL,
  "homeurl" character varying(255) DEFAULT '' NOT NULL,
  "cvsurl" character varying(255) DEFAULT '' NOT NULL,
  "bugtrackerurl" character varying(255) DEFAULT '' NOT NULL,
  "documentationurl" character varying(255) DEFAULT '' NOT NULL,
  "imageid" bigint DEFAULT '0' NOT NULL,
  "public" smallint DEFAULT '1' NOT NULL,
  "coveragethreshold" smallint DEFAULT '70' NOT NULL,
  "nightlytime" character varying(50) DEFAULT '00:00:00' NOT NULL,
  "googletracker" character varying(50) DEFAULT '' NOT NULL,
  "emailbuildmissing" smallint DEFAULT '0' NOT NULL,
  "emaillowcoverage" smallint DEFAULT '0' NOT NULL,
  "emailtesttimingchanged" smallint DEFAULT '0' NOT NULL,
  "emailbrokensubmission" smallint DEFAULT '1' NOT NULL,
  "cvsviewertype" character varying(10) DEFAULT NULL,
  "testtimestd" numeric(3,1) DEFAULT '4.0',
  "testtimestdthreshold" numeric(3,1) DEFAULT '1.0',
  "showtesttime" smallint DEFAULT '0',
  PRIMARY KEY ("id")
);



--
-- Table: site
--
CREATE TABLE "site" (
  "id" serial NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "ip" character varying(255) DEFAULT '' NOT NULL,
  "latitude" character varying(10) DEFAULT '' NOT NULL,
  "longitude" character varying(10) DEFAULT '' NOT NULL,
  PRIMARY KEY ("id")
);



--
-- Table: siteinformation
--
CREATE TABLE "siteinformation" (
  "siteid" bigint NOT NULL,
  "timestamp" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "processoris64bits" smallint DEFAULT '-1' NOT NULL,
  "processorvendor" character varying(255) DEFAULT 'NA' NOT NULL,
  "processorvendorid" character varying(255) DEFAULT 'NA' NOT NULL,
  "processorfamilyid" bigint DEFAULT '-1' NOT NULL,
  "processormodelid" bigint DEFAULT '-1' NOT NULL,
  "processorcachesize" bigint DEFAULT '-1' NOT NULL,
  "numberlogicalcpus" smallint DEFAULT '-1' NOT NULL,
  "numberphysicalcpus" smallint DEFAULT '-1' NOT NULL,
  "totalvirtualmemory" bigint DEFAULT '-1' NOT NULL,
  "totalphysicalmemory" bigint DEFAULT '-1' NOT NULL,
  "logicalprocessorsperphysical" bigint DEFAULT '-1' NOT NULL,
  "processorclockfrequency" bigint DEFAULT '-1' NOT NULL,
  "description" character varying(255) DEFAULT 'NA' NOT NULL
);
CREATE INDEX "siteinformation_siteid_idx" on "siteinformation" ("siteid", "timestamp");


--
-- Table: buildinformation
--
CREATE TABLE "buildinformation" (
  "buildid" bigint NOT NULL,
  "osname" character varying(255) NOT NULL,
  "osplatform" character varying(255) NOT NULL,
  "osrelease" character varying(255) NOT NULL,
  "osversion" character varying(255) NOT NULL,
  "compilername" character varying(255) NOT NULL,
  "compilerversion" character varying(20) NOT NULL,
  PRIMARY KEY ("buildid")
);



--
-- Table: site2user
--
CREATE TABLE "site2user" (
  "siteid" bigint DEFAULT '0' NOT NULL,
  "userid" bigint DEFAULT '0' NOT NULL
);
CREATE INDEX "site2user_siteid_idx" on "site2user" ("siteid");;
CREATE INDEX "site2user_userid_idx" on "site2user" ("userid");


--
-- Table: test
--
CREATE TABLE "test" (
  "id" serial NOT NULL,
  "crc32" bigint NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "path" character varying(255) DEFAULT '' NOT NULL,
  "command" text NOT NULL,
  "details" text NOT NULL,
  "output" text NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "test_crc32_idx" on "test" ("crc32");;
CREATE INDEX "test_name_idx" on "test" ("name");


--
-- Table: build2test
--
CREATE TABLE "build2test" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "testid" bigint DEFAULT '0' NOT NULL,
  "status" character varying(10) DEFAULT '' NOT NULL,
  "time" numeric(7,2) DEFAULT '0.00' NOT NULL,
  "timemean" numeric(7,2) DEFAULT '0.00' NOT NULL,
  "timestd" numeric(7,2) DEFAULT '0.00' NOT NULL,
  "timestatus" smallint DEFAULT '0' NOT NULL
);
CREATE INDEX "build2test_buildid_idx" on "build2test" ("buildid");;
CREATE INDEX "build2test_testid_idx" on "build2test" ("testid");;
CREATE INDEX "build2test_status_idx" on "build2test" ("status");;
CREATE INDEX "build2test_timestatus_idx" on "build2test" ("timestatus");


--
-- Table: updatefile
--
CREATE TABLE "updatefile" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "filename" character varying(255) DEFAULT '' NOT NULL,
  "checkindate" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "author" character varying(255) DEFAULT '' NOT NULL,
  "email" character varying(255) DEFAULT '' NOT NULL,
  "log" text NOT NULL,
  "revision" character varying(20) DEFAULT '0' NOT NULL,
  "priorrevision" character varying(20) DEFAULT '0' NOT NULL
);
CREATE INDEX "updatefile_buildid_idx" on "updatefile" ("buildid");


--
-- Table: user
--
CREATE TABLE "user" (
  "id" serial NOT NULL,
  "email" character varying(255) DEFAULT '' NOT NULL,
  "password" character varying(40) DEFAULT '' NOT NULL,
  "firstname" character varying(40) DEFAULT '' NOT NULL,
  "lastname" character varying(40) DEFAULT '' NOT NULL,
  "institution" character varying(255) DEFAULT '' NOT NULL,
  "admin" smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);



--
-- Table: user2project
--
CREATE TABLE "user2project" (
  "userid" bigint DEFAULT '0' NOT NULL,
  "projectid" bigint DEFAULT '0' NOT NULL,
  "role" bigint DEFAULT '0' NOT NULL,
  "cvslogin" character varying(50) DEFAULT '' NOT NULL,
  "emailtype" smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("userid", "projectid")
);



--
-- Table: buildnote
--
CREATE TABLE "buildnote" (
  "buildid" bigint NOT NULL,
  "userid" bigint NOT NULL,
  "note" text NOT NULL,
  "timestamp" timestamp(0) NOT NULL,
  "status" smallint DEFAULT '0' NOT NULL
);
CREATE INDEX "buildnote_buildid_idx" on "buildnote" ("buildid");


--
-- Table: repositories
--
CREATE TABLE "repositories" (
  "id" serial NOT NULL,
  "url" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);



--
-- Table: project2repositories
--
CREATE TABLE "project2repositories" (
  "projectid" bigint NOT NULL,
  "repositoryid" bigint NOT NULL,
  PRIMARY KEY ("projectid", "repositoryid")
);



--
-- Table: testmeasurement
--
CREATE TABLE "testmeasurement" (
  "testid" bigint NOT NULL,
  "name" character varying(70) NOT NULL,
  "type" character varying(70) NOT NULL,
  "value" text NOT NULL
);
CREATE INDEX "testmeasurement_testid_idx" on "testmeasurement" ("testid");


--
-- Table: dailyupdate
--
CREATE TABLE "dailyupdate" (
  "id" serial NOT NULL,
  "projectid" bigint NOT NULL,
  "date" date NOT NULL,
  "command" text NOT NULL,
  "type" character varying(4) DEFAULT '' NOT NULL,
  "status" smallint DEFAULT '0' NOT NULL
);
CREATE INDEX "dailyupdate_buildid_idx" on "dailyupdate" ("id");


--
-- Table: dailyupdatefile
--
CREATE TABLE "dailyupdatefile" (
  "dailyupdateid" bigint DEFAULT '0' NOT NULL,
  "filename" character varying(255) DEFAULT '' NOT NULL,
  "checkindate" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "author" character varying(255) DEFAULT '' NOT NULL,
  "log" text NOT NULL,
  "revision" character varying(10) DEFAULT '0' NOT NULL,
  "priorrevision" character varying(10) DEFAULT '0' NOT NULL
);
CREATE INDEX "dailyupdatefile_buildid_idx" on "dailyupdatefile" ("dailyupdateid");


--
-- Table: builderrordiff
--
CREATE TABLE "builderrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "builderrordiff_buildid_idx" on "builderrordiff" ("buildid");


--
-- Table: testdiff
--
CREATE TABLE "testdiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "testdiff_buildid_idx" on "testdiff" ("buildid", "type");


--
-- Table: build2note
--
CREATE TABLE "build2note" (
  "buildid" bigint NOT NULL,
  "noteid" bigint NOT NULL,
  "time" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL
);
CREATE INDEX "build2note_buildid_idx" on "build2note" ("buildid", "noteid");

CREATE TABLE "version" (
  "major" smallint DEFAULT '0' NOT NULL,
  "minor" smallint DEFAULT '0' NOT NULL,
  "patch" smallint DEFAULT '0' NOT NULL
);
