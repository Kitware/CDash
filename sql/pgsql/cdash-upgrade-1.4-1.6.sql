--
-- Table: coveragefilepriority
--
CREATE TABLE "coveragefilepriority" (
  "id" bigint NOT NULL,
  "priority" smallint NOT NULL,
  "fullpath" character varying(255) DEFAULT '' NOT NULL,
  "projectid" bigint  NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "coveragefilepriority_priority" on "coveragefile2user" ("priority");
CREATE INDEX "coveragefilepriority_fullpath" on "coveragefile2user" ("fullpath");
CREATE INDEX "coveragefilepriority_projectid" on "coveragefile2user" ("projectid");