CREATE TABLE "overviewbuildgroups" (
  "projectid" bigint NOT NULL,
  "buildgroupid" bigint NOT NULL,
  "position" bigint NOT NULL
);
CREATE INDEX "overviewbuildgroups_projectid" on "overviewbuildgroups" ("projectid");
CREATE INDEX "overviewbuildgroups_buildgroupid" on "overviewbuildgroups" ("buildgroupid");
