<table width="100%" border="0">
    @forelse($users as $user_array)
        <tr>
            <td width="20%" bgcolor="#EEEEEE">
                <font size="2">{{ $user_array->firstname }} {{ $user_array->lastname }} ({{ $user_array->email }})</font>
            </td>
            <td bgcolor="#EEEEEE">
                <font size="2">
                    <form method="post" action="" name="formuser_{{ $user_array->id }}">
                        <input name="userid" type="hidden" value="{{ $user_array->id }}">
                        role: <select name="role">
                            <option value="0">Normal User</option>
                            <option value="1">Site maintainer</option>
                            <option value="2">Project administrator</option>
                        </select>
                        Repository credential: <input name="repositoryCredential" type="text" size="20"/>
                        <input name="adduser" type="submit" value="add user">
                    </form>
                </font>
            </td>
        </tr>
    @empty
        <tr><td>[none]</tr></td>
    @endforelse
</table>
