CREATE TABLE "build2configure" (
  "configureid" integer DEFAULT '0' NOT NULL,
  "buildid" integer DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "configureid" on "build2configure" ("configureid");
