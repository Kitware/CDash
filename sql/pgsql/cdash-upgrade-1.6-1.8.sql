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

CREATE TABLE "client_site2program" (
  "siteid" bigint  NOT NULL,
  "name" character varying(30) NOT NULL,
  "version" character varying(30) NOT NULL,
  "path" character varying(512) NOT NULL
);
CREATE INDEX "client_site2program_siteid" on "client_site2program" ("siteid");