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

CREATE TABLE "summaryemail" (
  "buildid" smallint  NOT NULL,
  "date" date NOT NULL,
  "groupid" smallint  NOT NULL
);
CREATE INDEX "summaryemail_date_idx" on "summaryemail" ("date", "groupid");


CREATE TABLE "configureerror" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "text" text NOT NULL,
);
CREATE INDEX "configureerror_buildid_idx" on "configureerror" ("buildid");
CREATE INDEX "configureerror_type_idx" on "configureerror" ("type");


CREATE TABLE "configureerrordiff" (
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "difference" smallint NOT NULL,
);
CREATE INDEX "configureerrordiff_buildid_idx" on "configureerrordiff" ("buildid");
CREATE INDEX "configureerrordiff_type_idx" on "configureerrordiff" ("type");

