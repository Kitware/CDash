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
CREATE INDEX "coveragefile2user_coveragefileid_idx" on "coveragefile2user" ("fileid");
CREATE INDEX "coveragefile2user_userid_idx" on "coveragefile2user" ("userid");

--
-- Table: label
--
CREATE TABLE "label" (
  "id" bigserial NOT NULL,
  "text" character varying(255) NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "label_text_idx" on "label" ("text");

--
-- Table: label2build
--
CREATE TABLE "label2build" (
  "labelid" bigint NOT NULL,
  "buildid" bigint NOT NULL
);
CREATE INDEX "label2build_buildid_idx" on "label2build" ("labelid", "buildid");

--
-- Table: label2configure
--
CREATE TABLE "label2configure" (
  "labelid" bigint NOT NULL,
  "configureid" bigint NOT NULL
);
CREATE INDEX "label2configure_configureid_idx" on "label2configure" ("labelid", "configureid");

--
-- Table: label2coverage
--
CREATE TABLE "label2coverage" (
  "labelid" bigint NOT NULL,
  "coverageid" bigint NOT NULL
);
CREATE INDEX "label2coverage_labelid_idx" on "label2coverage" ("labelid", "coverageid");

--
-- Table: label2dynamicanalysis
--
CREATE TABLE "label2dynamicanalysis" (
  "labelid" bigint NOT NULL,
  "dynamicanalysisid" bigint NOT NULL
);
CREATE INDEX "label2dynamicanalysis_dynamicanalysisid_idx" on "label2dynamicanalysis" ("labelid", "dynamicanalysisid");

--
-- Table: label2test
--
CREATE TABLE "label2test" (
  "labelid" bigint NOT NULL,
  "testid" bigint NOT NULL
);
CREATE INDEX "label2test_labelid_idx" on "label2test" ("labelid", "testid");

--
-- Table: label2update
--
CREATE TABLE "label2update" (
  "labelid" bigint NOT NULL,
  "updateid" bigint NOT NULL
);
CREATE INDEX "label2update_labelid_idx" on "label2update" ("labelid", "updateid");

--
-- Table: subproject
--
CREATE TABLE "subproject" (
  "id" bigint NOT NULL,
  "name" character varying(255) NOT NULL,
  "projectid" bigint NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "subproject_projectid_idx" on "subproject" ("projectid");

--
-- Table: subproject2subproject
--
CREATE TABLE "subproject2subproject" (
  "subprojectid" bigint NOT NULL,
  "dependsonid" bigint NOT NULL
);
CREATE INDEX "subproject2subproject_subprojectid_idx" on "subproject2subproject" ("subprojectid");
CREATE INDEX "subproject2subproject_dependsonid_idx" on "subproject2subproject" ("dependsonid");

--
-- Table: subproject2build
--
CREATE TABLE "subproject2build" (
  "subprojectid" bigint NOT NULL,
  "buildid" bigint NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "subproject2build_subprojectid_idx" on "subproject2build" ("subprojectid");

