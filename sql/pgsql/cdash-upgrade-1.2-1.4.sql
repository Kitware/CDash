--
-- Table: buildfailure
--
CREATE TABLE "buildfailure" (
  "id" bigserial NOT NULL,
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "workingdirectory" character varying(255) NOT NULL,
  "arguments" text NOT NULL,
  "stdoutput" text NOT NULL,
  "stderror" text NOT NULL,
  "exitcondition" smallint NOT NULL,
  "language" character varying(10) NOT NULL,
  "targetname" character varying(255) NOT NULL,
  "outputfile" character varying(255) NOT NULL,
  "outputtype" character varying(255) NOT NULL,
  "sourcefile" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "buildid" on "buildfailure" ("buildid");
CREATE INDEX "type" on "buildfailure" ("type");

--
-- Table: labelemail
--
CREATE TABLE "labelemail" (
  "projectid" bigint NOT NULL,
  "userid" bigint NOT NULL,
  "labelid" bigint NOT NULL
);
CREATE INDEX "projectid" on "labelemail" ("projectid");
CREATE INDEX "userid" on "labelemail" ("userid");

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
  "argumentid" bigint NOT NULL
);
CREATE INDEX "argumentid" on "buildfailure2argument" ("argumentid");
CREATE INDEX "buildfailureid" on "buildfailure2argument" ("buildfailureid");

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
CREATE INDEX "userid2" on "coveragefile2user" ("userid");

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
CREATE INDEX "projectid2" on "subproject" ("projectid");

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
