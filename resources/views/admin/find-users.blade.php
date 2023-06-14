<table width="100%" border="0">
    @forelse ($users as $user_array)
        <tr>
            <td width="20%" bgcolor="#EEEEEE">
                <font size="2">{{ $user_array->firstname }} {{ $user_array->lastname }} ({{ $user_array->email }})</font>
            </td>
            <td bgcolor="#EEEEEE">
                <font size="2">
                    <form method="post" action="" name="formuser_{{ $user_array->id }}">
                        <input name="userid" type="hidden" value="{{ $user_array->id }}">
                        @if($user_array->admin)
                            Administrator
                            @if($user_array->id > 1)
                                <input name="makenormaluser" type="submit" value="make normal user">
                            @endif
                        @else
                            Normal User
                            <input name="makeadmin"  type="submit" value="make admin">
                        @endif

                        @if(intval($user_array->id) > 1)
                            <input name="removeuser" type="submit" onclick="return confirmRemove()" value="remove user">
                        @endif
                        <input name="search" type="hidden" value="{{ $search }}">
                    </form>
                </font>
            </td>
        </tr>
    @empty
        <tr><td>[none]</tr></td>
    @endforelse
</table>
