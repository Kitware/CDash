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
