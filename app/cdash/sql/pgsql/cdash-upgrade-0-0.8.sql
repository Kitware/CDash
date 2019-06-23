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
