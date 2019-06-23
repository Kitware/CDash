#!/usr/bin/perl

sub alter_column_type
{
    $table = @_[0];
    $column = @_[1];
    $type = @_[2];

    # SQLTranslator also ignores this:
    $type =~ s/INT\s*\(\s*\d+\s*\)/INT/;

    # check for "NOT NULL":
    if($type =~ /NOT\s+NULL/) {
        $type =~ s/\s*NOT\s+NULL//;
        $notnull = 1;
    }
    else {
        $notnull = 0;
    }

    # check for "DEFAULT":
    if($type =~ /DEFAULT\s+\S+/) {
        $type =~ s/\s*DEFAULT\s+(\S+)//;
        $setdefault = 1;
        $default = $1;
    }
    else {
        $setdefault = 0;
    }

    # check for "AUTO_INCREMENT":
    if($type =~ /AUTO_INCREMENT/) {
        $type =~ s/\s*AUTO_INCREMENT//;
        $serial = 1;
    }
    else {
        $serial = 0;
    }

    print "ALTER TABLE \"$table\" ALTER COLUMN \"$column\" TYPE $type;\n";

    if($notnull) {
        print "ALTER TABLE \"$table\" ALTER COLUMN \"$column\" SET NOT NULL;\n";
    }

    if($setdefault) {
        print "ALTER TABLE \"$table\" ALTER COLUMN \"$column\" SET DEFAULT $default;\n";
    }

    if($serial) {
        $seqname = "${table}_${column}_seq";
        print "CREATE SEQUENCE \"$seqname\";\n";
        print "ALTER TABLE \"$table\" ALTER COLUMN \"$column\" SET DEFAULT nextval('$seqname');\n";
        print "ALTER SEQUENCE \"$seqname\" OWNED BY \"$table\".\"$column\";\n";
    }
}

while(<>) {
    next if !/^ALTER TABLE/;
    chop;
    s/`//g;

    if(/^ALTER TABLE\s+(\w+)\s+ADD\s+INDEX\s*\(\s*(\w+)\s*\)\s*;\s*$/) {
        $table = $1;
        $column = $2;
        print "CREATE INDEX \"${table}_${column}_idx\" ON \"$table\" (\"$column\");\n";
    }
    elsif(/^ALTER TABLE\s+(\w+)\s+DROP\s+INDEX\s+(\w+)\s*;\s*$/) {
        $table = $1;
        $column = $2;
        print "DROP INDEX \"${table}_${column}_idx\";\n";
    }
    elsif(/^ALTER TABLE\s+(\w+)\s+CHANGE\s+(\w+)\s+(\w+)\s+(.*)\s*;\s*$/) {
        $table = $1;
        $col_old = $2;
        $col_new = $3;
        $type = $4;

        if($col_old ne $col_new) {
            print "can't rename column \"$col_old\" to \"$col_new\" in table \"$table\"\n";
        }
        else {
            alter_column_type($table, $col_old, $type);
        }
    }
    elsif(/^ALTER TABLE\s+(\w+)\s+MODIFY\s+(\w+)\s+(.*)\s*;\s*$/) {
        $table = $1;
        $column = $2;
        $type = $3;
        alter_column_type($table, $column, $type);
    }
    elsif(/^ALTER TABLE\s+(\w+)\s+ADD\s+PRIMARY\s+KEY\s*\(\s*(\w+)\s*\)\s*;\s*$/) {
        $table = $1;
        $column = $2;
        print "ALTER TABLE \"$table\" ADD PRIMARY KEY (\"$column\");\n";
    }
    elsif(/^ALTER TABLE\s+(\w+)\s+DROP\s+PRIMARY\s+KEY\s*;\s*$/) {
        $table = $1;
        print "ALTER TABLE \"$table\" DROP CONSTRAINT \"${column}_pkey\";\n";
    }
    elsif(/^ALTER TABLE\s+(\w+)\s+DROP\s+(.*)\s*;\s*$/) {
        $table = $1;
        $column = $2;
        print "ALTER TABLE \"$table\" DROP COLUMN \"$column\";\n";
    }
    else {
        print stderr "can't convert \"$_\"\n";
    }
}
