@extends('cdash', [
    'title' => 'Manage Users'
])

@section('main_content')
    @if(strlen($warning) > 0)
        <div style="color: green;">{{ $warning }}</div><br/>
    @endif
    @if(strlen($error) > 0)
        <div style="color: red;">{{ $error }}</div>
    @endif

    <form method="post" action="{{ url('manageUsers.php') }}" name="regform">
        <table width="100%"  border="0">
            <tr>
                <td></td>
                <td  bgcolor="#DDDDDD">
                    <strong>Search for already registered users</strong>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    @if($fullemail)
                        <i>type the full email address of the user to add</i>
                    @else
                        <i>start typing a name or email address (% to display all users)</i>
                    @endif
                </td>
            </tr>
            <tr>
                <td><div align="right">Search:</div></td>
                <td>
                    <input
                        name="search"
                        type="text"
                        id="search"
                        size="40"
                        value="{{ $search }}"
                    >
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div name="newuser" id="newuser"></div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td  bgcolor="#DDDDDD">
                    <strong>Add new user</strong>
                </td>
            </tr>
            <tr class="treven">
                <td width="20%" height="2" class="nob">
                    <div align="right"> First Name: </div>
                </td>
                <td  width="80%" height="2" class="nob">
                    <input class="textbox" name="fname" size="20"/>
                </td>
            </tr>
            <tr class="trodd">
                <td width="20%" height="2" class="nob">
                    <div align="right"> Last Name: </div>
                </td>
                <td  width="80%" height="2" class="nob">
                    <input class="textbox" name="lname" size="20"/>
                </td>
            </tr>
            <tr class="treven">
                <td width="20%" height="2" class="nob">
                    <div align="right"> Email: </div>
                </td>
                <td  width="80%" height="2" class="nob">
                    <input class="textbox"  name="email" size="20"/>
                </td>
            </tr>
            <tr class="trodd">
                <td width="20%" height="2" class="nob">
                    <div align="right">Password: </div>
                </td>
                <td width="80%" height="2" class="nob">
                    <input
                        class="textbox"
                        type="password"
                        id="passwd"
                        name="passwd"
                        size="20"
                    >
                    <input
                        type="button"
                        value="Generate Password"
                        onclick="javascript:generatePassword();"
                        name="generatepassword"
                        class="textbox"
                    >
                    <span id="clearpasswd"></span>
                </td>
            </tr>
            <tr class="treven">
                <td width="20%" height="2" class="nob">
                    <div align="right">Confirm Password: </div>
                </td>
                <td width="80%" height="2" class="nob">
                    <input
                        class="textbox"
                        type="password"
                        id="passwd2"
                        name="passwd2"
                        size="20"
                    >
                </td>
            </tr>
            <tr class="trodd">
                <td width="20%" height="2" class="nob">
                    <div align="right"> Institution: </div>
                </td>
                <td  width="80%" height="2" class="nob">
                    <input class="textbox" name="institution" size="20">
                </td>
            </tr>
            <tr>
                <td width="20%" class="nob"></td>
                <td width="80%" class="nob">
                    <input type="submit" value="Add user >>" name="adduser" class="textbox"/>
                    (password will be displayed in clear text upon addition)
                </td>
            </tr>
        </table>
    </form>

    <!-- Include project roles -->
    <script src="{{ asset('js/cdashManageUsers.js') }}" type="text/javascript"></script>

    <!-- Functions to confirm the email -->
    <script language="JavaScript" type="text/javascript">
        // TODO: (williamjallen) Move this to a separate JS file

        $(document).ready(function() {
            $(window).keydown(function(event){
                if(event.keyCode == 13) {
                    event.preventDefault();
                    return false;
                }
            });
        });

        function confirmRemove() {
            return window.confirm("Are you sure you want to remove this user from the database?");

        }

        function generatePassword()
        {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
            let passwd = "";
            for(let x = 0; x < 12; x++)
            {
                passwd += chars.charAt(Math.floor(Math.random() * 62));
            }
            $("input#passwd").val(passwd);
            $("input#passwd2").val(passwd);
            $("#clearpasswd").html("("+passwd+")");
        }
    </script>
@endsection
