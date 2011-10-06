CREATE TABLE "uploadfile" (
  "id" serial NOT NULL,
  "filename" character varying(255) NOT NULL,
  "filesize" bigint NOT NULL DEFAULT '0',
  "sha1sum" character varying(40) NOT NULL,
  "isurl" smallint NOT NULL DEFAULT '0',
  PRIMARY KEY("id")
);
CREATE INDEX "uploadfile_sha1sum" on "uploadfile" ("sha1sum");

CREATE TABLE "build2uploadfile" (
  "fileid" bigint NOT NULL,
  "buildid" bigint NOT NULL
);
CREATE INDEX "build2uploadfile_fileid" on "build2uploadfile" ("fileid");
CREATE INDEX "build2uploadfile_buildid" on "build2uploadfile" ("buildid");
