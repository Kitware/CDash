DROP TABLE IF EXISTS "overviewbuildgroups";

CREATE TABLE "overview_components" (
  "projectid" bigint NOT NULL,
  "buildgroupid" bigint NOT NULL,
  "position" bigint NOT NULL,
  "type" text DEFAULT 'build' NOT NULL,
);
CREATE INDEX "overview_components_projectid" on "overview_components" ("projectid");
CREATE INDEX "overview_components_buildgroupid" on "overview_components" ("buildgroupid");
