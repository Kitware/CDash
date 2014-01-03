CREATE TABLE "build2update" (
  "buildid" bigint NOT NULL,
  "updateid" bigint NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "build2update_updateid" on "build2update" ("updateid");

CREATE TABLE "submission2ip" (
  "submissionid" bigint NOT NULL UNIQUE,
  "ip" character varying(255) DEFAULT '' NOT NULL,
  PRIMARY KEY("submissionid")
);

CREATE TABLE "measurement" (
  "id" serial NOT NULL,
  "projectid" bigint NOT NULL,
  "name" character varying(40) NOT NULL,
  "testpage" smallint NOT NULL DEFAULT '0',
  "summarypage" smallint NOT NULL DEFAULT '0',
  PRIMARY KEY("id")
);
CREATE INDEX "measurement_projectid" on "measurement" ("projectid");
CREATE INDEX "measurement_name" on "measurement" ("name");


CREATE TABLE "feed" (
  "id" serial NOT NULL,
  "projectid" bigint NOT NULL,
  "date" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "buildid" bigint NOT NULL DEFAULT '0',
  "type" bigint NOT NULL DEFAULT '0',
  "description" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "feed_projectid" on "feed" ("projectid");
CREATE INDEX "feed_date" on "feed" ("date");