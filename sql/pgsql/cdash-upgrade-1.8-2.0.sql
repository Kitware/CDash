CREATE TABLE "uploadfile" (
  "id" serial NOT NULL,
  "file" bytea NOT NULL,
  "filename" character varying(255) NOT NULL,
  "filesize" bigint NOT NULL DEFAULT '0',
  "md5sum" character varying(32) NOT NULL,
  PRIMARY KEY("id")
);
CREATE INDEX "uploadfile_md5sum" on "uploadfile" ("md5sum");

CREATE TABLE "build2uploadfile" (
  "fileid" bigint NOT NULL,
  "buildid" bigint NOT NULL
);
CREATE INDEX "build2uploadfile_fileid" on "build2uploadfile" ("fileid");
CREATE INDEX "build2uploadfile_buildid" on "build2uploadfile" ("buildid");
