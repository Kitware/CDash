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