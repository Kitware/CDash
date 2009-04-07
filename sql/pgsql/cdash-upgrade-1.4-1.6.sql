--
-- Table: coveragefilepriority
--
CREATE TABLE "coveragefilepriority" (
  "fileid" bigint NOT NULL,
  "priority" smallint NOT NULL,
  PRIMARY KEY ("fileid"),
);
CREATE INDEX "coveragefilepriority_priority" on "coveragefile2user" ("priority");
