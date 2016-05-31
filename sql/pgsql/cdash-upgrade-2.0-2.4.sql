DROP TABLE IF EXISTS "overviewbuildgroups";

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

CREATE TABLE "subprojectgroup" (
  "id" bigserial NOT NULL,
  "name" character varying(255) NOT NULL,
  "projectid" bigint NOT NULL,
  "coveragethreshold" smallint DEFAULT '70' NOT NULL,
  "is_default" smallint NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "spgroupname" on "subprojectgroup" ("name");
CREATE INDEX "spgroupprojectid" on "subprojectgroup" ("projectid");

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

CREATE TABLE "password" (
  "userid" integer NOT NULL,
  "password" character varying(255) DEFAULT '' NOT NULL,
  "date" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL
);
CREATE INDEX "password_userid" on "password" ("userid");

CREATE TABLE "dynamicanalysissummary" (
  "buildid" integer DEFAULT '0' NOT NULL,
  "checker" character varying(60) DEFAULT '' NOT NULL,
  "numdefects" integer DEFAULT '0' NOT NULL,
  PRIMARY KEY ("buildid")
);

CREATE TABLE "lockout" (
  "userid" integer NOT NULL,
  "failedattempts" smallint DEFAULT '0',
  "islocked" smallint DEFAULT '0',
  "unlocktime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("userid")
);
