--
-- Table: build
--
CREATE TABLE "build" (
  "id" serial NOT NULL,
  "siteid" bigint DEFAULT '0' NOT NULL,
  "projectid" bigint DEFAULT '0' NOT NULL,
  "parentid" bigint DEFAULT '0' NOT NULL,
  "stamp" character varying(255) DEFAULT '' NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "type" character varying(255) DEFAULT '' NOT NULL,
  "generator" character varying(255) DEFAULT '' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "submittime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "command" text DEFAULT '' NOT NULL,
  "log" text DEFAULT '' NOT NULL,
  "configureerrors" smallint DEFAULT '-1',
  "configurewarnings" smallint DEFAULT '-1',
  "configureduration" integer DEFAULT '0' NOT NULL,
  "builderrors" smallint DEFAULT '-1',
  "buildwarnings" smallint DEFAULT '-1',
  "buildduration" integer DEFAULT '0' NOT NULL,
  "testnotrun" smallint DEFAULT '-1',
  "testfailed" smallint DEFAULT '-1',
  "testpassed" smallint DEFAULT '-1',
  "testtimestatusfailed" smallint DEFAULT '-1',
  "testduration" integer DEFAULT '0' NOT NULL,
  "notified" smallint DEFAULT '0' NOT NULL,
  "done" smallint DEFAULT '0' NOT NULL,
  "uuid" character varying(36) NOT NULL,
  "changeid" character varying(40) DEFAULT '',
  PRIMARY KEY ("id"),
  CONSTRAINT "uuid" UNIQUE ("uuid")
);
CREATE INDEX "projectid" on "build" ("projectid");
CREATE INDEX "starttime" on "build" ("starttime");
CREATE INDEX "submittime" on "build" ("submittime");
CREATE INDEX "siteid" on "build" ("siteid");
CREATE INDEX "stamp" on "build" ("stamp");
CREATE INDEX "type" on "build" ("type");
CREATE INDEX "name" on "build" ("name");
CREATE INDEX "parentid" on "build" ("parentid");
CREATE INDEX "projectid_parentid_starttime" ON "build" (projectid,parentid,starttime);

--
-- Table: buildgroup
--
CREATE TABLE "buildgroup" (
  "id" serial NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "projectid" bigint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "autoremovetimeframe" bigint DEFAULT '0',
  "description" text DEFAULT '' NOT NULL,
  "summaryemail" smallint DEFAULT '0',
  "includesubprojectotal" smallint DEFAULT '1',
  "emailcommitters" smallint DEFAULT '0',
  "type" character varying(20) DEFAULT 'Daily' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "projectid2" on "buildgroup" ("projectid");
CREATE INDEX "starttime2" on "buildgroup" ("starttime");
CREATE INDEX "endtime" on "buildgroup" ("endtime");
CREATE INDEX "buildgrouptype" on "buildgroup" ("type");

--
-- Table: buildgroupposition
--
CREATE TABLE "buildgroupposition" (
  "buildgroupid" bigint DEFAULT '0' NOT NULL,
  "position" bigint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "buildgroupid" on "buildgroupposition" ("buildgroupid");
CREATE INDEX "endtime2" on "buildgroupposition" ("endtime");
CREATE INDEX "starttime3" on "buildgroupposition" ("starttime");
CREATE INDEX "position" on "buildgroupposition" ("position");

-- Table: build2configure
--
CREATE TABLE "build2configure" (
  "configureid" integer DEFAULT '0' NOT NULL,
  "buildid" integer DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "configureid" on "build2configure" ("configureid");

--
-- Table: build2group
--
CREATE TABLE "build2group" (
  "groupid" bigint DEFAULT '0' NOT NULL,
  "buildid" bigint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "groupid" on "build2group" ("groupid");

--
-- Table: build2grouprule
--
CREATE TABLE "build2grouprule" (
  "groupid" bigint DEFAULT '0' NOT NULL,
  "parentgroupid" bigint DEFAULT '0' NOT NULL,
  "buildtype" character varying(20) DEFAULT '' NOT NULL,
  "buildname" character varying(255) DEFAULT '' NOT NULL,
  "siteid" bigint DEFAULT '0' NOT NULL,
  "expected" smallint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "groupid2" on "build2grouprule" ("groupid");
CREATE INDEX "parentgroupid" on "build2grouprule" ("parentgroupid");
CREATE INDEX "buildtype" on "build2grouprule" ("buildtype");
CREATE INDEX "buildname" on "build2grouprule" ("buildname");
CREATE INDEX "siteid2" on "build2grouprule" ("siteid");
CREATE INDEX "expected" on "build2grouprule" ("expected");
CREATE INDEX "starttime4" on "build2grouprule" ("starttime");
CREATE INDEX "endtime3" on "build2grouprule" ("endtime");

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
  "precontext" text,
  "postcontext" text,
  "repeatcount" bigint DEFAULT '0' NOT NULL,
  "crc32" bigint DEFAULT '0' NOT NULL,
  "newstatus" smallint DEFAULT '0' NOT NULL
);
CREATE INDEX "buildid" on "builderror" ("buildid");
CREATE INDEX "type2" on "builderror" ("type");
CREATE INDEX "builderror_newstatus" on "builderror" ("newstatus");
CREATE INDEX "builderror_crc32" on "builderror" ("crc32");


--
-- Table: buildupdate
--
CREATE TABLE "buildupdate" (
  "id" SERIAL NOT NULL,
  "starttime" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "command" text NOT NULL,
  "type" character varying(4) DEFAULT '' NOT NULL,
  "status" text NOT NULL,
  "nfiles" smallint DEFAULT '-1',
  "warnings" smallint DEFAULT '-1',
  "revision" character varying(60) DEFAULT '0' NOT NULL,
  "priorrevision" character varying(60) DEFAULT '0' NOT NULL,
  "path" character varying(255) DEFAULT '' NOT NULL,
   PRIMARY KEY ("id")
);
CREATE INDEX "revision" on "buildupdate" ("revision");


CREATE TABLE "build2update" (
  "buildid" bigint NOT NULL,
  "updateid" bigint NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "build2update_updateid" on "build2update" ("updateid");

--
-- Table: configure
--
CREATE TABLE "configure" (
  "id" SERIAL NOT NULL,
  "command" text NOT NULL,
  "log" text NOT NULL,
  "status" smallint DEFAULT '0' NOT NULL,
  "warnings" smallint DEFAULT '-1',
  "crc32" bigint DEFAULT NULL,
   PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX "configure_crc32" on "configure" ("crc32");

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
CREATE INDEX "buildid4" on "coverage" ("buildid");
CREATE INDEX "fileid" on "coverage" ("fileid");
CREATE INDEX "covered" on "coverage" ("covered");

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
CREATE INDEX "fullpath" on "coveragefile" ("fullpath");
CREATE INDEX "crc32" on "coveragefile" ("crc32");

--
-- Table: coveragefilelog
--
CREATE TABLE "coveragefilelog" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "fileid" bigint DEFAULT '0' NOT NULL,
  "log" bytea NOT NULL
);
CREATE INDEX "fileid2" on "coveragefilelog" ("fileid");
CREATE INDEX "buildid5" on "coveragefilelog" ("buildid");

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
CREATE INDEX "buildid6" on "dynamicanalysis" ("buildid");

--
-- Table: dynamicanalysisdefect
--
CREATE TABLE "dynamicanalysisdefect" (
  "dynamicanalysisid" bigint DEFAULT '0' NOT NULL,
  "type" character varying(255) DEFAULT '' NOT NULL,
  "value" bigint DEFAULT '0' NOT NULL
);
CREATE INDEX "buildid7" on "dynamicanalysisdefect" ("dynamicanalysisid");

--
-- Table: dynamicanalysissummary
--
CREATE TABLE "dynamicanalysissummary" (
  "buildid" integer DEFAULT '0' NOT NULL,
  "checker" character varying(60) DEFAULT '' NOT NULL,
  "numdefects" integer DEFAULT '0' NOT NULL,
  PRIMARY KEY ("buildid")
);

--
-- Table: image
--
CREATE TABLE "image" (
  "id" serial NOT NULL,
  "img" bytea NOT NULL,
  "extension" text NOT NULL,
  "checksum" bigint NOT NULL,
  CONSTRAINT "id" PRIMARY KEY ("id")
);
CREATE INDEX "checksum" on "image" ("checksum");

--
-- Table: test2image
--
CREATE TABLE "test2image" (
  "id" serial NOT NULL,
  "imgid" bigint NOT NULL,
  "outputid" bigint NOT NULL,
  "role" text NOT NULL,
   PRIMARY KEY ("id")
);
CREATE INDEX "imgid" on "test2image" ("imgid");
CREATE INDEX "outputid" on "test2image" ("outputid");

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
CREATE INDEX "crc322" on "note" ("crc32");

--
-- Table: pending_submissions
--
CREATE TABLE "pending_submissions" (
  "buildid" integer NOT NULL,
  "numfiles" smallint DEFAULT '0' NOT NULL,
  "recheck" smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("buildid")
);

--
-- Table: project
--
CREATE TABLE "project" (
  "id" serial NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "description" text DEFAULT '' NOT NULL,
  "homeurl" character varying(255) DEFAULT '' NOT NULL,
  "cvsurl" character varying(255) DEFAULT '' NOT NULL,
  "bugtrackerurl" character varying(255) DEFAULT '' NOT NULL,
  "bugtrackerfileurl" character varying(255) DEFAULT '' NOT NULL,
  "bugtrackernewissueurl" character varying(255) DEFAULT '' NOT NULL,
  "bugtrackertype" character varying(16) DEFAULT NULL,
  "documentationurl" character varying(255) DEFAULT '' NOT NULL,
  "imageid" bigint DEFAULT '0' NOT NULL,
  "public" smallint DEFAULT '1' NOT NULL,
  "coveragethreshold" smallint DEFAULT '70' NOT NULL,
  "testingdataurl" character varying(255) DEFAULT '' NOT NULL,
  "nightlytime" character varying(50) DEFAULT '00:00:00' NOT NULL,
  "googletracker" character varying(50) DEFAULT '' NOT NULL,
  "emaillowcoverage" smallint DEFAULT '0' NOT NULL,
  "emailtesttimingchanged" smallint DEFAULT '0' NOT NULL,
  "emailbrokensubmission" smallint DEFAULT '1' NOT NULL,
  "emailredundantfailures" smallint DEFAULT '0' NOT NULL,
  "emailadministrator" smallint DEFAULT '1' NOT NULL,
  "showipaddresses" smallint DEFAULT '1' NOT NULL,
  "cvsviewertype" character varying(10) DEFAULT NULL,
  "testtimestd" numeric(3,1) DEFAULT '4.0',
  "testtimestdthreshold" numeric(3,1) DEFAULT '1.0',
  "showtesttime" smallint DEFAULT '0',
  "testtimemaxstatus" smallint DEFAULT '3',
  "emailmaxitems" smallint DEFAULT '5',
  "emailmaxchars" bigint DEFAULT '255',
  "displaylabels" smallint default '1',
  "autoremovetimeframe" bigint default '0',
  "autoremovemaxbuilds" bigint default '300',
  "uploadquota" bigint default '0',
  "webapikey" character varying(40) DEFAULT '' NOT NULL,
  "tokenduration" integer DEFAULT '0',
  "showcoveragecode" smallint default '1',
  "sharelabelfilters" smallint default '0',
  "authenticatesubmissions" smallint default '0',
  PRIMARY KEY ("id")
);
CREATE INDEX "name2" on "project" ("name");
CREATE INDEX "public" on "project" ("public");

--
-- Table: site
--
CREATE TABLE "site" (
  "id" serial NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "ip" character varying(255) DEFAULT '' NOT NULL,
  "latitude" character varying(10) DEFAULT '' NOT NULL,
  "longitude" character varying(10) DEFAULT '' NOT NULL,
  "outoforder" smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX "site_name" on "site" ("name");

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
CREATE INDEX "siteid3" on "siteinformation" ("siteid", "timestamp");

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
CREATE INDEX "siteid4" on "site2user" ("siteid");
CREATE INDEX "userid" on "site2user" ("userid");

--
-- Table: test
--
CREATE TABLE "test" (
  "id" serial NOT NULL,
  "projectid" bigint NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX "test_name_projectid_unique" ON "test" ("name", "projectid");

--
-- Table: testoutput
--
CREATE TABLE "testoutput" (
  "id" serial NOT NULL,
  "testid" bigint NOT NULL,
  "crc32" bigint NOT NULL,
  "path" character varying(255) DEFAULT '' NOT NULL,
  "command" text NOT NULL,
  "output" bytea NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "crc323" on "testoutput" ("crc32");
CREATE INDEX "testoutput_testid_index" on "testoutput" ("testid");


--
-- Table: build2test
--
CREATE TABLE "build2test" (
  "id" serial NOT NULL,
  "buildid" bigint DEFAULT '0' NOT NULL,
  "outputid" bigint DEFAULT '0' NOT NULL,
  "testid" bigint DEFAULT '0' NOT NULL,
  "status" character varying(10) DEFAULT '' NOT NULL,
  "details" character varying(255) DEFAULT '' NOT NULL,
  "time" numeric(7,2) DEFAULT '0.00' NOT NULL,
  "timemean" numeric(7,2) DEFAULT '0.00' NOT NULL,
  "timestd" numeric(7,2) DEFAULT '0.00' NOT NULL,
  "timestatus" smallint DEFAULT '0' NOT NULL,
  "newstatus" smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "build2test_buildid" on "build2test" ("buildid");
CREATE INDEX "build2test_outputid" on "build2test" ("outputid");
CREATE INDEX "build2test_testid" on "build2test" ("testid");
CREATE INDEX "status" on "build2test" ("status");
CREATE INDEX "timestatus" on "build2test" ("timestatus");
CREATE INDEX "newstatus" on "build2test" ("newstatus");

--
-- Table: buildtesttime
--
CREATE TABLE "buildtesttime" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "time" numeric(7,2) DEFAULT '0.00' NOT NULL,
  PRIMARY KEY ("buildid")
);

--
-- Table: updatefile
--
CREATE TABLE "updatefile" (
  "updateid" bigint DEFAULT '0' NOT NULL,
  "filename" character varying(255) DEFAULT '' NOT NULL,
  "checkindate" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "author" character varying(255) DEFAULT '' NOT NULL,
  "email" character varying(255) DEFAULT '' NOT NULL,
  "committer" character varying(255) DEFAULT '' NOT NULL,
  "committeremail" character varying(255) DEFAULT '' NOT NULL,
  "log" text NOT NULL,
  "revision" character varying(60) DEFAULT '0' NOT NULL,
  "priorrevision" character varying(60) DEFAULT '0' NOT NULL,
  "status" character varying(12) DEFAULT '' NOT NULL
);
CREATE INDEX "updatefile_updateid" on "updatefile" ("updateid");
CREATE INDEX "updatefile_author" on "updatefile" ("author");

--
-- Table: user
--
CREATE TABLE "user" (
  "id" serial NOT NULL,
  "email" character varying(255) DEFAULT '' NOT NULL,
  "password" character varying(255) DEFAULT '' NOT NULL,
  "firstname" character varying(255) DEFAULT '' NOT NULL,
  "lastname" character varying(255) DEFAULT '' NOT NULL,
  "institution" character varying(255) DEFAULT '' NOT NULL,
  "admin" smallint DEFAULT '0' NOT NULL,
  "email_verified_at" timestamp NULL,
  "remember_token" varchar(100) NULL,
  "created_at" timestamp NULL,
  "updated_at" timestamp NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "email" on "user" ("email");


--
-- Table: usertemp
--
CREATE TABLE "usertemp" (
  "email" character varying(255) DEFAULT '' NOT NULL,
  "password" character varying(255) DEFAULT '' NOT NULL,
  "firstname" character varying(255) DEFAULT '' NOT NULL,
  "lastname" character varying(255) DEFAULT '' NOT NULL,
  "institution" character varying(255) DEFAULT '' NOT NULL,
  "registrationdate" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "registrationkey" character varying(40)  DEFAULT '' NOT NULL,
  PRIMARY KEY ("email")
);
CREATE INDEX "usertemp_registrationdate" on "usertemp" ("registrationdate");

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
  "emailsuccess" smallint DEFAULT '0' NOT NULL,
  "emailmissingsites" smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("userid", "projectid")
);
CREATE INDEX "cvslogin" on "user2project" ("cvslogin");
CREATE INDEX "emailtype" on "user2project" ("emailtype");
CREATE INDEX "emailsuccess" on "user2project" ("emailsuccess");
CREATE INDEX "emailmissingsites" on "user2project" ("emailmissingsites");

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
CREATE INDEX "buildid10" on "buildnote" ("buildid");

--
-- Table: repositories
--
CREATE TABLE "repositories" (
  "id" serial NOT NULL,
  "url" character varying(255) NOT NULL,
  "username" character varying(50) DEFAULT '' NOT NULL,
  "password" character varying(50) DEFAULT '' NOT NULL,
  "branch" character varying(60) DEFAULT '' NOT NULL,
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
  "id" serial NOT NULL,
  "outputid" bigint NOT NULL,
  "name" character varying(70) NOT NULL,
  "type" character varying(70) NOT NULL,
  "value" text NOT NULL,
   PRIMARY KEY ("id")
);
CREATE INDEX "testmeasurement_outputid" on "testmeasurement" ("outputid");

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
  "revision" character varying(60) DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "date" on "dailyupdate" ("date");
CREATE INDEX "projectid3" on "dailyupdate" ("projectid");

--
-- Table: dailyupdatefile
--
CREATE TABLE "dailyupdatefile" (
  "dailyupdateid" bigint DEFAULT '0' NOT NULL,
  "filename" character varying(255) DEFAULT '' NOT NULL,
  "checkindate" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "author" character varying(255) DEFAULT '' NOT NULL,
  "email" character varying(255) DEFAULT '' NOT NULL,
  "log" text NOT NULL,
  "revision" character varying(60) DEFAULT '0' NOT NULL,
  "priorrevision" character varying(60) DEFAULT '0' NOT NULL
);
CREATE INDEX "buildid11" on "dailyupdatefile" ("dailyupdateid");
CREATE INDEX "buildid11_2" on "dailyupdatefile" ("author");

--
-- Table: builderrordiff
--
CREATE TABLE "builderrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference_positive" bigint NOT NULL,
  "difference_negative" bigint NOT NULL,
  CONSTRAINT "unique_builddiff" UNIQUE ("buildid", "type")
);
CREATE INDEX "builderrordiff_buildid" on "builderrordiff" ("buildid");
CREATE INDEX "builderrordiff_type" on "builderrordiff" ("type");
CREATE INDEX "builderrordiff_difference_positive" on "builderrordiff" ("difference_positive");
CREATE INDEX "builderrordiff_difference_negative" on "builderrordiff" ("difference_negative");
CREATE INDEX "builderrordiff_buildid_type" on "builderrordiff" ("buildid", "type");

--
-- Table: testdiff
--
CREATE TABLE "testdiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference_positive" bigint NOT NULL,
  "difference_negative" bigint NOT NULL,
  CONSTRAINT "unique_testdiff" UNIQUE ("buildid", "type")
);
CREATE INDEX "buildid13" on "testdiff" ("buildid", "type");
CREATE INDEX "testdiff_type" on "testdiff" ("type");
CREATE INDEX "testdiff_difference_positive" on "testdiff" ("difference_positive");
CREATE INDEX "testdiff_difference_negative" on "testdiff" ("difference_negative");
CREATE INDEX "testdiff_buildid_type" on "testdiff" ("buildid", "type");

--
-- Table: build2note
--
CREATE TABLE "build2note" (
  "buildid" bigint NOT NULL,
  "noteid" bigint NOT NULL,
  "time" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL
);
CREATE INDEX "buildid14" on "build2note" ("buildid");
CREATE INDEX "noteid" on "build2note" ("noteid");

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
CREATE INDEX "userid2" on "userstatistics" ("userid");
CREATE INDEX "projectid4" on "userstatistics" ("projectid");
CREATE INDEX "checkindate" on "userstatistics" ("checkindate");

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
CREATE INDEX "date2" on "summaryemail" ("date");
CREATE INDEX "groupid3" on "summaryemail" ("groupid");

--
-- Table: configureerror
--
CREATE TABLE "configureerror" (
  "configureid" integer NOT NULL,
  "type" smallint,
  "text" text
);
CREATE INDEX "configureid2" on "configureerror" ("configureid");
CREATE INDEX "type3" on "configureerror" ("type");

--
-- Table: configureerrordiff
--
CREATE TABLE "configureerrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint,
  "difference" bigint,
  CONSTRAINT "unique_configureerrordiff" UNIQUE ("buildid", "type")
);
CREATE INDEX "buildid16" on "configureerrordiff" ("buildid");
CREATE INDEX "type4" on "configureerrordiff" ("type");
CREATE INDEX "configureerrordiff_buildid_type" on "configureerrordiff" ("buildid", "type");

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
CREATE INDEX "coveragefileid" on "coveragefile2user" ("fileid");
CREATE INDEX "userid3" on "coveragefile2user" ("userid");

--
-- Table: label
--
CREATE TABLE "label" (
  "id" bigserial NOT NULL,
  "text" character varying(255) NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "text" UNIQUE ("text")
);

--
-- Table: label2build
--
CREATE TABLE "label2build" (
  "labelid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildid")
);
CREATE INDEX "labelid" on "label2build" ("labelid");
CREATE INDEX "label2build_buildid" on "label2build" ("buildid");

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
  "outputid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildid", "outputid")
);
CREATE INDEX "label2test_buildid" on "label2test" ("buildid");
CREATE INDEX "label2test_outputid" on "label2test" ("outputid");

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
  "groupid" bigint NOT NULL,
  "path" character varying(512) DEFAULT '' NOT NULL,
  "position" smallint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "subproject_unique1" UNIQUE ("name", "projectid", "endtime")
);
CREATE INDEX "projectid5" on "subproject" ("projectid");
CREATE INDEX "subprojectgroupid" on "subproject" ("groupid");
CREATE INDEX "subproject_unique2" on "subproject" ("name", "projectid", "endtime");
CREATE INDEX "subprojectpath" on "subproject" ("path");

--
-- Table: subprojectgroup
--
CREATE TABLE "subprojectgroup" (
  "id" bigserial NOT NULL,
  "name" character varying(255) NOT NULL,
  "projectid" bigint NOT NULL,
  "coveragethreshold" smallint DEFAULT '70' NOT NULL,
  "is_default" smallint NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "position" bigint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "spgroupname" on "subprojectgroup" ("name");
CREATE INDEX "spgroupprojectid" on "subprojectgroup" ("projectid");
CREATE INDEX "spgroupposition" on "subprojectgroup" ("position");

--
-- Table: subproject2subproject
--
CREATE TABLE "subproject2subproject" (
  "subprojectid" bigint NOT NULL,
  "dependsonid" bigint NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "subprojectid" on "subproject2subproject" ("subprojectid");
CREATE INDEX "dependsonid" on "subproject2subproject" ("dependsonid");

--
-- Table: subproject2build
--
CREATE TABLE "subproject2build" (
  "subprojectid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "subprojectid2" on "subproject2build" ("subprojectid");

--
-- Table: buildfailure
--
CREATE TABLE "buildfailure" (
  "id" bigserial NOT NULL,
  "buildid" bigint NOT NULL,
  "detailsid" bigint NOT NULL,
  "workingdirectory" character varying(512) NOT NULL,
  "sourcefile" character varying(512) NOT NULL,
  "newstatus" smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "buildid17" on "buildfailure" ("buildid");
CREATE INDEX "buildfailure_details" on "buildfailure" ("detailsid");
CREATE INDEX "buildfailure_newstatus" on "buildfailure" ("newstatus");

--
-- Table: buildfailuredetails
--
CREATE TABLE "buildfailuredetails" (
  "id" bigserial NOT NULL,
  "type" smallint NOT NULL,
  "stdoutput" text NOT NULL,
  "stderror" text NOT NULL,
  "exitcondition" character varying(255) NOT NULL,
  "language" character varying(64) NOT NULL,
  "targetname" character varying(255) NOT NULL,
  "outputfile" character varying(512) NOT NULL,
  "outputtype" character varying(255) NOT NULL,
  "crc32" bigint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "type5" on "buildfailuredetails" ("type");
CREATE INDEX "buildfailuredetails_crc32" on "buildfailuredetails" ("crc32");

--
-- Table: buildfailureargument
--
CREATE TABLE "buildfailureargument" (
  "id" bigserial NOT NULL,
  "argument" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "argument" on "buildfailureargument" ("argument");

--
-- Table: buildfailure2argument
--
CREATE TABLE "buildfailure2argument" (
  "buildfailureid" bigint NOT NULL,
  "argumentid" bigint NOT NULL,
  "place" bigint NOT NULL
);
CREATE INDEX "argumentid" on "buildfailure2argument" ("argumentid");
CREATE INDEX "buildfailureid" on "buildfailure2argument" ("buildfailureid");

--
-- Table: labelemail
--
CREATE TABLE "labelemail" (
  "projectid" bigint NOT NULL,
  "userid" bigint NOT NULL,
  "labelid" bigint NOT NULL
);
CREATE INDEX "projectid6" on "labelemail" ("projectid");
CREATE INDEX "userid4" on "labelemail" ("userid");

--
-- Table: buildemail
--
CREATE TABLE "buildemail" (
  "userid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  "category" smallint NOT NULL,
  "time" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "buildemail_userid" on "buildemail" ("userid");
CREATE INDEX "buildemail_buildid" on "buildemail" ("buildid");
CREATE INDEX "buildemail_category" on "buildemail" ("category");
CREATE INDEX "buildemail_time" on "buildemail" ("time");

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
  "filename" character varying(500) DEFAULT '' NOT NULL,
  "projectid" bigint  NOT NULL,
  "status" smallint NOT NULL,
  "attempts" bigint DEFAULT '0' NOT NULL,
  "filesize" bigint DEFAULT '0' NOT NULL,
  "filemd5sum" character varying(32) DEFAULT '' NOT NULL,
  "lastupdated" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "created" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "started" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "finished" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "submission_projectid" on "submission" ("projectid");
CREATE INDEX "submission_status" on "submission" ("status");
CREATE INDEX "submission_finished" on "submission" ("finished");


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
-- Table structure for table "filesum"
--

CREATE TABLE "filesum" (
  "id" serial NOT NULL,
  "md5sum" character varying(32) NOT NULL,
  "contents" bytea,
  PRIMARY KEY ("id")
);
CREATE INDEX "filesum_md5sum" on "filesum" ("md5sum");

--
-- Table: submissionprocessor
--
CREATE TABLE "submissionprocessor" (
  "projectid" bigint NOT NULL,
  "pid" bigint NOT NULL,
  "lastupdated" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "locked" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("projectid")
);

--
-- Table: user2repository
--
CREATE TABLE "user2repository" (
  "userid" bigint NOT NULL,
  "credential" character varying(512) NOT NULL,
  "projectid" bigint NOT NULL DEFAULT '0'
);
CREATE INDEX "user2repository_userid" on "user2repository" ("userid");
CREATE INDEX "user2repository_credential" on "user2repository" ("credential");
CREATE INDEX "user2repository_projectid" on "user2repository" ("projectid");

CREATE TABLE "apitoken" (
  "projectid" bigint NOT NULL,
  "token" character varying(40),
  "expiration_date" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "apitoken_token" on "apitoken" ("token");

--
-- Table: uploadfile
--
CREATE TABLE "uploadfile" (
  "id" serial NOT NULL,
  "filename" character varying(255) NOT NULL,
  "filesize" bigint NOT NULL DEFAULT '0',
  "sha1sum" character varying(40) NOT NULL,
  "isurl" smallint NOT NULL DEFAULT '0',
  PRIMARY KEY("id")
);
CREATE INDEX "uploadfile_sha1sum" on "uploadfile" ("sha1sum");

--
-- Table: build2uploadfile
--
CREATE TABLE "build2uploadfile" (
  "fileid" bigint NOT NULL,
  "buildid" bigint NOT NULL
);
CREATE INDEX "build2uploadfile_fileid" on "build2uploadfile" ("fileid");
CREATE INDEX "build2uploadfile_buildid" on "build2uploadfile" ("buildid");

--
-- Table: submission2ip
--
CREATE TABLE "submission2ip" (
  "submissionid" bigint NOT NULL UNIQUE,
  "ip" character varying(255) DEFAULT '' NOT NULL,
  PRIMARY KEY("submissionid")
);

CREATE TABLE "measurement" (
  "id" serial NOT NULL,
  "projectid" bigint NOT NULL,
  "name" character varying(40) NOT NULL,
  "testpage" smallint NOT NULL DEFAULT '0',
  "summarypage" smallint NOT NULL DEFAULT '0',
  PRIMARY KEY("id")
);
CREATE INDEX "measurement_projectid" on "measurement" ("projectid");
CREATE INDEX "measurement_name" on "measurement" ("name");


--
-- Table: overview_components
--
CREATE TABLE "overview_components" (
  "projectid" bigint NOT NULL,
  "buildgroupid" bigint NOT NULL,
  "position" bigint NOT NULL,
  "type" text DEFAULT 'build' NOT NULL
);
CREATE INDEX "overview_components_projectid" on "overview_components" ("projectid");
CREATE INDEX "overview_components_buildgroupid" on "overview_components" ("buildgroupid");


CREATE TABLE "buildfile" (
  "buildid" bigint NOT NULL,
  "filename" character varying(255) NOT NULL,
  "md5" character varying(40) NOT NULL,
  "type" character varying(32) NOT NULL
);
CREATE INDEX "buildfile_buildid" on "buildfile" ("buildid");
CREATE INDEX "buildfile_filename" on "buildfile" ("filename");
CREATE INDEX "buildfile_type" on "buildfile" ("type");
CREATE INDEX "buildfile_md5" on "buildfile" ("md5");


--
-- Table: password
--
CREATE TABLE "password" (
  "userid" integer NOT NULL,
  "password" character varying(255) DEFAULT '' NOT NULL,
  "date" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "updated_at" timestamp NULL,
  "created_at" timestamp NULL
);
CREATE INDEX "password_userid" on "password" ("userid");


--
-- Table: lockout
--
CREATE TABLE "lockout" (
  "userid" integer NOT NULL,
  "failedattempts" smallint DEFAULT '0',
  "islocked" smallint DEFAULT '0',
  "unlocktime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("userid")
);


--
-- Table: authtoken
--
CREATE TABLE "authtoken" (
  "hash" character varying(128) NOT NULL,
  "userid" integer DEFAULT '0' NOT NULL ,
  "created" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "expires" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "description" character varying(255)
);
CREATE INDEX "authtokenhash" on "authtoken" ("hash");
CREATE INDEX "authtokenuserid" on "authtoken" ("userid");
CREATE INDEX "authtokenexpires" on "authtoken" ("expires");


--
-- Table: buildproperties
--
CREATE TABLE "buildproperties" (
  "buildid" integer DEFAULT '0' NOT NULL,
  "properties" text DEFAULT '' NOT NULL,
  PRIMARY KEY ("buildid")
);

--
-- Table: related_builds
--
CREATE TABLE "related_builds" (
  "buildid" integer DEFAULT '0' NOT NULL,
  "relatedid" integer DEFAULT '0' NOT NULL,
  "relationship" character varying(255),
  PRIMARY KEY ("buildid", "relatedid")
);
CREATE INDEX "related_buildid" on "related_builds" ("buildid");
CREATE INDEX "relatedid" on "related_builds" ("relatedid");

--
-- Table: build_filters
--
CREATE TABLE "build_filters" (
  "projectid" bigint NOT NULL,
  "warnings" text,
  "errors" text,
  PRIMARY KEY ("projectid")
);


--
-- Table structure for table `migrations`
--
create table "migrations"
(
    "id" integer NOT NULL ,
    "migration" character varying(255) NOT NULL,
    "batch" integer NOT NULL
)
