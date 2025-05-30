--
-- PostgreSQL database dump
--

-- Dumped from database version 17.2 (Debian 17.2-1.pgdg120+1)
-- Dumped by pg_dump version 17.2 (Debian 17.2-1.pgdg120+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
root@c25034cfa33e:/# ls /var/lib/postgresql/data
base  global  pg_commit_ts  pg_dynshmem  pg_hba.conf  pg_ident.conf  pg_logical  pg_multixact  pg_notify  pg_replslot  pg_serial  pg_snapshots  pg_stat  pg_stat_tmp  pg_subtrans  pg_tblspc  pg_twophase  PG_VERSION  pg_wal  pg_xact  postgresql.auto.conf  postgresql.conf  postmaster.opts  postmaster.pid
root@c25034cfa33e:/# cp pgsql-schema.sql /var/lib/postgresql/data/pgsql-schema.sql
root@c25034cfa33e:/# ls /var/lib/postgresql/data
base  global  pg_commit_ts  pg_dynshmem  pg_hba.conf  pg_ident.conf  pg_logical  pg_multixact  pg_notify  pg_replslot  pg_serial  pg_snapshots  pgsql-schema.sql  pg_stat  pg_stat_tmp  pg_subtrans  pg_tblspc  pg_twophase  PG_VERSION  pg_wal  pg_xact  postgresql.auto.conf  postgresql.conf  postmaster.opts  postmaster.pid
root@c25034cfa33e:/# cat pgsql-schema.sql
--
-- PostgreSQL database dump
--

-- Dumped from database version 17.2 (Debian 17.2-1.pgdg120+1)
-- Dumped by pg_dump version 17.2 (Debian 17.2-1.pgdg120+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: apitoken; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.apitoken (
                               projectid integer NOT NULL,
                               token character varying(40),
                               expiration_date timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL
);


--
-- Name: authtoken; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.authtoken (
                                hash character varying(128) NOT NULL,
                                userid integer DEFAULT 0 NOT NULL,
                                created timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
                                expires timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                description character varying(255),
                                projectid integer,
                                scope character varying(255) DEFAULT 'full_access'::character varying NOT NULL
);


--
-- Name: blockbuild; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.blockbuild (
                                 id integer NOT NULL,
                                 projectid integer NOT NULL,
                                 buildname character varying(255) NOT NULL,
                                 sitename character varying(255) NOT NULL,
                                 ipaddress character varying(50) NOT NULL
);


--
-- Name: blockbuild_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.blockbuild_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: blockbuild_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.blockbuild_id_seq OWNED BY public.blockbuild.id;


--
-- Name: build; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build (
                            id integer NOT NULL,
                            siteid integer DEFAULT 0 NOT NULL,
                            projectid integer DEFAULT 0 NOT NULL,
                            parentid integer DEFAULT 0 NOT NULL,
                            stamp character varying(255) DEFAULT ''::character varying NOT NULL,
                            name character varying(255) DEFAULT ''::character varying NOT NULL,
                            type character varying(255) DEFAULT ''::character varying NOT NULL,
                            generator character varying(255) DEFAULT ''::character varying NOT NULL,
                            starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                            endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                            submittime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                            command text DEFAULT ''::text NOT NULL,
                            configureerrors smallint DEFAULT '-1'::smallint,
                            configurewarnings smallint DEFAULT '-1'::smallint,
                            configureduration integer DEFAULT 0 NOT NULL,
                            builderrors smallint DEFAULT '-1'::smallint,
                            buildwarnings smallint DEFAULT '-1'::smallint,
                            buildduration integer DEFAULT 0 NOT NULL,
                            testnotrun smallint DEFAULT '-1'::smallint,
                            testfailed smallint DEFAULT '-1'::smallint,
                            testpassed smallint DEFAULT '-1'::smallint,
                            testtimestatusfailed smallint DEFAULT '-1'::smallint,
                            testduration integer DEFAULT 0 NOT NULL,
                            notified smallint DEFAULT '0'::smallint,
                            done smallint DEFAULT '0'::smallint,
                            uuid character varying(36) NOT NULL,
                            changeid character varying(40) DEFAULT ''::character varying,
                            osname character varying(255),
                            osplatform character varying(255),
                            osrelease character varying(255),
                            osversion character varying(255),
                            compilername character varying(255),
                            compilerversion character varying(255)
);


--
-- Name: build2configure; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build2configure (
                                      configureid integer DEFAULT 0 NOT NULL,
                                      buildid integer DEFAULT 0 NOT NULL,
                                      starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                      endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL
);


--
-- Name: build2group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build2group (
                                  groupid integer DEFAULT 0 NOT NULL,
                                  buildid integer DEFAULT 0 NOT NULL
);


--
-- Name: build2grouprule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build2grouprule (
                                      groupid integer DEFAULT 0 NOT NULL,
                                      parentgroupid integer DEFAULT 0 NOT NULL,
                                      buildtype character varying(20) DEFAULT ''::character varying NOT NULL,
                                      buildname character varying(255) DEFAULT ''::character varying NOT NULL,
                                      siteid integer DEFAULT 0 NOT NULL,
                                      expected smallint DEFAULT '0'::smallint NOT NULL,
                                      starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                      endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL
);


--
-- Name: build2note; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build2note (
                                 buildid integer NOT NULL,
                                 noteid bigint NOT NULL,
                                 "time" timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL
);


--
-- Name: build2test; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build2test (
                                 buildid integer DEFAULT 0 NOT NULL,
                                 outputid integer DEFAULT 0 NOT NULL,
                                 status character varying(10) DEFAULT ''::character varying NOT NULL,
                                 "time" numeric(7,2) DEFAULT '0'::numeric NOT NULL,
                                 timemean numeric(7,2) DEFAULT '0'::numeric NOT NULL,
                                 timestd numeric(7,2) DEFAULT '0'::numeric NOT NULL,
                                 timestatus smallint DEFAULT '0'::smallint NOT NULL,
                                 newstatus smallint DEFAULT '0'::smallint NOT NULL,
                                 id bigint NOT NULL,
                                 details character varying(255) DEFAULT ''::character varying NOT NULL,
                                 testname character varying(255) NOT NULL
);


--
-- Name: build2test_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.build2test_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: build2test_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.build2test_id_seq OWNED BY public.build2test.id;


--
-- Name: build2update; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build2update (
                                   buildid integer NOT NULL,
                                   updateid bigint NOT NULL
);


--
-- Name: build2uploadfile; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build2uploadfile (
                                       fileid integer NOT NULL,
                                       buildid integer NOT NULL
);


--
-- Name: build_filters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.build_filters (
                                    projectid integer NOT NULL,
                                    warnings text,
                                    errors text
);


--
-- Name: build_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.build_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: build_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.build_id_seq OWNED BY public.build.id;


--
-- Name: buildcommandoutputs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildcommandoutputs (
                                          id bigint NOT NULL,
                                          buildcommandid bigint,
                                          size bigint NOT NULL,
                                          name text NOT NULL
);


--
-- Name: buildcommandoutputs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildcommandoutputs_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildcommandoutputs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildcommandoutputs_id_seq OWNED BY public.buildcommandoutputs.id;


--
-- Name: buildcommands; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildcommands (
                                    id bigint NOT NULL,
                                    buildid integer NOT NULL,
                                    targetid bigint,
                                    type character varying(255) NOT NULL,
                                    starttime timestamp(0) with time zone NOT NULL,
                                    duration integer NOT NULL,
                                    command text NOT NULL,
                                    workingdirectory text NOT NULL,
                                    result text NOT NULL,
                                    source text,
                                    language character varying(32),
                                    config character varying(32),
                                    CONSTRAINT buildcommands_type_check CHECK (((type)::text = ANY ((ARRAY['COMPILE'::character varying, 'LINK'::character varying, 'CUSTOM'::character varying, 'CMAKE_BUILD'::character varying, 'CMAKE_INSTALL'::character varying, 'INSTALL'::character varying])::text[])))
);


--
-- Name: buildcommands_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildcommands_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildcommands_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildcommands_id_seq OWNED BY public.buildcommands.id;


--
-- Name: buildemail; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildemail (
                                 userid integer NOT NULL,
                                 buildid integer NOT NULL,
                                 category smallint NOT NULL,
                                 "time" timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL
);


--
-- Name: builderror; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.builderror (
                                 buildid integer DEFAULT 0 NOT NULL,
                                 type smallint DEFAULT '0'::smallint NOT NULL,
                                 logline integer DEFAULT 0 NOT NULL,
                                 text text NOT NULL,
                                 sourcefile character varying(255) DEFAULT ''::character varying NOT NULL,
                                 sourceline integer DEFAULT 0 NOT NULL,
                                 precontext text,
                                 postcontext text,
                                 repeatcount integer DEFAULT 0 NOT NULL,
                                 crc32 bigint DEFAULT '0'::bigint NOT NULL,
                                 newstatus smallint DEFAULT '0'::smallint NOT NULL,
                                 id integer NOT NULL
);


--
-- Name: builderror_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.builderror_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: builderror_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.builderror_id_seq OWNED BY public.builderror.id;


--
-- Name: builderrordiff; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.builderrordiff (
                                     buildid integer NOT NULL,
                                     type smallint NOT NULL,
                                     difference_positive integer NOT NULL,
                                     difference_negative integer NOT NULL
);


--
-- Name: buildfailure; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildfailure (
                                   id bigint NOT NULL,
                                   buildid integer NOT NULL,
                                   detailsid bigint NOT NULL,
                                   workingdirectory character varying(512) NOT NULL,
                                   sourcefile character varying(512) NOT NULL,
                                   newstatus smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: buildfailure2argument; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildfailure2argument (
                                            buildfailureid bigint NOT NULL,
                                            argumentid bigint NOT NULL,
                                            place integer DEFAULT 0 NOT NULL
);


--
-- Name: buildfailure_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildfailure_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildfailure_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildfailure_id_seq OWNED BY public.buildfailure.id;


--
-- Name: buildfailureargument; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildfailureargument (
                                           id bigint NOT NULL,
                                           argument character varying(255) NOT NULL
);


--
-- Name: buildfailureargument_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildfailureargument_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildfailureargument_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildfailureargument_id_seq OWNED BY public.buildfailureargument.id;


--
-- Name: buildfailuredetails; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildfailuredetails (
                                          id bigint NOT NULL,
                                          type smallint NOT NULL,
                                          stdoutput text NOT NULL,
                                          stderror text NOT NULL,
                                          exitcondition character varying(255) NOT NULL,
                                          language character varying(64) NOT NULL,
                                          targetname character varying(255) NOT NULL,
                                          outputfile character varying(512) NOT NULL,
                                          outputtype character varying(255) NOT NULL,
                                          crc32 bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: buildfailuredetails_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildfailuredetails_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildfailuredetails_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildfailuredetails_id_seq OWNED BY public.buildfailuredetails.id;


--
-- Name: buildfile; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildfile (
                                buildid integer NOT NULL,
                                filename character varying(255) NOT NULL,
                                md5 character varying(40) NOT NULL,
                                type character varying(32) DEFAULT ''::character varying NOT NULL,
                                id integer NOT NULL
);


--
-- Name: buildfile_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildfile_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildfile_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildfile_id_seq OWNED BY public.buildfile.id;


--
-- Name: buildgroup; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildgroup (
                                 id integer NOT NULL,
                                 name character varying(255) DEFAULT ''::character varying NOT NULL,
                                 projectid integer DEFAULT 0 NOT NULL,
                                 starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                 endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                 autoremovetimeframe integer DEFAULT 0,
                                 description text,
                                 summaryemail smallint DEFAULT '0'::smallint,
                                 includesubprojectotal smallint DEFAULT '1'::smallint,
                                 emailcommitters smallint DEFAULT '0'::smallint,
                                 type character varying(20) DEFAULT 'Daily'::character varying NOT NULL
);


--
-- Name: buildgroup_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildgroup_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildgroup_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildgroup_id_seq OWNED BY public.buildgroup.id;


--
-- Name: buildgroupposition; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildgroupposition (
                                         buildgroupid integer DEFAULT 0 NOT NULL,
                                         "position" integer DEFAULT 0 NOT NULL,
                                         starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                         endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                         id integer NOT NULL
);


--
-- Name: buildgroupposition_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildgroupposition_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildgroupposition_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildgroupposition_id_seq OWNED BY public.buildgroupposition.id;


--
-- Name: buildmeasurements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildmeasurements (
                                        id bigint NOT NULL,
                                        buildcommandid bigint NOT NULL,
                                        type character varying(512) NOT NULL,
                                        name character varying(512) NOT NULL,
                                        value character varying(512) NOT NULL
);


--
-- Name: buildmeasurements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildmeasurements_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildmeasurements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildmeasurements_id_seq OWNED BY public.buildmeasurements.id;


--
-- Name: buildproperties; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildproperties (
                                      buildid integer DEFAULT 0 NOT NULL,
                                      properties text NOT NULL
);


--
-- Name: buildtesttime; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildtesttime (
                                    buildid integer DEFAULT 0 NOT NULL,
                                    "time" numeric(7,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: buildupdate; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildupdate (
                                  id integer NOT NULL,
                                  starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                  endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                  command text NOT NULL,
                                  type character varying(4) DEFAULT ''::character varying NOT NULL,
                                  status text NOT NULL,
                                  nfiles smallint DEFAULT '-1'::smallint,
                                  warnings smallint DEFAULT '-1'::smallint,
                                  revision character varying(60) DEFAULT '0'::character varying NOT NULL,
                                  priorrevision character varying(60) DEFAULT '0'::character varying NOT NULL,
                                  path character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: buildupdate_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildupdate_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: buildupdate_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildupdate_id_seq OWNED BY public.buildupdate.id;


--
-- Name: comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.comments (
                               buildid integer NOT NULL,
                               userid integer NOT NULL,
                               text text NOT NULL,
                               "timestamp" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
                               status smallint DEFAULT '0'::smallint NOT NULL,
                               id integer NOT NULL
);


--
-- Name: comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.comments_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.comments_id_seq OWNED BY public.comments.id;


--
-- Name: configure; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configure (
                                id integer NOT NULL,
                                command text NOT NULL,
                                log text NOT NULL,
                                status smallint DEFAULT '0'::smallint NOT NULL,
                                warnings smallint DEFAULT '-1'::smallint,
                                crc32 bigint NOT NULL
);


--
-- Name: configure_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configure_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: configure_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configure_id_seq OWNED BY public.configure.id;


--
-- Name: configureerror; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configureerror (
                                     configureid integer NOT NULL,
                                     type smallint,
                                     text text
);


--
-- Name: configureerrordiff; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configureerrordiff (
                                         buildid integer NOT NULL,
                                         type smallint,
                                         difference integer
);


--
-- Name: coverage; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.coverage (
                               buildid integer DEFAULT 0 NOT NULL,
                               fileid integer DEFAULT 0 NOT NULL,
                               covered smallint DEFAULT '0'::smallint NOT NULL,
                               loctested integer DEFAULT 0 NOT NULL,
                               locuntested integer DEFAULT 0 NOT NULL,
                               branchestested integer DEFAULT 0 NOT NULL,
                               branchesuntested integer DEFAULT 0 NOT NULL,
                               functionstested integer DEFAULT 0 NOT NULL,
                               functionsuntested integer DEFAULT 0 NOT NULL,
                               id integer NOT NULL
);


--
-- Name: coverage_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.coverage_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: coverage_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.coverage_id_seq OWNED BY public.coverage.id;


--
-- Name: coveragefile; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.coveragefile (
                                   id integer NOT NULL,
                                   fullpath character varying(255) DEFAULT ''::character varying NOT NULL,
                                   file bytea,
                                   crc32 bigint
);


--
-- Name: coveragefile_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.coveragefile_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: coveragefile_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.coveragefile_id_seq OWNED BY public.coveragefile.id;


--
-- Name: coveragefilelog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.coveragefilelog (
                                      buildid integer DEFAULT 0 NOT NULL,
                                      fileid integer DEFAULT 0 NOT NULL,
                                      log bytea NOT NULL
);


--
-- Name: coveragesummary; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.coveragesummary (
                                      buildid integer DEFAULT 0 NOT NULL,
                                      loctested integer DEFAULT 0 NOT NULL,
                                      locuntested integer DEFAULT 0 NOT NULL,
                                      loctesteddiff integer DEFAULT 0 NOT NULL,
                                      locuntesteddiff integer DEFAULT 0 NOT NULL
);


--
-- Name: dailyupdate; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dailyupdate (
                                  id bigint NOT NULL,
                                  projectid integer NOT NULL,
                                  date date NOT NULL,
                                  command text NOT NULL,
                                  type character varying(4) DEFAULT ''::character varying NOT NULL,
                                  status smallint DEFAULT '0'::smallint NOT NULL,
                                  revision character varying(60) DEFAULT '0'::character varying NOT NULL
);


--
-- Name: dailyupdate_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dailyupdate_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: dailyupdate_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dailyupdate_id_seq OWNED BY public.dailyupdate.id;


--
-- Name: dynamicanalysis; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dynamicanalysis (
                                      id integer NOT NULL,
                                      buildid integer DEFAULT 0 NOT NULL,
                                      status character varying(10) DEFAULT ''::character varying NOT NULL,
                                      checker character varying(60) DEFAULT ''::character varying NOT NULL,
                                      name character varying(255) DEFAULT ''::character varying NOT NULL,
                                      path character varying(255) DEFAULT ''::character varying NOT NULL,
                                      fullcommandline character varying(255) DEFAULT ''::character varying NOT NULL,
                                      log text NOT NULL
);


--
-- Name: dynamicanalysis_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dynamicanalysis_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: dynamicanalysis_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dynamicanalysis_id_seq OWNED BY public.dynamicanalysis.id;


--
-- Name: dynamicanalysisdefect; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dynamicanalysisdefect (
                                            dynamicanalysisid integer DEFAULT 0 NOT NULL,
                                            type character varying(255) DEFAULT ''::character varying NOT NULL,
                                            value integer DEFAULT 0 NOT NULL
);


--
-- Name: dynamicanalysissummary; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dynamicanalysissummary (
                                             buildid integer DEFAULT 0 NOT NULL,
                                             checker character varying(60) DEFAULT ''::character varying NOT NULL,
                                             numdefects integer DEFAULT 0 NOT NULL
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
                                  id bigint NOT NULL,
                                  connection text NOT NULL,
                                  queue text NOT NULL,
                                  payload text NOT NULL,
                                  exception text NOT NULL,
                                  failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: global_invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.global_invitations (
                                         id bigint NOT NULL,
                                         email character varying(255) NOT NULL,
                                         invited_by_id integer NOT NULL,
                                         role character varying(255) NOT NULL,
                                         invitation_timestamp timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
                                         password text NOT NULL,
                                         CONSTRAINT global_invitations_role_check CHECK (((role)::text = ANY ((ARRAY['USER'::character varying, 'ADMINISTRATOR'::character varying])::text[])))
);


--
-- Name: global_invitations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.global_invitations_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: global_invitations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.global_invitations_id_seq OWNED BY public.global_invitations.id;


--
-- Name: image; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.image (
                            id integer NOT NULL,
                            img bytea NOT NULL,
                            extension text NOT NULL,
                            checksum bigint NOT NULL
);


--
-- Name: image_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.image_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: image_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.image_id_seq OWNED BY public.image.id;


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
                           id bigint NOT NULL,
                           queue character varying(255) NOT NULL,
                           payload text NOT NULL,
                           attempts smallint NOT NULL,
                           reserved_at integer,
                           available_at integer NOT NULL,
                           created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: label; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label (
                            id bigint NOT NULL,
                            text character varying(255) NOT NULL
);


--
-- Name: label2build; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label2build (
                                  labelid bigint NOT NULL,
                                  buildid integer NOT NULL
);


--
-- Name: label2buildfailure; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label2buildfailure (
                                         labelid bigint NOT NULL,
                                         buildfailureid bigint NOT NULL
);


--
-- Name: label2coveragefile; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label2coveragefile (
                                         labelid bigint NOT NULL,
                                         buildid integer NOT NULL,
                                         coveragefileid integer NOT NULL
);


--
-- Name: label2dynamicanalysis; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label2dynamicanalysis (
                                            labelid bigint NOT NULL,
                                            dynamicanalysisid integer NOT NULL
);


--
-- Name: label2target; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label2target (
                                   targetid bigint NOT NULL,
                                   labelid bigint NOT NULL
);


--
-- Name: label2test; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label2test (
                                 labelid bigint NOT NULL,
                                 testid bigint NOT NULL
);


--
-- Name: label2update; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label2update (
                                   labelid bigint NOT NULL,
                                   updateid integer NOT NULL
);


--
-- Name: label_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.label_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: label_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.label_id_seq OWNED BY public.label.id;


--
-- Name: labelemail; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.labelemail (
                                 projectid integer NOT NULL,
                                 userid integer NOT NULL,
                                 labelid bigint NOT NULL
);


--
-- Name: lockout; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lockout (
                              userid integer NOT NULL,
                              failedattempts smallint DEFAULT '0'::smallint,
                              islocked smallint DEFAULT '0'::smallint NOT NULL,
                              unlocktime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL
);


--
-- Name: measurement; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.measurement (
                                  id integer NOT NULL,
                                  projectid integer NOT NULL,
                                  name character varying(255) NOT NULL,
                                  "position" smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: measurement_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.measurement_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: measurement_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.measurement_id_seq OWNED BY public.measurement.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
                                 id integer NOT NULL,
                                 migration character varying(255) NOT NULL,
                                 batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: note; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.note (
                           id bigint NOT NULL,
                           text text NOT NULL,
                           name character varying(255) NOT NULL,
                           crc32 bigint NOT NULL
);


--
-- Name: note_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.note_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: note_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.note_id_seq OWNED BY public.note.id;


--
-- Name: overview_components; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.overview_components (
                                          projectid integer DEFAULT 1 NOT NULL,
                                          buildgroupid integer DEFAULT 0 NOT NULL,
                                          "position" integer DEFAULT 0 NOT NULL,
                                          type character varying(32) DEFAULT 'build'::character varying NOT NULL
);


--
-- Name: password_resets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_resets (
                                      email character varying(255) NOT NULL,
                                      token character varying(255) NOT NULL,
                                      created_at timestamp(0) without time zone
);


--
-- Name: pending_submissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pending_submissions (
                                          buildid integer NOT NULL,
                                          numfiles smallint DEFAULT '0'::smallint NOT NULL,
                                          recheck smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: project; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.project (
                              id integer NOT NULL,
                              name character varying(255) DEFAULT ''::character varying NOT NULL,
                              description text DEFAULT ''::text NOT NULL,
                              homeurl character varying(255) DEFAULT ''::character varying NOT NULL,
                              cvsurl character varying(255) DEFAULT ''::character varying NOT NULL,
                              bugtrackerurl character varying(255) DEFAULT ''::character varying NOT NULL,
                              bugtrackernewissueurl character varying(255) DEFAULT ''::character varying NOT NULL,
                              bugtrackertype character varying(16),
                              documentationurl character varying(255) DEFAULT ''::character varying NOT NULL,
                              imageid integer DEFAULT 0 NOT NULL,
                              public smallint DEFAULT '1'::smallint NOT NULL,
                              coveragethreshold smallint DEFAULT '70'::smallint NOT NULL,
                              testingdataurl character varying(255) DEFAULT ''::character varying NOT NULL,
                              nightlytime character varying(50) DEFAULT '00:00:00'::character varying NOT NULL,
                              emaillowcoverage smallint DEFAULT '0'::smallint NOT NULL,
                              emailtesttimingchanged smallint DEFAULT '0'::smallint NOT NULL,
                              emailbrokensubmission smallint DEFAULT '1'::smallint NOT NULL,
                              emailredundantfailures smallint DEFAULT '0'::smallint NOT NULL,
                              showipaddresses smallint DEFAULT '1'::smallint NOT NULL,
                              cvsviewertype character varying(10),
                              testtimestd numeric(3,1) DEFAULT '4'::numeric,
                              testtimestdthreshold numeric(3,1) DEFAULT '1'::numeric,
                              showtesttime smallint DEFAULT '0'::smallint,
                              testtimemaxstatus smallint DEFAULT '3'::smallint,
                              emailmaxitems smallint DEFAULT '5'::smallint,
                              emailmaxchars integer DEFAULT 255,
                              displaylabels smallint DEFAULT '1'::smallint,
                              autoremovetimeframe integer DEFAULT 0,
                              autoremovemaxbuilds integer DEFAULT 300,
                              uploadquota bigint DEFAULT '0'::bigint,
                              showcoveragecode smallint DEFAULT '1'::smallint,
                              sharelabelfilters smallint DEFAULT '0'::smallint,
                              authenticatesubmissions smallint DEFAULT '0'::smallint,
                              viewsubprojectslink smallint DEFAULT '1'::smallint NOT NULL,
                              ldapfilter character varying(255),
                              banner character varying(255)
);


--
-- Name: project2repositories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.project2repositories (
                                           projectid integer NOT NULL,
                                           repositoryid integer NOT NULL
);


--
-- Name: project_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.project_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: project_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.project_id_seq OWNED BY public.project.id;


--
-- Name: project_invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.project_invitations (
                                          id bigint NOT NULL,
                                          email character varying(255) NOT NULL,
                                          invited_by_id integer NOT NULL,
                                          project_id integer NOT NULL,
                                          role character varying(255) NOT NULL,
                                          invitation_timestamp timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
                                          CONSTRAINT project_invitations_role_check CHECK (((role)::text = ANY ((ARRAY['USER'::character varying, 'ADMINISTRATOR'::character varying])::text[])))
);


--
-- Name: project_invitations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.project_invitations_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: project_invitations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.project_invitations_id_seq OWNED BY public.project_invitations.id;


--
-- Name: related_builds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.related_builds (
                                     buildid integer NOT NULL,
                                     relatedid integer NOT NULL,
                                     relationship character varying(255)
);


--
-- Name: repositories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.repositories (
                                   id integer NOT NULL,
                                   url character varying(255) NOT NULL,
                                   username character varying(50) DEFAULT ''::character varying NOT NULL,
                                   password character varying(50) DEFAULT ''::character varying NOT NULL,
                                   branch character varying(60) DEFAULT ''::character varying NOT NULL
);


--
-- Name: repositories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.repositories_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: repositories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.repositories_id_seq OWNED BY public.repositories.id;


--
-- Name: saml2_tenants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.saml2_tenants (
                                    id integer NOT NULL,
                                    uuid uuid NOT NULL,
                                    key character varying(255),
                                    idp_entity_id character varying(255) NOT NULL,
                                    idp_login_url character varying(255) NOT NULL,
                                    idp_logout_url character varying(255) NOT NULL,
                                    idp_x509_cert text NOT NULL,
                                    metadata json NOT NULL,
                                    created_at timestamp(0) without time zone,
                                    updated_at timestamp(0) without time zone,
                                    deleted_at timestamp(0) without time zone,
                                    relay_state_url character varying(255),
                                    name_id_format character varying(255) DEFAULT 'persistent'::character varying NOT NULL
);


--
-- Name: saml2_tenants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.saml2_tenants_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: saml2_tenants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.saml2_tenants_id_seq OWNED BY public.saml2_tenants.id;


--
-- Name: site; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.site (
                           id integer NOT NULL,
                           name character varying(255) DEFAULT ''::character varying NOT NULL,
                           ip character varying(255) DEFAULT ''::character varying NOT NULL,
                           latitude character varying(10) DEFAULT ''::character varying NOT NULL,
                           longitude character varying(10) DEFAULT ''::character varying NOT NULL,
                           outoforder smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: site2user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.site2user (
                                siteid integer DEFAULT 0 NOT NULL,
                                userid integer DEFAULT 0 NOT NULL
);


--
-- Name: site_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.site_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.site_id_seq OWNED BY public.site.id;


--
-- Name: siteinformation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.siteinformation (
                                      siteid integer NOT NULL,
                                      "timestamp" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
                                      processoris64bits smallint,
                                      processorvendor character varying(255),
                                      processorvendorid character varying(255),
                                      processorfamilyid integer,
                                      processormodelid integer,
                                      processorcachesize integer,
                                      numberlogicalcpus integer,
                                      numberphysicalcpus integer,
                                      totalvirtualmemory integer,
                                      totalphysicalmemory integer,
                                      logicalprocessorsperphysical integer,
                                      processorclockfrequency integer,
                                      description character varying(255),
                                      id integer NOT NULL,
                                      processormodelname text
);


--
-- Name: siteinformation_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.siteinformation_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: siteinformation_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.siteinformation_id_seq OWNED BY public.siteinformation.id;


--
-- Name: subproject; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subproject (
                                 id bigint NOT NULL,
                                 name character varying(255) NOT NULL,
                                 projectid integer NOT NULL,
                                 groupid integer NOT NULL,
                                 path character varying(512) DEFAULT ''::character varying NOT NULL,
                                 "position" smallint DEFAULT '0'::smallint NOT NULL,
                                 starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                 endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL
);


--
-- Name: subproject2build; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subproject2build (
                                       subprojectid integer NOT NULL,
                                       buildid integer NOT NULL
);


--
-- Name: subproject2subproject; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subproject2subproject (
                                            subprojectid bigint NOT NULL,
                                            dependsonid bigint NOT NULL,
                                            starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                            endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL
);


--
-- Name: subproject_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subproject_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: subproject_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subproject_id_seq OWNED BY public.subproject.id;


--
-- Name: subprojectgroup; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subprojectgroup (
                                      id integer NOT NULL,
                                      name character varying(255) NOT NULL,
                                      projectid integer NOT NULL,
                                      coveragethreshold smallint DEFAULT '70'::smallint NOT NULL,
                                      is_default smallint NOT NULL,
                                      starttime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                      endtime timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                      "position" integer DEFAULT 0 NOT NULL
);


--
-- Name: subprojectgroup_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subprojectgroup_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: subprojectgroup_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subprojectgroup_id_seq OWNED BY public.subprojectgroup.id;


--
-- Name: successful_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.successful_jobs (
                                      id bigint NOT NULL,
                                      filename text NOT NULL,
                                      finished_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: successful_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.successful_jobs_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: successful_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.successful_jobs_id_seq OWNED BY public.successful_jobs.id;


--
-- Name: summaryemail; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.summaryemail (
                                   buildid integer NOT NULL,
                                   date date NOT NULL,
                                   groupid smallint NOT NULL
);


--
-- Name: targets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.targets (
                              id bigint NOT NULL,
                              buildid integer NOT NULL,
                              type character varying(255) NOT NULL,
                              name character varying(255) NOT NULL,
                              CONSTRAINT targets_type_check CHECK (((type)::text = ANY ((ARRAY['UNKNOWN'::character varying, 'STATIC_LIBRARY'::character varying, 'MODULE_LIBRARY'::character varying, 'SHARED_LIBRARY'::character varying, 'OBJECT_LIBRARY'::character varying, 'INTERFACE_LIBRARY'::character varying, 'EXECUTABLE'::character varying])::text[])))
);


--
-- Name: targets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.targets_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: targets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.targets_id_seq OWNED BY public.targets.id;


--
-- Name: test2image; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.test2image (
                                 id bigint NOT NULL,
                                 imgid integer NOT NULL,
                                 outputid integer NOT NULL,
                                 role text NOT NULL
);


--
-- Name: test2image_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.test2image_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: test2image_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.test2image_id_seq OWNED BY public.test2image.id;


--
-- Name: testoutput; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.testoutput (
                                 id integer NOT NULL,
                                 crc32 bigint NOT NULL,
                                 path character varying(255) DEFAULT ''::character varying NOT NULL,
                                 command text NOT NULL,
                                 output bytea NOT NULL
);


--
-- Name: test_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.test_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: test_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.test_id_seq OWNED BY public.testoutput.id;


--
-- Name: testdiff; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.testdiff (
                               buildid integer NOT NULL,
                               type smallint NOT NULL,
                               difference_positive integer NOT NULL,
                               difference_negative integer NOT NULL,
                               id integer NOT NULL
);


--
-- Name: testdiff_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.testdiff_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: testdiff_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.testdiff_id_seq OWNED BY public.testdiff.id;


--
-- Name: testmeasurement; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.testmeasurement (
                                      id bigint NOT NULL,
                                      name text NOT NULL,
                                      type character varying(70) NOT NULL,
                                      value text NOT NULL,
                                      testid bigint NOT NULL
);


--
-- Name: COLUMN testmeasurement.value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.testmeasurement.value IS ' ';


--
-- Name: testmeasurement_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.testmeasurement_id_seq
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: testmeasurement_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.testmeasurement_id_seq OWNED BY public.testmeasurement.id;


--
-- Name: updatefile; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.updatefile (
                                 updateid integer DEFAULT 0 NOT NULL,
                                 filename character varying(255) DEFAULT ''::character varying NOT NULL,
                                 checkindate timestamp(0) without time zone DEFAULT '1980-01-01 00:00:00'::timestamp without time zone NOT NULL,
                                 author character varying(255) DEFAULT ''::character varying NOT NULL,
                                 email character varying(255) DEFAULT ''::character varying NOT NULL,
                                 committer character varying(255) DEFAULT ''::character varying NOT NULL,
                                 committeremail character varying(255) DEFAULT ''::character varying NOT NULL,
                                 log text NOT NULL,
                                 revision character varying(60) DEFAULT '0'::character varying NOT NULL,
                                 priorrevision character varying(60) DEFAULT '0'::character varying NOT NULL,
                                 status character varying(12) DEFAULT ''::character varying NOT NULL
);


--
-- Name: uploadfile; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.uploadfile (
                                 id integer NOT NULL,
                                 filename character varying(255) NOT NULL,
                                 filesize integer DEFAULT 0 NOT NULL,
                                 sha1sum character varying(40) NOT NULL,
                                 isurl smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: uploadfile_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.uploadfile_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: uploadfile_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.uploadfile_id_seq OWNED BY public.uploadfile.id;


--
-- Name: user2project; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user2project (
                                   userid integer DEFAULT 0 NOT NULL,
                                   projectid integer DEFAULT 0 NOT NULL,
                                   role integer DEFAULT 0 NOT NULL,
                                   emailtype smallint DEFAULT '0'::smallint NOT NULL,
                                   emailcategory smallint DEFAULT '62'::smallint NOT NULL,
                                   emailsuccess smallint DEFAULT '0'::smallint NOT NULL,
                                   emailmissingsites smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
                            id integer NOT NULL,
                            email character varying(255) DEFAULT ''::character varying NOT NULL,
                            password character varying(255) DEFAULT ''::character varying NOT NULL,
                            firstname character varying(40) DEFAULT ''::character varying NOT NULL,
                            lastname character varying(40) DEFAULT ''::character varying NOT NULL,
                            institution character varying(255) DEFAULT ''::character varying NOT NULL,
                            admin smallint DEFAULT '0'::smallint NOT NULL,
                            email_verified_at timestamp(0) without time zone,
                            remember_token character varying(100),
                            created_at timestamp(0) without time zone,
                            updated_at timestamp(0) without time zone,
                            ldapguid character varying(255),
                            ldapdomain character varying(255),
                            password_updated_at timestamp(0) with time zone
);


--
-- Name: user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_id_seq
  AS integer
  START WITH 1
  INCREMENT BY 1
  NO MINVALUE
  NO MAXVALUE
  CACHE 1;


--
-- Name: user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_id_seq OWNED BY public.users.id;


--
-- Name: usertemp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usertemp (
                               email character varying(255) DEFAULT ''::character varying NOT NULL,
                               password character varying(255) DEFAULT ''::character varying NOT NULL,
                               firstname character varying(40) DEFAULT ''::character varying NOT NULL,
                               lastname character varying(40) DEFAULT ''::character varying NOT NULL,
                               institution character varying(255) DEFAULT ''::character varying NOT NULL,
                               registrationdate timestamp(0) without time zone NOT NULL,
                               registrationkey character varying(40) DEFAULT ''::character varying NOT NULL
);


--
-- Name: blockbuild id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blockbuild ALTER COLUMN id SET DEFAULT nextval('public.blockbuild_id_seq'::regclass);


--
-- Name: build id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build ALTER COLUMN id SET DEFAULT nextval('public.build_id_seq'::regclass);


--
-- Name: build2test id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2test ALTER COLUMN id SET DEFAULT nextval('public.build2test_id_seq'::regclass);


--
-- Name: buildcommandoutputs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildcommandoutputs ALTER COLUMN id SET DEFAULT nextval('public.buildcommandoutputs_id_seq'::regclass);


--
-- Name: buildcommands id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildcommands ALTER COLUMN id SET DEFAULT nextval('public.buildcommands_id_seq'::regclass);


--
-- Name: builderror id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.builderror ALTER COLUMN id SET DEFAULT nextval('public.builderror_id_seq'::regclass);


--
-- Name: buildfailure id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfailure ALTER COLUMN id SET DEFAULT nextval('public.buildfailure_id_seq'::regclass);


--
-- Name: buildfailureargument id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfailureargument ALTER COLUMN id SET DEFAULT nextval('public.buildfailureargument_id_seq'::regclass);


--
-- Name: buildfailuredetails id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfailuredetails ALTER COLUMN id SET DEFAULT nextval('public.buildfailuredetails_id_seq'::regclass);


--
-- Name: buildfile id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfile ALTER COLUMN id SET DEFAULT nextval('public.buildfile_id_seq'::regclass);


--
-- Name: buildgroup id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildgroup ALTER COLUMN id SET DEFAULT nextval('public.buildgroup_id_seq'::regclass);


--
-- Name: buildgroupposition id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildgroupposition ALTER COLUMN id SET DEFAULT nextval('public.buildgroupposition_id_seq'::regclass);


--
-- Name: buildmeasurements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildmeasurements ALTER COLUMN id SET DEFAULT nextval('public.buildmeasurements_id_seq'::regclass);


--
-- Name: buildupdate id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildupdate ALTER COLUMN id SET DEFAULT nextval('public.buildupdate_id_seq'::regclass);


--
-- Name: comments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments ALTER COLUMN id SET DEFAULT nextval('public.comments_id_seq'::regclass);


--
-- Name: configure id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configure ALTER COLUMN id SET DEFAULT nextval('public.configure_id_seq'::regclass);


--
-- Name: coverage id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coverage ALTER COLUMN id SET DEFAULT nextval('public.coverage_id_seq'::regclass);


--
-- Name: coveragefile id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coveragefile ALTER COLUMN id SET DEFAULT nextval('public.coveragefile_id_seq'::regclass);


--
-- Name: dailyupdate id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dailyupdate ALTER COLUMN id SET DEFAULT nextval('public.dailyupdate_id_seq'::regclass);


--
-- Name: dynamicanalysis id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dynamicanalysis ALTER COLUMN id SET DEFAULT nextval('public.dynamicanalysis_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: global_invitations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.global_invitations ALTER COLUMN id SET DEFAULT nextval('public.global_invitations_id_seq'::regclass);


--
-- Name: image id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image ALTER COLUMN id SET DEFAULT nextval('public.image_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: label id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label ALTER COLUMN id SET DEFAULT nextval('public.label_id_seq'::regclass);


--
-- Name: measurement id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement ALTER COLUMN id SET DEFAULT nextval('public.measurement_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: note id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.note ALTER COLUMN id SET DEFAULT nextval('public.note_id_seq'::regclass);


--
-- Name: project id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project ALTER COLUMN id SET DEFAULT nextval('public.project_id_seq'::regclass);


--
-- Name: project_invitations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_invitations ALTER COLUMN id SET DEFAULT nextval('public.project_invitations_id_seq'::regclass);


--
-- Name: repositories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.repositories ALTER COLUMN id SET DEFAULT nextval('public.repositories_id_seq'::regclass);


--
-- Name: saml2_tenants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saml2_tenants ALTER COLUMN id SET DEFAULT nextval('public.saml2_tenants_id_seq'::regclass);


--
-- Name: site id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site ALTER COLUMN id SET DEFAULT nextval('public.site_id_seq'::regclass);


--
-- Name: siteinformation id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.siteinformation ALTER COLUMN id SET DEFAULT nextval('public.siteinformation_id_seq'::regclass);


--
-- Name: subproject id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subproject ALTER COLUMN id SET DEFAULT nextval('public.subproject_id_seq'::regclass);


--
-- Name: subprojectgroup id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subprojectgroup ALTER COLUMN id SET DEFAULT nextval('public.subprojectgroup_id_seq'::regclass);


--
-- Name: successful_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.successful_jobs ALTER COLUMN id SET DEFAULT nextval('public.successful_jobs_id_seq'::regclass);


--
-- Name: targets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.targets ALTER COLUMN id SET DEFAULT nextval('public.targets_id_seq'::regclass);


--
-- Name: test2image id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.test2image ALTER COLUMN id SET DEFAULT nextval('public.test2image_id_seq'::regclass);


--
-- Name: testdiff id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testdiff ALTER COLUMN id SET DEFAULT nextval('public.testdiff_id_seq'::regclass);


--
-- Name: testmeasurement id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testmeasurement ALTER COLUMN id SET DEFAULT nextval('public.testmeasurement_id_seq'::regclass);


--
-- Name: testoutput id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testoutput ALTER COLUMN id SET DEFAULT nextval('public.test_id_seq'::regclass);


--
-- Name: uploadfile id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.uploadfile ALTER COLUMN id SET DEFAULT nextval('public.uploadfile_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.user_id_seq'::regclass);


--
-- Name: blockbuild blockbuild_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blockbuild
  ADD CONSTRAINT blockbuild_pkey PRIMARY KEY (id);


--
-- Name: build2configure build2configure_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2configure
  ADD CONSTRAINT build2configure_pkey PRIMARY KEY (buildid);


--
-- Name: build2group build2group_buildid_groupid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2group
  ADD CONSTRAINT build2group_buildid_groupid_unique UNIQUE (buildid, groupid);


--
-- Name: build2group build2group_groupid_buildid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2group
  ADD CONSTRAINT build2group_groupid_buildid_unique UNIQUE (groupid, buildid);


--
-- Name: build2test build2test_id_testname_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2test
  ADD CONSTRAINT build2test_id_testname_unique UNIQUE (id, testname);


--
-- Name: build2test build2test_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2test
  ADD CONSTRAINT build2test_pkey PRIMARY KEY (id);


--
-- Name: build2test build2test_testname_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2test
  ADD CONSTRAINT build2test_testname_id_unique UNIQUE (testname, id);


--
-- Name: build2update build2update_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2update
  ADD CONSTRAINT build2update_pkey PRIMARY KEY (buildid);


--
-- Name: build_filters build_filters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build_filters
  ADD CONSTRAINT build_filters_pkey PRIMARY KEY (projectid);


--
-- Name: build build_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build
  ADD CONSTRAINT build_pkey PRIMARY KEY (id);


--
-- Name: build build_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build
  ADD CONSTRAINT build_uuid_unique UNIQUE (uuid);


--
-- Name: buildcommandoutputs buildcommandoutputs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildcommandoutputs
  ADD CONSTRAINT buildcommandoutputs_pkey PRIMARY KEY (id);


--
-- Name: buildcommands buildcommands_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildcommands
  ADD CONSTRAINT buildcommands_pkey PRIMARY KEY (id);


--
-- Name: builderror builderror_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.builderror
  ADD CONSTRAINT builderror_pkey PRIMARY KEY (id);


--
-- Name: buildfailure buildfailure_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfailure
  ADD CONSTRAINT buildfailure_pkey PRIMARY KEY (id);


--
-- Name: buildfailureargument buildfailureargument_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfailureargument
  ADD CONSTRAINT buildfailureargument_pkey PRIMARY KEY (id);


--
-- Name: buildfailuredetails buildfailuredetails_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfailuredetails
  ADD CONSTRAINT buildfailuredetails_pkey PRIMARY KEY (id);


--
-- Name: buildfile buildfile_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfile
  ADD CONSTRAINT buildfile_pkey PRIMARY KEY (id);


--
-- Name: buildgroup buildgroup_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildgroup
  ADD CONSTRAINT buildgroup_pkey PRIMARY KEY (id);


--
-- Name: buildgroupposition buildgroupposition_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildgroupposition
  ADD CONSTRAINT buildgroupposition_pkey PRIMARY KEY (id);


--
-- Name: buildmeasurements buildmeasurements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildmeasurements
  ADD CONSTRAINT buildmeasurements_pkey PRIMARY KEY (id);


--
-- Name: buildproperties buildproperties_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildproperties
  ADD CONSTRAINT buildproperties_pkey PRIMARY KEY (buildid);


--
-- Name: buildtesttime buildtesttime_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildtesttime
  ADD CONSTRAINT buildtesttime_pkey PRIMARY KEY (buildid);


--
-- Name: buildupdate buildupdate_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildupdate
  ADD CONSTRAINT buildupdate_pkey PRIMARY KEY (id);


--
-- Name: comments comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments
  ADD CONSTRAINT comments_pkey PRIMARY KEY (id);


--
-- Name: configure configure_crc32_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configure
  ADD CONSTRAINT configure_crc32_unique UNIQUE (crc32);


--
-- Name: configure configure_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configure
  ADD CONSTRAINT configure_pkey PRIMARY KEY (id);


--
-- Name: coverage coverage_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coverage
  ADD CONSTRAINT coverage_pkey PRIMARY KEY (id);


--
-- Name: coveragefile coveragefile_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coveragefile
  ADD CONSTRAINT coveragefile_pkey PRIMARY KEY (id);


--
-- Name: coveragesummary coveragesummary_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coveragesummary
  ADD CONSTRAINT coveragesummary_pkey PRIMARY KEY (buildid);


--
-- Name: dailyupdate dailyupdate_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dailyupdate
  ADD CONSTRAINT dailyupdate_pkey PRIMARY KEY (id);


--
-- Name: dynamicanalysis dynamicanalysis_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dynamicanalysis
  ADD CONSTRAINT dynamicanalysis_pkey PRIMARY KEY (id);


--
-- Name: dynamicanalysisdefect dynamicanalysisdefect_dynamicanalysisid_type_value_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dynamicanalysisdefect
  ADD CONSTRAINT dynamicanalysisdefect_dynamicanalysisid_type_value_unique UNIQUE (dynamicanalysisid, type, value);


--
-- Name: dynamicanalysisdefect dynamicanalysisdefect_dynamicanalysisid_value_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dynamicanalysisdefect
  ADD CONSTRAINT dynamicanalysisdefect_dynamicanalysisid_value_type_unique UNIQUE (dynamicanalysisid, value, type);


--
-- Name: dynamicanalysissummary dynamicanalysissummary_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dynamicanalysissummary
  ADD CONSTRAINT dynamicanalysissummary_pkey PRIMARY KEY (buildid);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
  ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: global_invitations global_invitations_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.global_invitations
  ADD CONSTRAINT global_invitations_email_unique UNIQUE (email);


--
-- Name: global_invitations global_invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.global_invitations
  ADD CONSTRAINT global_invitations_pkey PRIMARY KEY (id);


--
-- Name: image image_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image
  ADD CONSTRAINT image_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
  ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: label2build label2build_buildid_labelid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2build
  ADD CONSTRAINT label2build_buildid_labelid_unique UNIQUE (buildid, labelid);


--
-- Name: label2build label2build_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2build
  ADD CONSTRAINT label2build_pkey PRIMARY KEY (labelid, buildid);


--
-- Name: label2buildfailure label2buildfailure_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2buildfailure
  ADD CONSTRAINT label2buildfailure_pkey PRIMARY KEY (labelid, buildfailureid);


--
-- Name: label2coveragefile label2coveragefile_buildid_coveragefileid_labelid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_buildid_coveragefileid_labelid_unique UNIQUE (buildid, coveragefileid, labelid);


--
-- Name: label2coveragefile label2coveragefile_buildid_labelid_coveragefileid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_buildid_labelid_coveragefileid_unique UNIQUE (buildid, labelid, coveragefileid);


--
-- Name: label2coveragefile label2coveragefile_coveragefileid_buildid_labelid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_coveragefileid_buildid_labelid_unique UNIQUE (coveragefileid, buildid, labelid);


--
-- Name: label2coveragefile label2coveragefile_coveragefileid_labelid_buildid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_coveragefileid_labelid_buildid_unique UNIQUE (coveragefileid, labelid, buildid);


--
-- Name: label2coveragefile label2coveragefile_labelid_coveragefileid_buildid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_labelid_coveragefileid_buildid_unique UNIQUE (labelid, coveragefileid, buildid);


--
-- Name: label2coveragefile label2coveragefile_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_pkey PRIMARY KEY (labelid, buildid, coveragefileid);


--
-- Name: label2dynamicanalysis label2dynamicanalysis_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2dynamicanalysis
  ADD CONSTRAINT label2dynamicanalysis_pkey PRIMARY KEY (labelid, dynamicanalysisid);


--
-- Name: label2target label2target_labelid_targetid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2target
  ADD CONSTRAINT label2target_labelid_targetid_unique UNIQUE (labelid, targetid);


--
-- Name: label2target label2target_targetid_labelid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2target
  ADD CONSTRAINT label2target_targetid_labelid_unique UNIQUE (targetid, labelid);


--
-- Name: label2test label2test_labelid_testid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2test
  ADD CONSTRAINT label2test_labelid_testid_unique UNIQUE (labelid, testid);


--
-- Name: label2test label2test_testid_labelid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2test
  ADD CONSTRAINT label2test_testid_labelid_unique UNIQUE (testid, labelid);


--
-- Name: label2update label2update_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2update
  ADD CONSTRAINT label2update_pkey PRIMARY KEY (labelid, updateid);


--
-- Name: label2update label2update_updateid_labelid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2update
  ADD CONSTRAINT label2update_updateid_labelid_unique UNIQUE (updateid, labelid);


--
-- Name: label label_id_text_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label
  ADD CONSTRAINT label_id_text_unique UNIQUE (id, text);


--
-- Name: label label_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label
  ADD CONSTRAINT label_pkey PRIMARY KEY (id);


--
-- Name: label label_text_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label
  ADD CONSTRAINT label_text_id_unique UNIQUE (text, id);


--
-- Name: label label_text_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label
  ADD CONSTRAINT label_text_unique UNIQUE (text);


--
-- Name: lockout lockout_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lockout
  ADD CONSTRAINT lockout_pkey PRIMARY KEY (userid);


--
-- Name: measurement measurement_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement
  ADD CONSTRAINT measurement_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
  ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: note note_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.note
  ADD CONSTRAINT note_pkey PRIMARY KEY (id);


--
-- Name: pending_submissions pending_submissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pending_submissions
  ADD CONSTRAINT pending_submissions_pkey PRIMARY KEY (buildid);


--
-- Name: project2repositories project2repositories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project2repositories
  ADD CONSTRAINT project2repositories_pkey PRIMARY KEY (projectid, repositoryid);


--
-- Name: project_invitations project_invitations_email_project_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_invitations
  ADD CONSTRAINT project_invitations_email_project_id_unique UNIQUE (email, project_id);


--
-- Name: project_invitations project_invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_invitations
  ADD CONSTRAINT project_invitations_pkey PRIMARY KEY (id);


--
-- Name: project project_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project
  ADD CONSTRAINT project_name_unique UNIQUE (name);


--
-- Name: project project_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project
  ADD CONSTRAINT project_pkey PRIMARY KEY (id);


--
-- Name: related_builds related_builds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.related_builds
  ADD CONSTRAINT related_builds_pkey PRIMARY KEY (buildid, relatedid);


--
-- Name: repositories repositories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.repositories
  ADD CONSTRAINT repositories_pkey PRIMARY KEY (id);


--
-- Name: saml2_tenants saml2_tenants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saml2_tenants
  ADD CONSTRAINT saml2_tenants_pkey PRIMARY KEY (id);


--
-- Name: site2user site2user_siteid_userid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site2user
  ADD CONSTRAINT site2user_siteid_userid_unique UNIQUE (siteid, userid);


--
-- Name: site2user site2user_userid_siteid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site2user
  ADD CONSTRAINT site2user_userid_siteid_unique UNIQUE (userid, siteid);


--
-- Name: site site_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site
  ADD CONSTRAINT site_name_unique UNIQUE (name);


--
-- Name: site site_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site
  ADD CONSTRAINT site_pkey PRIMARY KEY (id);


--
-- Name: siteinformation siteinformation_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.siteinformation
  ADD CONSTRAINT siteinformation_pkey PRIMARY KEY (id);


--
-- Name: subproject2build subproject2build_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subproject2build
  ADD CONSTRAINT subproject2build_pkey PRIMARY KEY (buildid);


--
-- Name: subproject subproject_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subproject
  ADD CONSTRAINT subproject_pkey PRIMARY KEY (id);


--
-- Name: subproject subproject_unique_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subproject
  ADD CONSTRAINT subproject_unique_key UNIQUE (name, projectid, endtime);


--
-- Name: subprojectgroup subprojectgroup_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subprojectgroup
  ADD CONSTRAINT subprojectgroup_pkey PRIMARY KEY (id);


--
-- Name: successful_jobs successful_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.successful_jobs
  ADD CONSTRAINT successful_jobs_pkey PRIMARY KEY (id);


--
-- Name: targets targets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.targets
  ADD CONSTRAINT targets_pkey PRIMARY KEY (id);


--
-- Name: test2image test2image_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.test2image
  ADD CONSTRAINT test2image_pkey PRIMARY KEY (id);


--
-- Name: testoutput test_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testoutput
  ADD CONSTRAINT test_pkey PRIMARY KEY (id);


--
-- Name: testdiff testdiff_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testdiff
  ADD CONSTRAINT testdiff_pkey PRIMARY KEY (id);


--
-- Name: testmeasurement testmeasurement_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testmeasurement
  ADD CONSTRAINT testmeasurement_pkey PRIMARY KEY (id);


--
-- Name: builderrordiff unique_builderrordiff; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.builderrordiff
  ADD CONSTRAINT unique_builderrordiff UNIQUE (buildid, type);


--
-- Name: configureerrordiff unique_configureerrordiff; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configureerrordiff
  ADD CONSTRAINT unique_configureerrordiff UNIQUE (buildid, type);


--
-- Name: testdiff unique_testdiff; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testdiff
  ADD CONSTRAINT unique_testdiff UNIQUE (buildid, type);


--
-- Name: uploadfile uploadfile_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.uploadfile
  ADD CONSTRAINT uploadfile_pkey PRIMARY KEY (id);


--
-- Name: user2project user2project_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user2project
  ADD CONSTRAINT user2project_pkey PRIMARY KEY (userid, projectid);


--
-- Name: users user_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
  ADD CONSTRAINT user_email_unique UNIQUE (email);


--
-- Name: users user_ldapguid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
  ADD CONSTRAINT user_ldapguid_unique UNIQUE (ldapguid);


--
-- Name: users user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
  ADD CONSTRAINT user_pkey PRIMARY KEY (id);


--
-- Name: usertemp usertemp_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usertemp
  ADD CONSTRAINT usertemp_pkey PRIMARY KEY (email);


--
-- Name: apitoken_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apitoken_token_index ON public.apitoken USING btree (token);


--
-- Name: authtoken_expires_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX authtoken_expires_index ON public.authtoken USING btree (expires);


--
-- Name: authtoken_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX authtoken_hash_index ON public.authtoken USING btree (hash);


--
-- Name: authtoken_userid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX authtoken_userid_index ON public.authtoken USING btree (userid);


--
-- Name: blockbuild_buildname_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blockbuild_buildname_index ON public.blockbuild USING btree (buildname);


--
-- Name: blockbuild_ipaddress_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blockbuild_ipaddress_index ON public.blockbuild USING btree (ipaddress);


--
-- Name: blockbuild_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blockbuild_projectid_index ON public.blockbuild USING btree (projectid);


--
-- Name: blockbuild_sitename_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blockbuild_sitename_index ON public.blockbuild USING btree (sitename);


--
-- Name: build2configure_configureid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2configure_configureid_index ON public.build2configure USING btree (configureid);


--
-- Name: build2group_groupid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2group_groupid_index ON public.build2group USING btree (groupid);


--
-- Name: build2grouprule_buildname_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2grouprule_buildname_index ON public.build2grouprule USING btree (buildname);


--
-- Name: build2grouprule_buildtype_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2grouprule_buildtype_index ON public.build2grouprule USING btree (buildtype);


--
-- Name: build2grouprule_endtime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2grouprule_endtime_index ON public.build2grouprule USING btree (endtime);


--
-- Name: build2grouprule_expected_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2grouprule_expected_index ON public.build2grouprule USING btree (expected);


--
-- Name: build2grouprule_groupid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2grouprule_groupid_index ON public.build2grouprule USING btree (groupid);


--
-- Name: build2grouprule_parentgroupid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2grouprule_parentgroupid_index ON public.build2grouprule USING btree (parentgroupid);


--
-- Name: build2grouprule_siteid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2grouprule_siteid_index ON public.build2grouprule USING btree (siteid);


--
-- Name: build2grouprule_starttime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2grouprule_starttime_index ON public.build2grouprule USING btree (starttime);


--
-- Name: build2note_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2note_buildid_index ON public.build2note USING btree (buildid);


--
-- Name: build2note_noteid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2note_noteid_index ON public.build2note USING btree (noteid);


--
-- Name: build2test_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_buildid_index ON public.build2test USING btree (buildid);


--
-- Name: build2test_buildid_outputid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_buildid_outputid_index ON public.build2test USING btree (buildid, outputid);


--
-- Name: build2test_buildid_testname_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_buildid_testname_index ON public.build2test USING btree (buildid, testname);


--
-- Name: build2test_newstatus_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_newstatus_index ON public.build2test USING btree (newstatus);


--
-- Name: build2test_outputid_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_outputid_buildid_index ON public.build2test USING btree (outputid, buildid);


--
-- Name: build2test_outputid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_outputid_index ON public.build2test USING btree (outputid);


--
-- Name: build2test_outputid_testname_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_outputid_testname_index ON public.build2test USING btree (outputid, testname);


--
-- Name: build2test_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_status_index ON public.build2test USING btree (status);


--
-- Name: build2test_testname_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_testname_buildid_index ON public.build2test USING btree (testname, buildid);


--
-- Name: build2test_testname_outputid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_testname_outputid_index ON public.build2test USING btree (testname, outputid);


--
-- Name: build2test_timestatus_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2test_timestatus_index ON public.build2test USING btree (timestatus);


--
-- Name: build2update_updateid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2update_updateid_index ON public.build2update USING btree (updateid);


--
-- Name: build2uploadfile_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2uploadfile_buildid_index ON public.build2uploadfile USING btree (buildid);


--
-- Name: build2uploadfile_fileid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build2uploadfile_fileid_index ON public.build2uploadfile USING btree (fileid);


--
-- Name: build_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_name_index ON public.build USING btree (name);


--
-- Name: build_parentid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_parentid_index ON public.build USING btree (parentid);


--
-- Name: build_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_projectid_index ON public.build USING btree (projectid);


--
-- Name: build_projectid_submittime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_projectid_submittime_index ON public.build USING btree (projectid, submittime);


--
-- Name: build_siteid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_siteid_index ON public.build USING btree (siteid);


--
-- Name: build_stamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_stamp_index ON public.build USING btree (stamp);


--
-- Name: build_starttime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_starttime_index ON public.build USING btree (starttime);


--
-- Name: build_submittime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_submittime_index ON public.build USING btree (submittime);


--
-- Name: build_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX build_type_index ON public.build USING btree (type);


--
-- Name: buildcommandoutputs_buildcommandid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildcommandoutputs_buildcommandid_index ON public.buildcommandoutputs USING btree (buildcommandid);


--
-- Name: buildcommands_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildcommands_buildid_index ON public.buildcommands USING btree (buildid);


--
-- Name: buildcommands_targetid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildcommands_targetid_index ON public.buildcommands USING btree (targetid);


--
-- Name: buildemail_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildemail_buildid_index ON public.buildemail USING btree (buildid);


--
-- Name: buildemail_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildemail_category_index ON public.buildemail USING btree (category);


--
-- Name: buildemail_userid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildemail_userid_index ON public.buildemail USING btree (userid);


--
-- Name: builderror_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderror_buildid_index ON public.builderror USING btree (buildid);


--
-- Name: builderror_buildid_type_crc32_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderror_buildid_type_crc32_index ON public.builderror USING btree (buildid, type, crc32);


--
-- Name: builderror_crc32_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderror_crc32_index ON public.builderror USING btree (crc32);


--
-- Name: builderror_newstatus_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderror_newstatus_index ON public.builderror USING btree (newstatus);


--
-- Name: builderror_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderror_type_index ON public.builderror USING btree (type);


--
-- Name: builderrordiff_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderrordiff_buildid_index ON public.builderrordiff USING btree (buildid);


--
-- Name: builderrordiff_difference_negative_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderrordiff_difference_negative_index ON public.builderrordiff USING btree (difference_negative);


--
-- Name: builderrordiff_difference_positive_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderrordiff_difference_positive_index ON public.builderrordiff USING btree (difference_positive);


--
-- Name: builderrordiff_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX builderrordiff_type_index ON public.builderrordiff USING btree (type);


--
-- Name: buildfailure2argument_argumentid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailure2argument_argumentid_index ON public.buildfailure2argument USING btree (argumentid);


--
-- Name: buildfailure2argument_buildfailureid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailure2argument_buildfailureid_index ON public.buildfailure2argument USING btree (buildfailureid);


--
-- Name: buildfailure_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailure_buildid_index ON public.buildfailure USING btree (buildid);


--
-- Name: buildfailure_detailsid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailure_detailsid_index ON public.buildfailure USING btree (detailsid);


--
-- Name: buildfailure_newstatus_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailure_newstatus_index ON public.buildfailure USING btree (newstatus);


--
-- Name: buildfailureargument_argument_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailureargument_argument_index ON public.buildfailureargument USING btree (argument);


--
-- Name: buildfailuredetails_crc32_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailuredetails_crc32_index ON public.buildfailuredetails USING btree (crc32);


--
-- Name: buildfailuredetails_exitcondition_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailuredetails_exitcondition_index ON public.buildfailuredetails USING btree (exitcondition);


--
-- Name: buildfailuredetails_language_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailuredetails_language_index ON public.buildfailuredetails USING btree (language);


--
-- Name: buildfailuredetails_outputfile_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailuredetails_outputfile_index ON public.buildfailuredetails USING btree (outputfile);


--
-- Name: buildfailuredetails_outputtype_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailuredetails_outputtype_index ON public.buildfailuredetails USING btree (outputtype);


--
-- Name: buildfailuredetails_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfailuredetails_type_index ON public.buildfailuredetails USING btree (type);


--
-- Name: buildfile_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfile_buildid_index ON public.buildfile USING btree (buildid);


--
-- Name: buildfile_filename_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfile_filename_index ON public.buildfile USING btree (filename);


--
-- Name: buildfile_md5_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfile_md5_index ON public.buildfile USING btree (md5);


--
-- Name: buildfile_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildfile_type_index ON public.buildfile USING btree (type);


--
-- Name: buildgroup_endtime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildgroup_endtime_index ON public.buildgroup USING btree (endtime);


--
-- Name: buildgroup_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildgroup_projectid_index ON public.buildgroup USING btree (projectid);


--
-- Name: buildgroup_starttime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildgroup_starttime_index ON public.buildgroup USING btree (starttime);


--
-- Name: buildgroup_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildgroup_type_index ON public.buildgroup USING btree (type);


--
-- Name: buildgroupposition_buildgroupid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildgroupposition_buildgroupid_index ON public.buildgroupposition USING btree (buildgroupid);


--
-- Name: buildgroupposition_endtime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildgroupposition_endtime_index ON public.buildgroupposition USING btree (endtime);


--
-- Name: buildgroupposition_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildgroupposition_position_index ON public.buildgroupposition USING btree ("position");


--
-- Name: buildgroupposition_starttime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildgroupposition_starttime_index ON public.buildgroupposition USING btree (starttime);


--
-- Name: buildmeasurements_buildcommandid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildmeasurements_buildcommandid_index ON public.buildmeasurements USING btree (buildcommandid);


--
-- Name: buildnote_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildnote_buildid_index ON public.comments USING btree (buildid);


--
-- Name: buildupdate_revision_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildupdate_revision_index ON public.buildupdate USING btree (revision);


--
-- Name: configureerror_configureid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX configureerror_configureid_index ON public.configureerror USING btree (configureid);


--
-- Name: configureerror_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX configureerror_type_index ON public.configureerror USING btree (type);


--
-- Name: configureerrordiff_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX configureerrordiff_buildid_index ON public.configureerrordiff USING btree (buildid);


--
-- Name: configureerrordiff_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX configureerrordiff_type_index ON public.configureerrordiff USING btree (type);


--
-- Name: coverage_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coverage_buildid_index ON public.coverage USING btree (buildid);


--
-- Name: coverage_covered_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coverage_covered_index ON public.coverage USING btree (covered);


--
-- Name: coverage_fileid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coverage_fileid_index ON public.coverage USING btree (fileid);


--
-- Name: coveragefile_crc32_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coveragefile_crc32_index ON public.coveragefile USING btree (crc32);


--
-- Name: coveragefile_fullpath_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coveragefile_fullpath_index ON public.coveragefile USING btree (fullpath);


--
-- Name: coveragefilelog_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coveragefilelog_buildid_index ON public.coveragefilelog USING btree (buildid);


--
-- Name: coveragefilelog_fileid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coveragefilelog_fileid_index ON public.coveragefilelog USING btree (fileid);


--
-- Name: dailyupdate_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dailyupdate_date_index ON public.dailyupdate USING btree (date);


--
-- Name: dailyupdate_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dailyupdate_projectid_index ON public.dailyupdate USING btree (projectid);


--
-- Name: dynamicanalysis_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dynamicanalysis_buildid_index ON public.dynamicanalysis USING btree (buildid);


--
-- Name: dynamicanalysisdefect_dynamicanalysisid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dynamicanalysisdefect_dynamicanalysisid_index ON public.dynamicanalysisdefect USING btree (dynamicanalysisid);


--
-- Name: dynamicanalysisdefect_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dynamicanalysisdefect_type_index ON public.dynamicanalysisdefect USING btree (type);


--
-- Name: dynamicanalysisdefect_value_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dynamicanalysisdefect_value_index ON public.dynamicanalysisdefect USING btree (value);


--
-- Name: global_invitations_invitation_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX global_invitations_invitation_timestamp_index ON public.global_invitations USING btree (invitation_timestamp);


--
-- Name: global_invitations_invited_by_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX global_invitations_invited_by_id_index ON public.global_invitations USING btree (invited_by_id);


--
-- Name: image_checksum_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX image_checksum_index ON public.image USING btree (checksum);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: label2build_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX label2build_buildid_index ON public.label2build USING btree (buildid);


--
-- Name: label2build_labelid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX label2build_labelid_index ON public.label2build USING btree (labelid);


--
-- Name: label2buildfailure_buildfailureid_labelid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX label2buildfailure_buildfailureid_labelid_index ON public.label2buildfailure USING btree (buildfailureid, labelid);


--
-- Name: label2dynamicanalysis_dynamicanalysisid_labelid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX label2dynamicanalysis_dynamicanalysisid_labelid_index ON public.label2dynamicanalysis USING btree (dynamicanalysisid, labelid);


--
-- Name: labelemail_labelid_projectid_userid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX labelemail_labelid_projectid_userid_index ON public.labelemail USING btree (labelid, projectid, userid);


--
-- Name: labelemail_labelid_userid_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX labelemail_labelid_userid_projectid_index ON public.labelemail USING btree (labelid, userid, projectid);


--
-- Name: labelemail_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX labelemail_projectid_index ON public.labelemail USING btree (projectid);


--
-- Name: labelemail_projectid_labelid_userid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX labelemail_projectid_labelid_userid_index ON public.labelemail USING btree (projectid, labelid, userid);


--
-- Name: labelemail_projectid_userid_labelid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX labelemail_projectid_userid_labelid_index ON public.labelemail USING btree (projectid, userid, labelid);


--
-- Name: labelemail_userid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX labelemail_userid_index ON public.labelemail USING btree (userid);


--
-- Name: labelemail_userid_labelid_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX labelemail_userid_labelid_projectid_index ON public.labelemail USING btree (userid, labelid, projectid);


--
-- Name: labelemail_userid_projectid_labelid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX labelemail_userid_projectid_labelid_index ON public.labelemail USING btree (userid, projectid, labelid);


--
-- Name: measurement_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX measurement_name_index ON public.measurement USING btree (name);


--
-- Name: measurement_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX measurement_projectid_index ON public.measurement USING btree (projectid);


--
-- Name: note_crc32_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX note_crc32_index ON public.note USING btree (crc32);


--
-- Name: overview_components_buildgroupid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX overview_components_buildgroupid_index ON public.overview_components USING btree (buildgroupid);


--
-- Name: overview_components_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX overview_components_projectid_index ON public.overview_components USING btree (projectid);


--
-- Name: password_resets_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX password_resets_email_index ON public.password_resets USING btree (email);


--
-- Name: project_invitations_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX project_invitations_email_index ON public.project_invitations USING btree (email);


--
-- Name: project_invitations_invitation_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX project_invitations_invitation_timestamp_index ON public.project_invitations USING btree (invitation_timestamp);


--
-- Name: project_invitations_invited_by_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX project_invitations_invited_by_id_index ON public.project_invitations USING btree (invited_by_id);


--
-- Name: project_invitations_project_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX project_invitations_project_id_index ON public.project_invitations USING btree (project_id);


--
-- Name: project_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX project_name_index ON public.project USING btree (name);


--
-- Name: project_public_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX project_public_index ON public.project USING btree (public);


--
-- Name: projectid_parentid_starttime; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projectid_parentid_starttime ON public.build USING btree (projectid, parentid, starttime);


--
-- Name: related_builds_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX related_builds_buildid_index ON public.related_builds USING btree (buildid);


--
-- Name: related_builds_relatedid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX related_builds_relatedid_index ON public.related_builds USING btree (relatedid);


--
-- Name: site2user_siteid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX site2user_siteid_index ON public.site2user USING btree (siteid);


--
-- Name: site2user_userid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX site2user_userid_index ON public.site2user USING btree (userid);


--
-- Name: siteid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX siteid ON public.siteinformation USING btree (siteid, "timestamp");


--
-- Name: subproject2build_subprojectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subproject2build_subprojectid_index ON public.subproject2build USING btree (subprojectid);


--
-- Name: subproject2subproject_dependsonid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subproject2subproject_dependsonid_index ON public.subproject2subproject USING btree (dependsonid);


--
-- Name: subproject2subproject_subprojectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subproject2subproject_subprojectid_index ON public.subproject2subproject USING btree (subprojectid);


--
-- Name: subproject_groupid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subproject_groupid_index ON public.subproject USING btree (groupid);


--
-- Name: subproject_path_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subproject_path_index ON public.subproject USING btree (path);


--
-- Name: subproject_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subproject_projectid_index ON public.subproject USING btree (projectid);


--
-- Name: subprojectgroup_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subprojectgroup_name_index ON public.subprojectgroup USING btree (name);


--
-- Name: subprojectgroup_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subprojectgroup_position_index ON public.subprojectgroup USING btree ("position");


--
-- Name: subprojectgroup_projectid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subprojectgroup_projectid_index ON public.subprojectgroup USING btree (projectid);


--
-- Name: summaryemail_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX summaryemail_date_index ON public.summaryemail USING btree (date);


--
-- Name: summaryemail_groupid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX summaryemail_groupid_index ON public.summaryemail USING btree (groupid);


--
-- Name: targets_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX targets_buildid_index ON public.targets USING btree (buildid);


--
-- Name: test2image_imgid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX test2image_imgid_index ON public.test2image USING btree (imgid);


--
-- Name: test2image_testid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX test2image_testid_index ON public.test2image USING btree (outputid);


--
-- Name: test_crc32_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX test_crc32_index ON public.testoutput USING btree (crc32);


--
-- Name: testdiff_buildid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX testdiff_buildid_index ON public.testdiff USING btree (buildid);


--
-- Name: testdiff_difference_negative_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX testdiff_difference_negative_index ON public.testdiff USING btree (difference_negative);


--
-- Name: testdiff_difference_positive_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX testdiff_difference_positive_index ON public.testdiff USING btree (difference_positive);


--
-- Name: testdiff_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX testdiff_type_index ON public.testdiff USING btree (type);


--
-- Name: testmeasurement_testid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX testmeasurement_testid_index ON public.testmeasurement USING btree (testid);


--
-- Name: updatefile_author_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX updatefile_author_index ON public.updatefile USING btree (author);


--
-- Name: updatefile_filename_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX updatefile_filename_index ON public.updatefile USING btree (filename);


--
-- Name: updatefile_updateid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX updatefile_updateid_index ON public.updatefile USING btree (updateid);


--
-- Name: uploadfile_sha1sum_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX uploadfile_sha1sum_index ON public.uploadfile USING btree (sha1sum);


--
-- Name: user2project_emailmissingsites_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user2project_emailmissingsites_index ON public.user2project USING btree (emailmissingsites);


--
-- Name: user2project_emailsuccess_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user2project_emailsuccess_index ON public.user2project USING btree (emailsuccess);


--
-- Name: user2project_emailtype_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user2project_emailtype_index ON public.user2project USING btree (emailtype);


--
-- Name: user_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_email_index ON public.users USING btree (email);


--
-- Name: usertemp_registrationdate_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX usertemp_registrationdate_index ON public.usertemp USING btree (registrationdate);


--
-- Name: authtoken authtoken_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.authtoken
  ADD CONSTRAINT authtoken_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: authtoken authtoken_userid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.authtoken
  ADD CONSTRAINT authtoken_userid_foreign FOREIGN KEY (userid) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: blockbuild blockbuild_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blockbuild
  ADD CONSTRAINT blockbuild_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: build2configure build2configure_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2configure
  ADD CONSTRAINT build2configure_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: build2group build2group_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2group
  ADD CONSTRAINT build2group_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: build2group build2group_groupid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2group
  ADD CONSTRAINT build2group_groupid_foreign FOREIGN KEY (groupid) REFERENCES public.buildgroup(id) ON DELETE CASCADE;


--
-- Name: build2note build2note_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2note
  ADD CONSTRAINT build2note_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: build2test build2test_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2test
  ADD CONSTRAINT build2test_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: build2test build2test_outputid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2test
  ADD CONSTRAINT build2test_outputid_foreign FOREIGN KEY (outputid) REFERENCES public.testoutput(id) ON DELETE CASCADE;


--
-- Name: build2update build2update_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2update
  ADD CONSTRAINT build2update_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: build2uploadfile build2uploadfile_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2uploadfile
  ADD CONSTRAINT build2uploadfile_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: build2uploadfile build2uploadfile_fileid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build2uploadfile
  ADD CONSTRAINT build2uploadfile_fileid_foreign FOREIGN KEY (fileid) REFERENCES public.uploadfile(id) ON DELETE CASCADE;


--
-- Name: build_filters build_filters_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build_filters
  ADD CONSTRAINT build_filters_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: build build_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.build
  ADD CONSTRAINT build_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: buildcommandoutputs buildcommandoutputs_buildcommandid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildcommandoutputs
  ADD CONSTRAINT buildcommandoutputs_buildcommandid_foreign FOREIGN KEY (buildcommandid) REFERENCES public.buildcommands(id) ON DELETE CASCADE;


--
-- Name: buildcommands buildcommands_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildcommands
  ADD CONSTRAINT buildcommands_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: buildcommands buildcommands_targetid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildcommands
  ADD CONSTRAINT buildcommands_targetid_foreign FOREIGN KEY (targetid) REFERENCES public.targets(id) ON DELETE CASCADE;


--
-- Name: buildemail buildemail_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildemail
  ADD CONSTRAINT buildemail_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: buildemail buildemail_userid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildemail
  ADD CONSTRAINT buildemail_userid_foreign FOREIGN KEY (userid) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: builderror builderror_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.builderror
  ADD CONSTRAINT builderror_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: builderrordiff builderrordiff_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.builderrordiff
  ADD CONSTRAINT builderrordiff_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: buildfailure2argument buildfailure2argument_buildfailureid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfailure2argument
  ADD CONSTRAINT buildfailure2argument_buildfailureid_foreign FOREIGN KEY (buildfailureid) REFERENCES public.buildfailure(id) ON DELETE CASCADE;


--
-- Name: buildfailure buildfailure_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfailure
  ADD CONSTRAINT buildfailure_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: buildfile buildfile_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildfile
  ADD CONSTRAINT buildfile_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: buildgroup buildgroup_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildgroup
  ADD CONSTRAINT buildgroup_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: buildgroupposition buildgroupposition_buildgroupid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildgroupposition
  ADD CONSTRAINT buildgroupposition_buildgroupid_foreign FOREIGN KEY (buildgroupid) REFERENCES public.buildgroup(id) ON DELETE CASCADE;


--
-- Name: buildmeasurements buildmeasurements_buildcommandid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildmeasurements
  ADD CONSTRAINT buildmeasurements_buildcommandid_foreign FOREIGN KEY (buildcommandid) REFERENCES public.buildcommands(id) ON DELETE CASCADE;


--
-- Name: buildproperties buildproperties_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildproperties
  ADD CONSTRAINT buildproperties_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: buildtesttime buildtesttime_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildtesttime
  ADD CONSTRAINT buildtesttime_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: comments comments_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments
  ADD CONSTRAINT comments_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id);


--
-- Name: comments comments_userid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments
  ADD CONSTRAINT comments_userid_foreign FOREIGN KEY (userid) REFERENCES public.users(id);


--
-- Name: configureerror configureerror_configureid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configureerror
  ADD CONSTRAINT configureerror_configureid_foreign FOREIGN KEY (configureid) REFERENCES public.configure(id) ON DELETE CASCADE;


--
-- Name: configureerrordiff configureerrordiff_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configureerrordiff
  ADD CONSTRAINT configureerrordiff_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: coverage coverage_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coverage
  ADD CONSTRAINT coverage_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: coveragefilelog coveragefilelog_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coveragefilelog
  ADD CONSTRAINT coveragefilelog_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: coveragesummary coveragesummary_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coveragesummary
  ADD CONSTRAINT coveragesummary_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: dynamicanalysis dynamicanalysis_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dynamicanalysis
  ADD CONSTRAINT dynamicanalysis_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: dynamicanalysisdefect dynamicanalysisdefect_dynamicanalysisid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dynamicanalysisdefect
  ADD CONSTRAINT dynamicanalysisdefect_dynamicanalysisid_foreign FOREIGN KEY (dynamicanalysisid) REFERENCES public.dynamicanalysis(id) ON DELETE CASCADE;


--
-- Name: dynamicanalysissummary dynamicanalysissummary_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dynamicanalysissummary
  ADD CONSTRAINT dynamicanalysissummary_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: global_invitations global_invitations_invited_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.global_invitations
  ADD CONSTRAINT global_invitations_invited_by_id_foreign FOREIGN KEY (invited_by_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: label2build label2build_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2build
  ADD CONSTRAINT label2build_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: label2build label2build_labelid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2build
  ADD CONSTRAINT label2build_labelid_foreign FOREIGN KEY (labelid) REFERENCES public.label(id) ON DELETE CASCADE;


--
-- Name: label2buildfailure label2buildfailure_buildfailureid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2buildfailure
  ADD CONSTRAINT label2buildfailure_buildfailureid_foreign FOREIGN KEY (buildfailureid) REFERENCES public.buildfailure(id) ON DELETE CASCADE;


--
-- Name: label2buildfailure label2buildfailure_labelid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2buildfailure
  ADD CONSTRAINT label2buildfailure_labelid_foreign FOREIGN KEY (labelid) REFERENCES public.label(id) ON DELETE CASCADE;


--
-- Name: label2coveragefile label2coveragefile_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: label2coveragefile label2coveragefile_coveragefileid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_coveragefileid_foreign FOREIGN KEY (coveragefileid) REFERENCES public.coveragefile(id) ON DELETE CASCADE;


--
-- Name: label2coveragefile label2coveragefile_labelid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2coveragefile
  ADD CONSTRAINT label2coveragefile_labelid_foreign FOREIGN KEY (labelid) REFERENCES public.label(id) ON DELETE CASCADE;


--
-- Name: label2dynamicanalysis label2dynamicanalysis_dynamicanalysisid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2dynamicanalysis
  ADD CONSTRAINT label2dynamicanalysis_dynamicanalysisid_foreign FOREIGN KEY (dynamicanalysisid) REFERENCES public.dynamicanalysis(id) ON DELETE CASCADE;


--
-- Name: label2dynamicanalysis label2dynamicanalysis_labelid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2dynamicanalysis
  ADD CONSTRAINT label2dynamicanalysis_labelid_foreign FOREIGN KEY (labelid) REFERENCES public.label(id) ON DELETE CASCADE;


--
-- Name: label2target label2target_labelid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2target
  ADD CONSTRAINT label2target_labelid_foreign FOREIGN KEY (labelid) REFERENCES public.label(id) ON DELETE CASCADE;


--
-- Name: label2target label2target_targetid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2target
  ADD CONSTRAINT label2target_targetid_foreign FOREIGN KEY (targetid) REFERENCES public.targets(id) ON DELETE CASCADE;


--
-- Name: label2test label2test_labelid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2test
  ADD CONSTRAINT label2test_labelid_foreign FOREIGN KEY (labelid) REFERENCES public.label(id) ON DELETE CASCADE;


--
-- Name: label2test label2test_testid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2test
  ADD CONSTRAINT label2test_testid_foreign FOREIGN KEY (testid) REFERENCES public.build2test(id) ON DELETE CASCADE;


--
-- Name: label2update label2update_labelid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2update
  ADD CONSTRAINT label2update_labelid_foreign FOREIGN KEY (labelid) REFERENCES public.label(id) ON DELETE CASCADE;


--
-- Name: label2update label2update_updateid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label2update
  ADD CONSTRAINT label2update_updateid_foreign FOREIGN KEY (updateid) REFERENCES public.buildupdate(id) ON DELETE CASCADE;


--
-- Name: labelemail labelemail_labelid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.labelemail
  ADD CONSTRAINT labelemail_labelid_foreign FOREIGN KEY (labelid) REFERENCES public.label(id) ON DELETE CASCADE;


--
-- Name: labelemail labelemail_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.labelemail
  ADD CONSTRAINT labelemail_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: labelemail labelemail_userid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.labelemail
  ADD CONSTRAINT labelemail_userid_foreign FOREIGN KEY (userid) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: lockout lockout_userid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lockout
  ADD CONSTRAINT lockout_userid_foreign FOREIGN KEY (userid) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: measurement measurement_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement
  ADD CONSTRAINT measurement_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: overview_components overview_components_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.overview_components
  ADD CONSTRAINT overview_components_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: pending_submissions pending_submissions_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pending_submissions
  ADD CONSTRAINT pending_submissions_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: project2repositories project2repositories_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project2repositories
  ADD CONSTRAINT project2repositories_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: project_invitations project_invitations_invited_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_invitations
  ADD CONSTRAINT project_invitations_invited_by_id_foreign FOREIGN KEY (invited_by_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: project_invitations project_invitations_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_invitations
  ADD CONSTRAINT project_invitations_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: related_builds related_builds_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.related_builds
  ADD CONSTRAINT related_builds_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: related_builds related_builds_relatedid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.related_builds
  ADD CONSTRAINT related_builds_relatedid_foreign FOREIGN KEY (relatedid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: site2user site2user_siteid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site2user
  ADD CONSTRAINT site2user_siteid_foreign FOREIGN KEY (siteid) REFERENCES public.site(id) ON DELETE CASCADE;


--
-- Name: site2user site2user_userid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site2user
  ADD CONSTRAINT site2user_userid_foreign FOREIGN KEY (userid) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: siteinformation siteinformation_siteid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.siteinformation
  ADD CONSTRAINT siteinformation_siteid_foreign FOREIGN KEY (siteid) REFERENCES public.site(id) ON DELETE CASCADE;


--
-- Name: subproject2build subproject2build_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subproject2build
  ADD CONSTRAINT subproject2build_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: subproject2subproject subproject2subproject_dependsonid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subproject2subproject
  ADD CONSTRAINT subproject2subproject_dependsonid_foreign FOREIGN KEY (dependsonid) REFERENCES public.subproject(id) ON DELETE CASCADE;


--
-- Name: subproject2subproject subproject2subproject_subprojectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subproject2subproject
  ADD CONSTRAINT subproject2subproject_subprojectid_foreign FOREIGN KEY (subprojectid) REFERENCES public.subproject(id) ON DELETE CASCADE;


--
-- Name: subproject subproject_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subproject
  ADD CONSTRAINT subproject_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: subprojectgroup subprojectgroup_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subprojectgroup
  ADD CONSTRAINT subprojectgroup_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: summaryemail summaryemail_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.summaryemail
  ADD CONSTRAINT summaryemail_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: targets targets_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.targets
  ADD CONSTRAINT targets_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: test2image test2image_outputid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.test2image
  ADD CONSTRAINT test2image_outputid_foreign FOREIGN KEY (outputid) REFERENCES public.testoutput(id) ON DELETE CASCADE;


--
-- Name: testdiff testdiff_buildid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testdiff
  ADD CONSTRAINT testdiff_buildid_foreign FOREIGN KEY (buildid) REFERENCES public.build(id) ON DELETE CASCADE;


--
-- Name: testmeasurement testmeasurement_testid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testmeasurement
  ADD CONSTRAINT testmeasurement_testid_foreign FOREIGN KEY (testid) REFERENCES public.build2test(id) ON DELETE CASCADE;


--
-- Name: updatefile updatefile_updateid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.updatefile
  ADD CONSTRAINT updatefile_updateid_foreign FOREIGN KEY (updateid) REFERENCES public.buildupdate(id) ON DELETE CASCADE;


--
-- Name: user2project user2project_projectid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user2project
  ADD CONSTRAINT user2project_projectid_foreign FOREIGN KEY (projectid) REFERENCES public.project(id) ON DELETE CASCADE;


--
-- Name: user2project user2project_userid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user2project
  ADD CONSTRAINT user2project_userid_foreign FOREIGN KEY (userid) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--
