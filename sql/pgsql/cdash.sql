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
CREATE INDEX "siteid" on "build" ("siteid", "name");
CREATE INDEX "projectid" on "build" ("projectid");


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
  "summaryemail" smallint DEFAULT '0',
  PRIMARY KEY ("id")
);
CREATE INDEX "projectid2" on "buildgroup" ("projectid");


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
  "buildtype" character varying(20) DEFAULT '' NOT NULL,
  "buildname" character varying(255) DEFAULT '' NOT NULL,
  "siteid" bigint DEFAULT '0' NOT NULL,
  "expected" smallint DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "groupid2" on "build2grouprule" ("groupid");


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
CREATE INDEX "buildid" on "builderror" ("buildid");


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
CREATE INDEX "buildid2" on "buildupdate" ("buildid");


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
CREATE INDEX "buildid3" on "configure" ("buildid");


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
  "line" bigint DEFAULT '0' NOT NULL,
  "code" character varying(10) DEFAULT '' NOT NULL
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
  "type" character varying(50) DEFAULT '' NOT NULL,
  "value" bigint DEFAULT '0' NOT NULL
);
CREATE INDEX "buildid7" on "dynamicanalysisdefect" ("dynamicanalysisid");


--
-- Table: image
--
CREATE TABLE "image" (
  "id" serial NOT NULL,
  "img" bytea NOT NULL,
  "extension" text NOT NULL,
  "checksum" bigint NOT NULL
);
CREATE INDEX "id" on "image" ("id");
CREATE INDEX "checksum" on "image" ("checksum");


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
CREATE INDEX "crc322" on "note" ("crc32");


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
CREATE INDEX "siteid2" on "siteinformation" ("siteid", "timestamp");


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
CREATE INDEX "siteid3" on "site2user" ("siteid");
CREATE INDEX "userid" on "site2user" ("userid");


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
CREATE INDEX "crc323" on "test" ("crc32");
CREATE INDEX "name" on "test" ("name");


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
CREATE INDEX "buildid8" on "build2test" ("buildid");
CREATE INDEX "testid" on "build2test" ("testid");
CREATE INDEX "status" on "build2test" ("status");
CREATE INDEX "timestatus" on "build2test" ("timestatus");


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
CREATE INDEX "buildid9" on "updatefile" ("buildid");


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
  "emailcategory" smallint DEFAULT '62' NOT NULL,
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
CREATE INDEX "buildid10" on "buildnote" ("buildid");


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
CREATE INDEX "testid2" on "testmeasurement" ("testid");


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
CREATE INDEX "buildid11" on "dailyupdate" ("id");


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
CREATE INDEX "buildid12" on "dailyupdatefile" ("dailyupdateid");


--
-- Table: builderrordiff
--
CREATE TABLE "builderrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "buildid13" on "builderrordiff" ("buildid");


--
-- Table: testdiff
--
CREATE TABLE "testdiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "buildid14" on "testdiff" ("buildid", "type");


--
-- Table: build2note
--
CREATE TABLE "build2note" (
  "buildid" bigint NOT NULL,
  "noteid" bigint NOT NULL,
  "time" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL
);
CREATE INDEX "buildid15" on "build2note" ("buildid", "noteid");


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
CREATE INDEX "projectid3" on "userstatistics" ("projectid");
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
CREATE INDEX "date" on "summaryemail" ("date");
CREATE INDEX "groupid3" on "summaryemail" ("groupid");


--
-- Table: configureerror
--
CREATE TABLE "configureerror" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "text" text NOT NULL
);
CREATE INDEX "buildid16" on "configureerror" ("buildid");
CREATE INDEX "type" on "configureerror" ("type");


--
-- Table: configureerrordiff
--
CREATE TABLE "configureerrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "buildid17" on "configureerrordiff" ("buildid");
CREATE INDEX "type2" on "configureerrordiff" ("type");


--
-- Table: coveragesummarydiff
--
CREATE TABLE "coveragesummarydiff" (
  "buildid" bigint NOT NULL,
  "loctested" bigint DEFAULT '0' NOT NULL,
  "locuntested" bigint DEFAULT '0' NOT NULL,
  PRIMARY KEY ("buildid")
);

