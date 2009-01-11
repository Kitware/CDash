
--
-- Language: plpgsql
--
CREATE LANGUAGE 'plpgsql';


--
-- Function: unix_timestamp
--
CREATE FUNCTION unix_timestamp(timestamp) RETURNS int AS $$ BEGIN RETURN floor(extract(EPOCH FROM $1)); END $$ LANGUAGE 'plpgsql';

--
-- Language: plpgsql
--
CREATE LANGUAGE 'plpgsql';


--
-- Function: unix_timestamp
--
CREATE FUNCTION unix_timestamp(timestamp) RETURNS int AS $$ BEGIN RETURN floor(extract(EPOCH FROM $1)); END $$ LANGUAGE 'plpgsql';
