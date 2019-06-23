-- Functions in this file should start with CREATE
--
-- Function: unix_timestamp
--
CREATE OR REPLACE FUNCTION unix_timestamp(timestamp) RETURNS int AS $$ BEGIN RETURN floor(extract(EPOCH FROM $1)); END; $$ LANGUAGE 'plpgsql';

--
-- Function: Reverse
--
CREATE OR REPLACE FUNCTION REVERSE(text) RETURNS TEXT AS
'
DECLARE reversed_string text;
incoming alias for $1;
BEGIN
reversed_string = '''';
for i in reverse char_length(incoming)..1 loop
reversed_string = reversed_string || substring(incoming from i for 1);
end loop;
return reversed_string;
END'
LANGUAGE PLPGSQL;

--
-- Function: Locate
--
CREATE OR REPLACE FUNCTION LOCATE(text,text) RETURNS INT AS
'SELECT STRPOS($2, $1)'
LANGUAGE SQL;

--
-- Function: Right
--
CREATE OR REPLACE FUNCTION RIGHT(text,int) RETURNS TEXT AS
'SELECT SUBSTR($1, LENGTH($1)-$2+1)'
LANGUAGE SQL;