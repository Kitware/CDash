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
