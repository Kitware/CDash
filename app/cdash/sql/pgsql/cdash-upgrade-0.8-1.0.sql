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
