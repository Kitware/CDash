@extends('cdash', [
    'title' => 'My Profile'
])

@section('main_content')
    @if($error)
        <div style="color: red;">{{ $error }}</div>
        <br>
    @endif
    @if($message)
        <div style="color: green;">{{ $message }}</div>
        <br>
    @endif

    <form method="post" action="" id="profile_form">
        @csrf
    </form>
    <form method="post" action="" id="password_form">
        @csrf
    </form>
    <form method="post" action="" id="credentials_form">
        @csrf
    </form>

    <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb striped">
        <thead>
            <tr>
                <th class="table-heading1" colspan="100">
                    <h3>My Profile</h3>
                </th>
            </tr>
        </thead>
        <tbody>
            {{-- Profile Form --}}
            <tr>
                <td width="20%" height="2">
                    <div align="right">First Name</div>
                </td>
                <td width="80%" height="2" >
                    <input
                        class="textbox"
                        name="fname"
                        size="20"
                        value="{{ $user->FirstName }}"
                        form="profile_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%" height="2">
                    <div align="right">Last Name</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        name="lname"
                        size="20"
                        value="{{ $user->LastName }}"
                        form="profile_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%" height="2">
                    <div align="right">Email</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        name="email"
                        size="20"
                        value="{{ $user->Email }}"
                        form="profile_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%" height="2">
                    <div align="right"> Institution</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        name="institution"
                        size="20"
                        value="{{ $user->Institution }}"
                        form="profile_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%" ></td>
                <td width="80%">
                    <input
                        type="submit"
                        value="Update Profile"
                        name="updateprofile"
                        class="textbox"
                        form="profile_form"
                    >
                </td>
            </tr>

            {{-- Password Form --}}
            <tr>
                <td width="20%" height="2">
                    <div align="right">Current Password</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        type="password"
                        name="oldpasswd"
                        size="20"
                        form="password_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%" height="2">
                    <div align="right">New Password</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        type="password"
                        name="passwd"
                        size="20"
                        form="password_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%" height="2">
                    <div align="right">Confirm Password</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        type="password"
                        name="passwd2"
                        size="20"
                        form="password_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%"></td>
                <td width="80%">
                    <input
                        type="submit"
                        value="Update Password"
                        name="updatepassword"
                        class="textbox"
                        form="password_form"
                    >
                </td>
            </tr>

            {{-- Credentials Form --}}
            <tr>
                <td width="20%" height="2">
                    <div align="right">Repository Credential #1</div>
                </td>
                <td width="80%" height="2">
                    @if(count($credentials) === 0)
                        Not found (you should really add it)
                    @else
                        {{ $credentials[0] }}
                    @endif
                </td>
            </tr>
            <tr>
                <td width="20%" height="2">
                    <div align="right">Repository Credential #2</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        type="text"
                        name="credentials[1]"
                        value="{{ $credentials[1] ?? '' }}"
                        form="credentials_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%" height="2">
                    <div align="right">Repository Credential #3</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        type="text"
                        name="credentials[2]"
                        value="{{ $credentials[2] ?? '' }}"
                        form="credentials_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%" height="2">
                    <div align="right">Repository Credential #4</div>
                </td>
                <td width="80%" height="2">
                    <input
                        class="textbox"
                        type="text"
                        name="credentials[3]"
                        value="{{ $credentials[3] ?? '' }}"
                        form="credentials_form"
                    >
                </td>
            </tr>
            <tr>
                <td width="20%"></td>
                <td width="80%">
                    <input
                        type="submit"
                        value="Update Credentials"
                        name="updatecredentials"
                        class="textbox"
                        form="credentials_form"
                    >
                </td>
            </tr>
            {{-- Other Misc Info --}}
            <tr>
                <td width="20%" height="2">
                    <div align="right">Internal Id</div>
                </td>
                <td  width="80%" height="2">{{ $user->Id }}</td>
            </tr>
        </tbody>
    </table>
@endsection
