CREATE TABLE "build2update" (
  "buildid" bigint NOT NULL,
  "updateid" bigint NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "build2update_updateid" on "build2update" ("updateid");



