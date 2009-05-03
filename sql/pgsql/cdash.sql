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
CREATE INDEX "projectid" on "build" ("projectid");
CREATE INDEX "starttime" on "build" ("starttime");
CREATE INDEX "submittime" on "build" ("submittime");
CREATE INDEX "siteid" on "build" ("siteid");
CREATE INDEX "stamp" on "build" ("stamp");
CREATE INDEX "type" on "build" ("type");
CREATE INDEX "name" on "build" ("name");

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
  "includesubprojectotal" smallint DEFAULT '1',
  PRIMARY KEY ("id")
);
CREATE INDEX "projectid2" on "buildgroup" ("projectid");
CREATE INDEX "starttime2" on "buildgroup" ("starttime");
CREATE INDEX "endtime" on "buildgroup" ("endtime");

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
  "repeatcount" bigint DEFAULT '0' NOT NULL
);
CREATE INDEX "buildid" on "builderror" ("buildid");
CREATE INDEX "type2" on "builderror" ("type");

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
  "line" bigint DEFAULT '0' NOT NULL,
  "code" character varying(10) DEFAULT '' NOT NULL
);
CREATE INDEX "fileid2" on "coveragefilelog" ("fileid");
CREATE INDEX "buildid5" on "coveragefilelog" ("buildid");
CREATE INDEX "coveragefilelog_line" on "coveragefilelog" ("line");

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
  "checksum" bigint NOT NULL,
  CONSTRAINT "id" PRIMARY KEY ("id")
);
CREATE INDEX "checksum" on "image" ("checksum");

--
-- Table: test2image
--
CREATE TABLE "test2image" (
  "imgid" serial NOT NULL,
  "testid" bigint DEFAULT '0' NOT NULL,
  "role" text NOT NULL,
  PRIMARY KEY ("imgid")
);
CREATE INDEX "testid" on "test2image" ("testid");

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
  PRIMARY KEY ("id")
);
CREATE INDEX "name3" on "site" ("name");

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
  "crc32" bigint NOT NULL,
  "name" character varying(255) DEFAULT '' NOT NULL,
  "path" character varying(255) DEFAULT '' NOT NULL,
  "command" text NOT NULL,
  "details" text NOT NULL,
  "output" text NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "crc323" on "test" ("crc32");
CREATE INDEX "name4" on "test" ("name");

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
CREATE INDEX "testid2" on "build2test" ("testid");
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
  `cookiekey` character varying(40)  DEFAULT '' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "email" on "user" ("email");

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
CREATE INDEX "cvslogin" on "user2project" ("cvslogin");
CREATE INDEX "emailtype" on "user2project" ("emailtype");

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
CREATE INDEX "testid3" on "testmeasurement" ("testid");

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
  "log" text NOT NULL,
  "revision" character varying(10) DEFAULT '0' NOT NULL,
  "priorrevision" character varying(10) DEFAULT '0' NOT NULL
);
CREATE INDEX "buildid11" on "dailyupdatefile" ("dailyupdateid");

--
-- Table: builderrordiff
--
CREATE TABLE "builderrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "buildid12" on "builderrordiff" ("buildid");

--
-- Table: testdiff
--
CREATE TABLE "testdiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "buildid13" on "testdiff" ("buildid", "type");

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
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "text" text NOT NULL
);
CREATE INDEX "buildid15" on "configureerror" ("buildid");
CREATE INDEX "type3" on "configureerror" ("type");

--
-- Table: configureerrordiff
--
CREATE TABLE "configureerrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" bigint NOT NULL
);
CREATE INDEX "buildid16" on "configureerrordiff" ("buildid");
CREATE INDEX "type4" on "configureerrordiff" ("type");

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
CREATE INDEX "projectid5" on "subproject" ("projectid");

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
CREATE INDEX "buildid17" on "buildfailure" ("buildid");
CREATE INDEX "type5" on "buildfailure" ("type");

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