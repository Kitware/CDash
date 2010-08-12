--
-- Table: projectjobscript
--
CREATE TABLE "projectjobscript" (
  "id" serial NOT NULL,
  "projectid" bigint NOT NULL,
  "script" text NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "projectjobscript_projectid" on "projectjobscript" ("projectid");

--
-- Table: client_site2program
--
CREATE TABLE "client_site2program" (
  "siteid" bigint  NOT NULL,
  "name" character varying(30) NOT NULL,
  "version" character varying(30) NOT NULL,
  "path" character varying(512) NOT NULL
);
CREATE INDEX "client_site2program_siteid" on "client_site2program" ("siteid");

--
-- Table: errorlog
--
CREATE TABLE "errorlog" (
  "id" serial NOT NULL,
  "projectid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  "date" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "type" smallint  NOT NULL,
  "description" text NOT NULL,
  "resourcetype" smallint NOT NULL DEFAULT '0',
  "resourceid" bigint  NOT NULL
);
CREATE INDEX "errorlog_resourceid" on "errorlog" ("resourceid");
CREATE INDEX "errorlog_date" on "errorlog" ("date");
CREATE INDEX "errorlog_resourcetype" on "errorlog" ("resourcetype");
CREATE INDEX "errorlog_projectid" on "errorlog" ("projectid");
CREATE INDEX "errorlog_buildid" on "errorlog" ("buildid");

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
