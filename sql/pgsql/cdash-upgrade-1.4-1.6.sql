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
  "fullpath" character varying(255) DEFAULT '' NOT NULL,
  "projectid" bigint  NOT NULL,
  "status" smallint NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "submission_projectid" on "submission" ("projectid");
CREATE INDEX "submission_status" on "submission" ("status");

CREATE TABLE "buildtesttime" (
  "buildid" bigint DEFAULT '0' NOT NULL,
  "time" numeric(7,2) DEFAULT '0.00' NOT NULL,
  PRIMARY KEY ("buildid")
);

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
