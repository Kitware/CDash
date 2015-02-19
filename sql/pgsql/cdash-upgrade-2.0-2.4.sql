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
