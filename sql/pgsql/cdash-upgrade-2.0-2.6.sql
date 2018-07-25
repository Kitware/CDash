CREATE TABLE "build2configure" (
  "configureid" integer DEFAULT '0' NOT NULL,
  "buildid" integer DEFAULT '0' NOT NULL,
  "starttime" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "endtime" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("buildid")
);
CREATE INDEX "configureid" on "build2configure" ("configureid");

CREATE TABLE "authtoken" (
  "hash" character varying(128) NOT NULL,
  "userid" integer DEFAULT '0' NOT NULL ,
  "created" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
  "expires" timestamp(0) DEFAULT '1980-01-01 00:00:00' NOT NULL,
  "description" character varying(255)
);
CREATE INDEX "authtokenhash" on "authtoken" ("hash");
CREATE INDEX "authtokenuserid" on "authtoken" ("userid");
CREATE INDEX "authtokenexpires" on "authtoken" ("expires");

CREATE TABLE "buildproperties" (
  "buildid" integer DEFAULT '0' NOT NULL,
  "properties" text DEFAULT '' NOT NULL,
  PRIMARY KEY ("buildid")
);

CREATE TABLE "related_builds" (
  "buildid" integer DEFAULT '0' NOT NULL,
  "relatedid" integer DEFAULT '0' NOT NULL,
  "relationship" character varying(255),
  PRIMARY KEY ("buildid", "relatedid")
);
CREATE INDEX "related_buildid" on "related_builds" ("buildid");
CREATE INDEX "relatedid" on "related_builds" ("relatedid");
