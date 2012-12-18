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