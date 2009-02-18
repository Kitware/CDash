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
CREATE INDEX "build_projectid_idx" on "build" ("projectid");
CREATE INDEX "build_starttime_idx" on "build" ("starttime");
CREATE INDEX "build_submittime_idx" on "build" ("submittime");
CREATE INDEX "build_siteid_idx" on "build" ("siteid");
CREATE INDEX "build_stamp_idx" on "build" ("stamp");
CREATE INDEX "build_type_idx" on "build" ("type");
CREATE INDEX "build_name_idx" on "build" ("name");

--
-- Table: buildgroup
--
CREATE TABLE "buildgroup" (
  "id" serial NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "projectid" bigint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "description" text DEFAULT '' NOT NULL,
  "summaryemail" smallint DEFAULT '0',
  PRIMARY KEY ("id")
);
CREATE INDEX "buildgroup_projectid_idx" on "buildgroup" ("projectid");
CREATE INDEX "buildgroup_starttime_idx" on "buildgroup" ("starttime");
CREATE INDEX "buildgroup_endtime_idx" on "buildgroup" ("endtime");

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
CREATE INDEX "buildgroupposition_endtime_idx" on "buildgroupposition" ("endtime");
CREATE INDEX "buildgroupposition_starttime_idx" on "buildgroupposition" ("starttime");
CREATE INDEX "buildgroupposition_position_idx" on "buildgroupposition" ("position");

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
CREATE INDEX "build2grouprule_buildtype_idx" on "build2grouprule" ("buildtype");
CREATE INDEX "build2grouprule_buildname_idx" on "build2grouprule" ("buildname");
CREATE INDEX "build2grouprule_siteid_idx" on "build2grouprule" ("siteid");
CREATE INDEX "build2grouprule_expected_idx" on "build2grouprule" ("expected");
CREATE INDEX "build2grouprule_starttime_idx" on "build2grouprule" ("starttime");
CREATE INDEX "build2grouprule_endtime_idx" on "build2grouprule" ("endtime");

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
CREATE INDEX "builderror_type_idx" on "builderror" ("type");

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
CREATE INDEX "coverage_buildid_idx" on "coverage" ("buildid");
CREATE INDEX "coverage_fileid_idx" on "coverage" ("fileid");
CREATE INDEX "coverage_covered_idx" on "coverage" ("covered");

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
CREATE INDEX "coveragefile_fullpath_idx" on "coveragefile" ("fullpath");
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
CREATE INDEX "coveragefilelog_fileid_idx" on "coveragefilelog" ("fileid");
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
  "value" bigint DEFAULT '0' NOT NULL
);
CREATE INDEX "dynamicanalysisdefect_buildid_idx" on "dynamicanalysisdefect" ("dynamicanalysisid");

--
-- Table: image
--
CREATE TABLE "image" (
  "id" serial NOT NULL,
  "img" bytea NOT NULL,
  "extension" text NOT NULL,
  "checksum" bigint NOT NULL,
  CONSTRAINT "image_id_con" PRIMARY KEY ("id")
);
CREATE INDEX "image_checksum_idx" on "image" ("checksum");

--
-- Table: test2image
--
CREATE TABLE "test2image" (
  "imgid" serial NOT NULL,
  "testid" bigint DEFAULT '0' NOT NULL,
  "role" text NOT NULL,
  PRIMARY KEY ("imgid")
);
CREATE INDEX "test2image_testid_idx" on "test2image" ("testid");

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
  "testtimemaxstatus" smallint DEFAULT '3',
  "emailmaxitems" smallint DEFAULT '5',
  "emailmaxchars" bigint DEFAULT '255',
  PRIMARY KEY ("id")
);
CREATE INDEX "project_name_idx" on "project" ("name");
CREATE INDEX "project_public_idx" on "project" ("public");

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
CREATE INDEX "site_name_idx" on "site" ("name");

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
CREATE INDEX "site2user_siteid_idx" on "site2user" ("siteid");
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
CREATE INDEX "test_crc32_idx" on "test" ("crc32");
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
CREATE INDEX "build2test_buildid_idx" on "build2test" ("buildid");
CREATE INDEX "build2test_testid_idx" on "build2test" ("testid");
CREATE INDEX "build2test_status_idx" on "build2test" ("status");
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
CREATE INDEX "user_email_idx" on "user" ("email");

--
-- Table: user2project
--
CREATE TABLE "user2project" (
  "userid" bigint DEFAULT '0' NOT NULL,
  "projectid" bigint DEFAULT '0' NOT NULL,
  "role" bigint DEFAULT '0' NOT NULL,
  "cvslogin" character varying(50) DEFAULT '' NOT NULL,
  "emailtype" smallint DEFAULT '0' NOT NULL,
  "emailcategory" smallint DEFAULT '62' NOT NULL,
  PRIMARY KEY ("userid", "projectid")
);
CREATE INDEX "user2project_cvslogin_idx" on "user2project" ("cvslogin");
CREATE INDEX "user2project_emailtype_idx" on "user2project" ("emailtype");

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
  "status" smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "dailyupdate_date_idx" on "dailyupdate" ("date");
CREATE INDEX "dailyupdate_projectid_idx" on "dailyupdate" ("projectid");

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
CREATE INDEX "build2note_buildid_idx" on "build2note" ("buildid");
CREATE INDEX "build2note_noteid_idx" on "build2note" ("noteid");

--
-- Table: userstatistics
--
CREATE TABLE "userstatistics" (
  "userid" bigint NOT NULL,
  "projectid" smallint NOT NULL,
  "checkindate" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "totalupdatedfiles" bigint NOT NULL,
  "totalbuilds" bigint NOT NULL,
  "nfixedwarnings" bigint NOT NULL,
  "nfailedwarnings" bigint NOT NULL,
  "nfixederrors" bigint NOT NULL,
  "nfailederrors" bigint NOT NULL,
  "nfixedtests" bigint NOT NULL,
  "nfailedtests" bigint NOT NULL
);
CREATE INDEX "userstatistics_userid_idx" on "userstatistics" ("userid");
CREATE INDEX "userstatistics_projectid_idx" on "userstatistics" ("projectid");
CREATE INDEX "userstatistics_checkindate_idx" on "userstatistics" ("checkindate");

--
-- Table: version
--
CREATE TABLE "version" (
  "major" smallint NOT NULL,
  "minor" smallint NOT NULL,
  "patch" smallint NOT NULL
);

--
-- Table: summaryemail
--
CREATE TABLE "summaryemail" (
  "buildid" bigint NOT NULL,
  "date" date NOT NULL,
  "groupid" smallint NOT NULL
);
CREATE INDEX "summaryemail_date_idx" on "summaryemail" ("date");
CREATE INDEX "summaryemail_groupid_idx" on "summaryemail" ("groupid");

--
-- Table: configureerror
--
CREATE TABLE "configureerror" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "text" text NOT NULL
);
CREATE INDEX "configureerror_buildid_idx" on "configureerror" ("buildid");
CREATE INDEX "configureerror_type_idx" on "configureerror" ("type");

--
-- Table: configureerrordiff
--
CREATE TABLE "configureerrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "configureerrordiff_buildid_idx" on "configureerrordiff" ("buildid");
CREATE INDEX "configureerrordiff_type_idx" on "configureerrordiff" ("type");

--
-- Table: coveragesummarydiff
--
CREATE TABLE "coveragesummarydiff" (
  "buildid" bigint NOT NULL,
  "loctested" bigint DEFAULT '0' NOT NULL,
  "locuntested" bigint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("buildid")
);

--
-- Table: banner
--
CREATE TABLE "banner" (
  "projectid" bigint NOT NULL,
  "text" character varying(500) NOT NULL,
  PRIMARY KEY ("projectid")
);

--
-- Table: coveragefile2user
--
CREATE TABLE "coveragefile2user" (
  "fileid" bigint NOT NULL,
  "userid" bigint NOT NULL,
  "position" smallint NOT NULL
);
CREATE INDEX "coveragefile2user_coveragefileid_idx" on "coveragefile2user" ("fileid");
CREATE INDEX "coveragefile2user_userid_idx" on "coveragefile2user" ("userid");

--
-- Table: label
--
CREATE TABLE "label" (
  "id" bigserial NOT NULL,
  "text" character varying(255) NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "label_text_con" UNIQUE ("text")
);

--
-- Table: label2build
--
CREATE TABLE "label2build" (
  "labelid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildid")
);

--
-- Table: label2buildfailure
--
CREATE TABLE "label2buildfailure" (
  "labelid" bigint NOT NULL,
  "buildfailureid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildfailureid")
);

--
-- Table: label2coveragefile
--
CREATE TABLE "label2coveragefile" (
  "labelid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  "coveragefileid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildid", "coveragefileid")
);

--
-- Table: label2dynamicanalysis
--
CREATE TABLE "label2dynamicanalysis" (
  "labelid" bigint NOT NULL,
  "dynamicanalysisid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "dynamicanalysisid")
);

--
-- Table: label2test
--
CREATE TABLE "label2test" (
  "labelid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  "testid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildid", "testid")
);

--
-- Table: label2update
--
CREATE TABLE "label2update" (
  "labelid" bigint NOT NULL,
  "updateid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "updateid")
);

--
-- Table: subproject
--
CREATE TABLE "subproject" (
  "id" bigserial NOT NULL,
  "name" character varying(255) NOT NULL,
  "projectid" bigint NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "subproject_projectid_idx" on "subproject" ("projectid");

--
-- Table: subproject2subproject
--
CREATE TABLE "subproject2subproject" (
  "subprojectid" bigint NOT NULL,
  "dependsonid" bigint NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "subproject2subproject_subprojectid_idx" on "subproject2subproject" ("subprojectid");
CREATE INDEX "subproject2subproject_dependsonid_idx" on "subproject2subproject" ("dependsonid");

--
-- Table: subproject2build
--
CREATE TABLE "subproject2build" (
  "subprojectid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "subproject2build_subprojectid_idx" on "subproject2build" ("subprojectid");

--
-- Table: buildfailure
--
CREATE TABLE "buildfailure" (
  "id" bigserial NOT NULL,
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "workingdirectory" character varying(255) NOT NULL,
  "stdoutput" text NOT NULL,
  "stderror" text NOT NULL,
  "exitcondition" character varying(255) NOT NULL,
  "language" character varying(64) NOT NULL,
  "targetname" character varying(255) NOT NULL,
  "outputfile" character varying(255) NOT NULL,
  "outputtype" character varying(255) NOT NULL,
  "sourcefile" character varying(512) NOT NULL,
  PRIMARY KEY ("id")
);

--
-- Table: buildfailureargument
--
CREATE TABLE "buildfailureargument" (
  "id" bigserial NOT NULL,
  "buildfailureid" bigint NOT NULL,
  "argument" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "buildfailureargument_buildfailureid_idx" on "buildfailureargument" ("buildfailureid");


--
-- Language: plpgsql
--
CREATE LANGUAGE 'plpgsql';


--
-- Function: unix_timestamp
--
CREATE FUNCTION unix_timestamp(timestamp) RETURNS int AS $$ BEGIN RETURN floor(extract(EPOCH FROM $1)); END $$ LANGUAGE 'plpgsql';

--
-- Language: plpgsql
--
CREATE LANGUAGE 'plpgsql';


--
-- Function: unix_timestamp
--
CREATE FUNCTION unix_timestamp(timestamp) RETURNS int AS $$ BEGIN RETURN floor(extract(EPOCH FROM $1)); END $$ LANGUAGE 'plpgsql';
