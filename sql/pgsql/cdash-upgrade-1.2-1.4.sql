--
-- Table: buildfailure
--
CREATE TABLE "buildfailure" (
  "id" bigserial NOT NULL,
  "buildid" bigint NOT NULL,
  "type" smallint NOT NULL,
  "workingdirectory" character varying(255) NOT NULL,
  "arguments" text NOT NULL,
  "stdoutput" text NOT NULL,
  "stderror" text NOT NULL,
  "exitcondition" smallint NOT NULL,
  "language" character varying(10) NOT NULL,
  "targetname" character varying(255) NOT NULL,
  "outputfile" character varying(255) NOT NULL,
  "outputtype" character varying(255) NOT NULL,
  "sourcefile" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "buildid" on "buildfailure" ("buildid");
CREATE INDEX "type" on "buildfailure" ("type");

--
-- Table: labelemail
--
CREATE TABLE "labelemail" (
  "projectid" bigint NOT NULL,
  "userid" bigint NOT NULL,
  "labelid" bigint NOT NULL
);
CREATE INDEX "projectid" on "labelemail" ("projectid");
CREATE INDEX "userid" on "labelemail" ("userid");

--
-- Table: buildfailureargument
--
CREATE TABLE "buildfailureargument" (
  "id" bigserial NOT NULL,
  "argument" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "argument" on "buildfailureargument" ("argument");

--
-- Table: buildfailure2argument
--
CREATE TABLE "buildfailure2argument" (
  "buildfailureid" bigint NOT NULL,
  "argumentid" bigint NOT NULL
);
CREATE INDEX "argumentid" on "buildfailure2argument" ("argumentid");
CREATE INDEX "buildfailureid" on "buildfailure2argument" ("buildfailureid");

--
-- Table: banner
--
CREATE TABLE "banner" (
  "projectid" bigint NOT NULL,
  "text" character varying(500) NOT NULL,
  PRIMARY KEY ("projectid")
);

--
-- Table: coveragefile2user
--
CREATE TABLE "coveragefile2user" (
  "fileid" bigint NOT NULL,
  "userid" bigint NOT NULL,
  "position" smallint NOT NULL
);
CREATE INDEX "coveragefileid" on "coveragefile2user" ("fileid");
CREATE INDEX "userid2" on "coveragefile2user" ("userid");

--
-- Table: label
--
CREATE TABLE "label" (
  "id" bigserial NOT NULL,
  "text" character varying(255) NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "text" UNIQUE ("text")
);

--
-- Table: label2build
--
CREATE TABLE "label2build" (
  "labelid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildid")
);

--
-- Table: label2buildfailure
--
CREATE TABLE "label2buildfailure" (
  "labelid" bigint NOT NULL,
  "buildfailureid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildfailureid")
);

--
-- Table: label2coveragefile
--
CREATE TABLE "label2coveragefile" (
  "labelid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  "coveragefileid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildid", "coveragefileid")
);

--
-- Table: label2dynamicanalysis
--
CREATE TABLE "label2dynamicanalysis" (
  "labelid" bigint NOT NULL,
  "dynamicanalysisid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "dynamicanalysisid")
);

--
-- Table: label2test
--
CREATE TABLE "label2test" (
  "labelid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  "testid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "buildid", "testid")
);

--
-- Table: label2update
--
CREATE TABLE "label2update" (
  "labelid" bigint NOT NULL,
  "updateid" bigint NOT NULL,
  PRIMARY KEY ("labelid", "updateid")
);

--
-- Table: subproject
--
CREATE TABLE "subproject" (
  "id" bigserial NOT NULL,
  "name" character varying(255) NOT NULL,
  "projectid" bigint NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "projectid2" on "subproject" ("projectid");

--
-- Table: subproject2subproject
--
CREATE TABLE "subproject2subproject" (
  "subprojectid" bigint NOT NULL,
  "dependsonid" bigint NOT NULL,
  "starttime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL
);
CREATE INDEX "subprojectid" on "subproject2subproject" ("subprojectid");
CREATE INDEX "dependsonid" on "subproject2subproject" ("dependsonid");

--
-- Table: subproject2build
--
CREATE TABLE "subproject2build" (
  "subprojectid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "subprojectid2" on "subproject2build" ("subprojectid");

ALTER TABLE "buildfailureargument" ALTER COLUMN "argument" TYPE VARCHAR( 255 );
ALTER TABLE "buildfailureargument" ALTER COLUMN "argument" SET NOT NULL;
ALTER TABLE "buildfailure" ALTER COLUMN "exitcondition" TYPE VARCHAR( 255 );
ALTER TABLE "buildfailure" ALTER COLUMN "exitcondition" SET NOT NULL;
ALTER TABLE "buildfailure" ALTER COLUMN "language" TYPE VARCHAR( 64 );
ALTER TABLE "buildfailure" ALTER COLUMN "language" SET NOT NULL;
ALTER TABLE "buildfailure" ALTER COLUMN "sourcefile" TYPE VARCHAR( 512 );
ALTER TABLE "buildfailure" ALTER COLUMN "sourcefile" SET NOT NULL;
ALTER TABLE "buildfailure" DROP COLUMN "arguments";
ALTER TABLE "configure" ALTER COLUMN "log" TYPE MEDIUMTEXT;
ALTER TABLE "configure" ALTER COLUMN "log" SET NOT NULL;
CREATE INDEX "coverage_covered_idx" ON "coverage" ("covered");
CREATE INDEX "build2grouprule_starttime_idx" ON "build2grouprule" ("starttime");
CREATE INDEX "build2grouprule_endtime_idx" ON "build2grouprule" ("endtime");
CREATE INDEX "build2grouprule_buildtype_idx" ON "build2grouprule" ("buildtype");
CREATE INDEX "build2grouprule_buildname_idx" ON "build2grouprule" ("buildname");
CREATE INDEX "build2grouprule_expected_idx" ON "build2grouprule" ("expected");
CREATE INDEX "build2grouprule_siteid_idx" ON "build2grouprule" ("siteid");
DROP INDEX "build2note_buildid_idx";
CREATE INDEX "build2note_buildid_idx" ON "build2note" ("buildid");
CREATE INDEX "build2note_noteid_idx" ON "build2note" ("noteid");
CREATE INDEX "user2project_cvslogin_idx" ON "user2project" ("cvslogin");
CREATE INDEX "user2project_emailtype_idx" ON "user2project" ("emailtype");
CREATE INDEX "user_email_idx" ON "user" ("email");
CREATE INDEX "project_public_idx" ON "project" ("public");
CREATE INDEX "buildgroup_starttime_idx" ON "buildgroup" ("starttime");
CREATE INDEX "buildgroup_endtime_idx" ON "buildgroup" ("endtime");
CREATE INDEX "buildgroupposition_position_idx" ON "buildgroupposition" ("position");
CREATE INDEX "buildgroupposition_starttime_idx" ON "buildgroupposition" ("starttime");
CREATE INDEX "buildgroupposition_endtime_idx" ON "buildgroupposition" ("endtime");
CREATE INDEX "dailyupdate_date_idx" ON "dailyupdate" ("date");
CREATE INDEX "dailyupdate_projectid_idx" ON "dailyupdate" ("projectid");
CREATE INDEX "builderror_type_idx" ON "builderror" ("type");
CREATE INDEX "build_starttime_idx" ON "build" ("starttime");
CREATE INDEX "build_submittime_idx" ON "build" ("submittime");
DROP INDEX "build_siteid_idx";
CREATE INDEX "build_siteid_idx" ON "build" ("siteid");
CREATE INDEX "build_name_idx" ON "build" ("name");
CREATE INDEX "build_stamp_idx" ON "build" ("stamp");
CREATE INDEX "build_type_idx" ON "build" ("type");
CREATE INDEX "project_name_idx" ON "project" ("name");
CREATE INDEX "site_name_idx" ON "site" ("name");
ALTER TABLE "image" ALTER COLUMN "id" TYPE BIGINT;
ALTER TABLE "image" ALTER COLUMN "id" SET NOT NULL;
DROP INDEX "image_id_idx";
ALTER TABLE "image" ADD PRIMARY KEY ("id");
ALTER TABLE "image" ALTER COLUMN "id" TYPE BIGINT;
ALTER TABLE "image" ALTER COLUMN "id" SET NOT NULL;
CREATE SEQUENCE "image_id_seq";
ALTER TABLE "image" ALTER COLUMN "id" SET DEFAULT nextval('image_id_seq');
ALTER SEQUENCE "image_id_seq" OWNED BY "image"."id";
ALTER TABLE "dailyupdate" ALTER COLUMN "id" TYPE BIGINT;
ALTER TABLE "dailyupdate" ALTER COLUMN "id" SET NOT NULL;
DROP INDEX "dailyupdate_buildid_idx";
ALTER TABLE "dailyupdate" ADD PRIMARY KEY ("id");
ALTER TABLE "dailyupdate" ALTER COLUMN "id" TYPE BIGINT;
ALTER TABLE "dailyupdate" ALTER COLUMN "id" SET NOT NULL;
CREATE SEQUENCE "dailyupdate_id_seq";
ALTER TABLE "dailyupdate" ALTER COLUMN "id" SET DEFAULT nextval('dailyupdate_id_seq');
ALTER SEQUENCE "dailyupdate_id_seq" OWNED BY "dailyupdate"."id";
ALTER TABLE "dynamicanalysisdefect" ALTER COLUMN "value" TYPE INT;
ALTER TABLE "dynamicanalysisdefect" ALTER COLUMN "value" SET NOT NULL;
ALTER TABLE "dynamicanalysisdefect" ALTER COLUMN "value" SET DEFAULT 0;
ALTER TABLE "test2image" DROP CONSTRAINT "value_pkey";
ALTER TABLE "test2image" ALTER COLUMN "imgid" TYPE INT;
ALTER TABLE "test2image" ALTER COLUMN "imgid" SET NOT NULL;
CREATE SEQUENCE "test2image_imgid_seq";
ALTER TABLE "test2image" ALTER COLUMN "imgid" SET DEFAULT nextval('test2image_imgid_seq');
ALTER SEQUENCE "test2image_imgid_seq" OWNED BY "test2image"."imgid";
CREATE INDEX "test2image_testid_idx" ON "test2image" ("testid");
ALTER TABLE "image" ALTER COLUMN "checksum" TYPE BIGINT;
ALTER TABLE "image" ALTER COLUMN "checksum" SET NOT NULL;
ALTER TABLE "note" ALTER COLUMN "crc32" TYPE BIGINT;
ALTER TABLE "note" ALTER COLUMN "crc32" SET NOT NULL;
ALTER TABLE "test" ALTER COLUMN "crc32" TYPE BIGINT;
ALTER TABLE "test" ALTER COLUMN "crc32" SET NOT NULL;
ALTER TABLE "coveragefile" ALTER COLUMN "crc32" TYPE BIGINT;
ALTER TABLE "coveragefile" ALTER COLUMN "crc32" SET NOT NULL;
